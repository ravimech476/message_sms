#!/usr/bin/env bash
#
# monitor_services.sh — SMS Expert production health monitor.
#
# Checks system resources (CPU / memory / disk / load) AND the app's services
# (supervisor programs, MySQL, Redis, RabbitMQ + queue backlog). Sends a single
# email when anything breaches a threshold, with per-alert throttling so an ongoing
# problem does not spam you every run.
#
# Install (run every 5 minutes via cron on the production box):
#   */5 * * * * /var/www/sms_expert_new-BE/scripts/monitor_services.sh >/dev/null 2>&1
#
# Requires: bash, awk, and one of (mail|mailx|sendmail). Optional: supervisorctl,
# mysqladmin/mysql, redis-cli, rabbitmqctl, vmstat, bc.
#
set -uo pipefail

# ─────────────────────────────── CONFIG ────────────────────────────────────
# Where to send alerts (comma-separated is fine for `mail`).
ALERT_EMAIL="${ALERT_EMAIL:-ops@smsexpert.co.uk}"
ALERT_FROM="${ALERT_FROM:-monitor@smsexpert.co.uk}"
HOSTNAME_LABEL="$(hostname 2>/dev/null || echo unknown-host)"

# Thresholds (percent).
CPU_THRESHOLD="${CPU_THRESHOLD:-80}"     # alert when CPU usage > this
MEM_THRESHOLD="${MEM_THRESHOLD:-85}"
DISK_THRESHOLD="${DISK_THRESHOLD:-90}"
# Load average per-core multiplier (alert if 1-min load > cores * this).
LOAD_PER_CORE="${LOAD_PER_CORE:-2.0}"
# RabbitMQ: alert if any monitored queue has more than this many ready messages.
QUEUE_BACKLOG_THRESHOLD="${QUEUE_BACKLOG_THRESHOLD:-5000}"

# App root (used for artisan / cwd if needed).
APP_DIR="${APP_DIR:-/var/www/sms_expert_new-BE}"

# Re-alert cool-down: don't re-email the SAME alert within this many minutes.
COOLDOWN_MINUTES="${COOLDOWN_MINUTES:-30}"
STATE_DIR="${STATE_DIR:-/tmp/smsexpert-monitor}"

# Tool paths (override if not on PATH).
SUPERVISORCTL="${SUPERVISORCTL:-supervisorctl}"
MYSQLADMIN="${MYSQLADMIN:-mysqladmin}"
REDIS_CLI="${REDIS_CLI:-redis-cli}"
RABBITMQCTL="${RABBITMQCTL:-rabbitmqctl}"

# MySQL / Redis connection (leave blank to use local socket / defaults).
MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASS="${MYSQL_PASS:-}"
REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
REDIS_PORT="${REDIS_PORT:-6379}"

# Supervisor programs that MUST be RUNNING. Empty = auto-detect from `supervisorctl status`.
# List the critical ones explicitly so a missing/removed program is also caught.
REQUIRED_SUPERVISOR_PROGRAMS=(
  "sms_process_queue"
  "dlr_process_buffer"
  "nexmo_process-delivery-queue"
  "smpp_monitor"
  "rabbitmq_consume_emails"
  "dlr-callback-consume"
)

# RabbitMQ queues to watch for backlog.
MONITORED_QUEUES=(
  "sms.outbound"
  "nexmo.delivery.reports"
  "dlr.callback.push"
)
# ────────────────────────────── END CONFIG ─────────────────────────────────

mkdir -p "$STATE_DIR" 2>/dev/null || true
ALERTS=""          # accumulated "key|message" lines for this run
have() { command -v "$1" >/dev/null 2>&1; }

# add_alert <key> <message>
add_alert() { ALERTS+="${1}|${2}"$'\n'; }

# Numeric compare helper (float-safe via awk).
gt() { awk -v a="$1" -v b="$2" 'BEGIN{exit !(a+0 > b+0)}'; }

