# Mobile API Error Monitoring System

## Overview

The API Error Monitoring system automatically tracks, logs, and sends email notifications via **RabbitMQ** when errors occur in the Mobile API endpoints. This helps identify and resolve issues quickly.

---

## Features

- ✅ **Automatic Error Detection**: Catches all 4xx and 5xx responses
- ✅ **Exception Logging**: Logs full stack traces for exceptions
- ✅ **Database Logging**: Stores all errors in `api_error_logs` table
- ✅ **File Logging**: Writes to `storage/logs/api-errors.log`
- ✅ **Email via RabbitMQ**: Queues emails to RabbitMQ for async processing
- ✅ **Rate Limiting**: Limits emails to prevent flooding (default: 10/hour)
- ✅ **Severity Levels**: low, medium, high, critical
- ✅ **Sensitive Data Masking**: Passwords and tokens are redacted
- ✅ **Fallback**: Direct mail if RabbitMQ fails

---

## Configuration

### Environment Variables (.env)

```env
# Enable/disable email notifications
API_MONITOR_EMAIL_ENABLED=true

# Email recipients (comma-separated)
API_MONITOR_EMAIL_RECIPIENTS=admin@example.com,dev@sms.expert

# Maximum emails per hour (rate limiting)
API_MONITOR_MAX_EMAILS_HOUR=10

# Minimum severity to send email (low, medium, high, critical)
API_MONITOR_MIN_SEVERITY=high

# Log to database
API_MONITOR_LOG_DATABASE=true

# Days to keep logs
API_MONITOR_RETENTION_DAYS=30
```

### RabbitMQ Configuration (already set)

```env
RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
```

---

## How It Works

### Email Flow via RabbitMQ

```
┌──────────────────────────────────────────────────────────────┐
│                      API REQUEST                              │
│                  POST /api/mobile/sms/send                    │
└──────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────┐
│              ApiErrorMonitor Middleware                       │
│  Catches error response (4xx/5xx)                            │
└──────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────┐
│              ApiErrorMonitorService                           │
│                                                               │
│  1. Log to file (api-errors.log)                              │
│  2. Log to database (api_error_logs)                          │
│  3. Check severity & rate limit                               │
│  4. Queue email to RabbitMQ                                   │
└──────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────┐
│              RabbitMQ Queue                                   │
│              (email.notifications)                            │
└──────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────┐
│         Email Queue Consumer (runs continuously)              │
│         php artisan rabbitmq:consume-emails                   │
│                                                               │
│  1. Reads message from queue                                  │
│  2. Creates ApiErrorNotification mailable                     │
│  3. Sends email via configured mail driver                    │
└──────────────────────────────────────────────────────────────┘
```

---

## Severity Levels

| Level | HTTP Status | Sends Email | Description |
|-------|-------------|-------------|-------------|
| critical | 5xx | ✅ Yes | Server errors, database errors |
| high | 5xx | ✅ Yes | Unhandled exceptions |
| medium | 4xx | ❌ No* | Client errors (bad request, forbidden) |
| low | 4xx | ❌ No | Validation, auth failures |

*Email only sent if min_email_severity is set to 'medium'

---

## Database Table

### `api_error_logs`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| type | string | 'exception' or 'response_error' |
| severity | string | low, medium, high, critical |
| method | string | HTTP method (GET, POST, etc.) |
| path | string | API endpoint path |
| url | text | Full URL |
| ip_address | string | Client IP |
| user_id | bigint | User ID (if authenticated) |
| user_bigid | string | User BigID (if authenticated) |
| status_code | int | HTTP status code |
| error_message | text | Error message |
| exception_class | string | Exception class name |
| exception_file | string | File where error occurred |
| exception_line | int | Line number |
| request_data | json | Request details (sanitized) |
| response_data | json | Response details |
| trace | json | Stack trace (top 10 frames) |
| email_sent | bool | Whether email was queued |
| email_sent_at | timestamp | When email was queued |
| created_at | timestamp | When error occurred |

---

## Artisan Commands

### View Error Statistics
```bash
# Last 7 days (default)
php artisan api:error-stats

# Last 30 days
php artisan api:error-stats --days=30

# Filter by severity
php artisan api:error-stats --severity=critical
```

### Clean Old Logs
```bash
# Use config default (30 days)
php artisan api:clean-error-logs

# Custom days
php artisan api:clean-error-logs --days=14

# Dry run (show what would be deleted)
php artisan api:clean-error-logs --dry-run
```