# ── System: CPU ─────────────────────────────────────────────────────────────
check_cpu() {
  local usage=""
  if have vmstat; then
    # 15th column of vmstat is idle%. Sample over 1s.
    local idle; idle="$(vmstat 1 2 2>/dev/null | tail -1 | awk '{print $15}')"
    [ -n "${idle:-}" ] && usage="$(awk -v i="$idle" 'BEGIN{printf "%.0f", 100 - i}')"
  fi
  if [ -z "$usage" ]; then
    # Fallback: top single-shot, parse %id.
    local idle; idle="$(top -bn1 2>/dev/null | awk -F'[,%]+' '/Cpu\(s\)|%Cpu/{for(x=1;x<=NF;x++) if($x ~ /id/){gsub(/[^0-9.]/,"",$(x-1)); print $(x-1); exit}}')"
    [ -n "${idle:-}" ] && usage="$(awk -v i="$idle" 'BEGIN{printf "%.0f", 100 - i}')"
  fi
  [ -z "$usage" ] && { add_alert "cpu-read" "Could not read CPU usage"; return; }
  if gt "$usage" "$CPU_THRESHOLD"; then
    add_alert "cpu" "HIGH CPU: ${usage}% (threshold ${CPU_THRESHOLD}%)"
  fi
}

# ── System: Memory ──────────────────────────────────────────────────────────
check_memory() {
  local pct; pct="$(free 2>/dev/null | awk '/^Mem:/{printf "%.0f", ($2>0)?($3/$2*100):0}')"
  [ -z "${pct:-}" ] && return
  gt "$pct" "$MEM_THRESHOLD" && add_alert "mem" "HIGH MEMORY: ${pct}% used (threshold ${MEM_THRESHOLD}%)"
}

# ── System: Disk (all real mounts) ──────────────────────────────────────────
check_disk() {
  while read -r use mnt; do
    [ -z "${use:-}" ] && continue
    gt "$use" "$DISK_THRESHOLD" && add_alert "disk-${mnt}" "HIGH DISK: ${use}% on ${mnt} (threshold ${DISK_THRESHOLD}%)"
  done < <(df -P -x tmpfs -x devtmpfs -x overlay 2>/dev/null | awk 'NR>1{gsub("%","",$5); print $5, $6}')
}

# ── System: Load average ────────────────────────────────────────────────────
check_load() {
  local cores load1 limit
  cores="$(nproc 2>/dev/null || echo 1)"
  load1="$(awk '{print $1}' /proc/loadavg 2>/dev/null)"
  [ -z "${load1:-}" ] && return
  limit="$(awk -v c="$cores" -v m="$LOAD_PER_CORE" 'BEGIN{printf "%.2f", c*m}')"
  gt "$load1" "$limit" && add_alert "load" "HIGH LOAD: 1-min load ${load1} on ${cores} cores (limit ${limit})"
}

# ── Services: Supervisor programs ───────────────────────────────────────────
check_supervisor() {
  have "${SUPERVISORCTL%% *}" || { add_alert "supervisor-missing" "supervisorctl not found"; return; }
  local status; status="$($SUPERVISORCTL status 2>/dev/null)"
  [ -z "$status" ] && { add_alert "supervisor-down" "supervisorctl returned no status (supervisord down?)"; return; }

  # Any program NOT in RUNNING state → alert (covers STOPPED/FATAL/EXITED/BACKOFF).
  while read -r name state _; do
    [ -z "${name:-}" ] && continue
    case "$state" in
      RUNNING) ;;
      *) add_alert "svc-${name}" "SERVICE not running: ${name} is ${state}";;
    esac
  done <<< "$status"

  # Explicitly-required programs that are missing entirely from supervisor.
  local prog
  for prog in "${REQUIRED_SUPERVISOR_PROGRAMS[@]}"; do
    grep -qE "^${prog}(:| )" <<< "$status" || add_alert "svc-missing-${prog}" "SERVICE missing from supervisor: ${prog}"
  done
}

# ── Infra: MySQL ────────────────────────────────────────────────────────────
check_mysql() {
  have "${MYSQLADMIN%% *}" || return
  local args=(-h "$MYSQL_HOST" -u "$MYSQL_USER")
  [ -n "$MYSQL_PASS" ] && args+=("-p${MYSQL_PASS}")
  if ! $MYSQLADMIN "${args[@]}" ping >/dev/null 2>&1; then
    add_alert "mysql" "MySQL not responding (${MYSQL_HOST})"
  fi
}

# ── Infra: Redis ────────────────────────────────────────────────────────────
check_redis() {
  have "${REDIS_CLI%% *}" || return
  local pong; pong="$($REDIS_CLI -h "$REDIS_HOST" -p "$REDIS_PORT" ping 2>/dev/null)"
  [ "$pong" = "PONG" ] || add_alert "redis" "Redis not responding (${REDIS_HOST}:${REDIS_PORT})"
}

# ── Infra: RabbitMQ (up + queue backlog) ────────────────────────────────────
check_rabbitmq() {
  have "${RABBITMQCTL%% *}" || return
  if ! $RABBITMQCTL node_health_check >/dev/null 2>&1 && ! $RABBITMQCTL status >/dev/null 2>&1; then
    add_alert "rabbitmq" "RabbitMQ node not healthy"
    return
  fi
  # Queue backlog.
  local q depth
  local listing; listing="$($RABBITMQCTL list_queues -q name messages_ready 2>/dev/null)"
  [ -z "$listing" ] && return
  for q in "${MONITORED_QUEUES[@]}"; do
    depth="$(awk -v n="$q" '$1==n{print $2}' <<< "$listing")"
    [ -z "${depth:-}" ] && continue
    gt "$depth" "$QUEUE_BACKLOG_THRESHOLD" && \
      add_alert "queue-${q}" "QUEUE BACKLOG: '${q}' has ${depth} ready messages (threshold ${QUEUE_BACKLOG_THRESHOLD})"
  done
}

# ── Email dispatch with per-alert throttle ──────────────────────────────────
send_email() {
  local subject="$1" body="$2"
  if have mail; then
    printf '%s\n' "$body" | mail -s "$subject" ${ALERT_FROM:+-r "$ALERT_FROM"} "$ALERT_EMAIL"
  elif have mailx; then
    printf '%s\n' "$body" | mailx -s "$subject" "$ALERT_EMAIL"
  elif have sendmail; then
    { printf 'To: %s\nFrom: %s\nSubject: %s\n\n%s\n' "$ALERT_EMAIL" "$ALERT_FROM" "$subject" "$body"; } | sendmail -t
  else
    logger -t smsexpert-monitor "NO MAILER AVAILABLE — alert: $subject"
    return 1
  fi
}

dispatch_alerts() {
  [ -z "$ALERTS" ] && { # nothing wrong → clear any stale state so recovery re-alerts next time
    return 0
  }

  local now cutoff key msg to_send=""
  now="$(date +%s)"
  cutoff=$(( COOLDOWN_MINUTES * 60 ))

  while IFS='|' read -r key msg; do
    [ -z "${key:-}" ] && continue
    local sf="${STATE_DIR}/${key}.last"
    local last=0
    [ -f "$sf" ] && last="$(cat "$sf" 2>/dev/null || echo 0)"
    if [ $(( now - last )) -ge "$cutoff" ]; then
      to_send+="  • ${msg}"$'\n'
      echo "$now" > "$sf"
    fi
  done <<< "$ALERTS"

  [ -z "$to_send" ] && return 0   # all alerts still within cool-down

  local subject body
  subject="[SMS Expert ALERT] ${HOSTNAME_LABEL}: $(printf '%s' "$to_send" | grep -c '•') issue(s)"
  body="$(cat <<EOF
Health alert from ${HOSTNAME_LABEL} at $(date '+%Y-%m-%d %H:%M:%S %Z')

The following checks breached their thresholds:

${to_send}
Thresholds: CPU>${CPU_THRESHOLD}%  MEM>${MEM_THRESHOLD}%  DISK>${DISK_THRESHOLD}%
Cool-down: ${COOLDOWN_MINUTES} min per alert (so ongoing issues don't spam).

-- SMS Expert monitor (${APP_DIR}/scripts/monitor_services.sh)
EOF
)"
  send_email "$subject" "$body"
}

# ─────────────────────────────── RUN ───────────────────────────────────────
check_cpu
check_memory
check_disk
check_load
check_supervisor
check_mysql
check_redis
check_rabbitmq
dispatch_alerts
exit 0