### Test Error Monitor
```bash
# Test configuration, database, and RabbitMQ connection
php artisan api:test-error-monitor

# Test email via RabbitMQ (queues email)
php artisan api:test-error-monitor --send-email

# Test email directly (bypass RabbitMQ)
php artisan api:test-error-monitor --direct-mail
```

### Email Queue Consumer (must be running!)
```bash
# Start consumer (keeps running)
php artisan rabbitmq:consume-emails

# With timeout
php artisan rabbitmq:consume-emails --timeout=60

# Process limited messages
php artisan rabbitmq:consume-emails --max-messages=10
```

---

## Setup Steps

### 1. Run Migration
```bash
php artisan migrate
```

### 2. Configure .env
```env
API_MONITOR_EMAIL_ENABLED=true
API_MONITOR_EMAIL_RECIPIENTS=your-email@example.com,team@example.com
```

### 3. Clear Cache
```bash
php artisan config:clear
php artisan cache:clear
```

### 4. Start Email Consumer
```bash
# In production, run as background service
php artisan rabbitmq:consume-emails
```

### 5. Test the System
```bash
# Test configuration
php artisan api:test-error-monitor

# Test with email (make sure consumer is running)
php artisan api:test-error-monitor --send-email
```

---

## Running the Email Consumer

The email consumer **MUST be running** to process queued emails.

### Development
```bash
php artisan rabbitmq:consume-emails
```

### Production (Supervisor)

Create `/etc/supervisor/conf.d/email-consumer.conf`:

```ini
[program:email-consumer]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/artisan rabbitmq:consume-emails --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/your/app/storage/logs/email-consumer.log
stopwaitsecs=3600
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start email-consumer:*
```

---

## Log Files

| File | Location | Description |
|------|----------|-------------|
| API Errors | `storage/logs/api-errors.log` | Daily rotating log |
| Email Consumer | `storage/logs/email-consumer.log` | Consumer output |
| Cleanup Log | `storage/logs/{date}/api-error-cleanup.log` | Cleanup job output |

---

## Files Created/Updated

| File | Description |
|------|-------------|
| `app/Services/ApiErrorMonitorService.php` | Main service (uses RabbitMQ) |
| `app/Http/Middleware/ApiErrorMonitor.php` | Middleware |
| `app/Mail/ApiErrorNotification.php` | Email class |
| `resources/views/emails/api-error-notification.blade.php` | Email template |
| `config/api_monitor.php` | Configuration |
| `database/migrations/2024_12_10_create_api_error_logs_table.php` | Migration |
| `app/Console/Commands/CleanApiErrorLogs.php` | Cleanup command |
| `app/Console/Commands/ApiErrorStats.php` | Stats command |
| `app/Console/Commands/TestApiErrorMonitor.php` | Test command |
| `app/Console/Commands/ConsumeEmailQueueCommand.php` | Updated: handles ApiErrorNotification |

---

## Troubleshooting

### Emails Not Sending

1. Check consumer is running: `ps aux | grep consume-emails`
2. Check RabbitMQ: `php artisan api:test-error-monitor` (shows connection status)
3. Check queue has messages: Look at "Messages pending" in test output
4. Check mail configuration in `.env`
5. Check `API_MONITOR_EMAIL_ENABLED=true`
6. Check `API_MONITOR_EMAIL_RECIPIENTS` is set

### RabbitMQ Not Connected

1. Verify RabbitMQ is running: `systemctl status rabbitmq-server`
2. Check credentials in `.env`
3. Test connection: `php artisan api:test-error-monitor`

### Logs Not Appearing

1. Check `api_error_logs` table exists: `php artisan migrate`
2. Check middleware is registered in `bootstrap/app.php`
3. Check routes use the middleware

### Too Many Emails

1. Increase `API_MONITOR_MAX_EMAILS_HOUR`
2. Change `API_MONITOR_MIN_SEVERITY` to 'critical'
3. Add paths to `excluded_paths` in config

---

## Email Example

When an error occurs, you'll receive an email like this:

**Subject:** `[CRITICAL] SMS Expert - Mobile API Error: /api/mobile/sms/send`

**Content:**
- Error severity and timestamp
- Endpoint and status code
- Full error message
- Request details (IP, user, device)
- Stack trace
- Environment info

---

*Last Updated: December 2024*
