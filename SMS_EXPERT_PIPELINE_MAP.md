# sms_expert — SMS Pipeline Map (SMPP + RabbitMQ + smsg_log)

> Reference for porting the full outbound/DLR pipeline into the **FootFall (bulk-sms)** project.
> Source project: `D:\MESSAGE\message_sms\sms_expert_new-BE` (Laravel 11 / PHP 8.2).

---

## 0. The whole pipeline at a glance

```
                          ┌──────────────────────── OUTBOUND (send) ────────────────────────┐
 Client / Campaign            API endpoint            RabbitMQ                Worker (SMPP)            Vonage SMPP
 ───────────────           ───────────────         ─────────────          ──────────────────         ───────────
  POST /api/smsg/sms.mes  →  Controller           →  publish to        →   sms:process-queue      →   submit_sm
  POST /api/mobile/.../send   + write smsg_log row    queue             (ProcessSmsQueue)              over TCP bind
  POST /api/smpp/send         (status=pending)       "sms.outbound"      → SMPPService->sendSMS()   →  smpp-eu.vonage.com:8000
                                                                          → capture message_id
                                                                          → UPDATE smsg_log
                                                                            (sentstatus=ok, msgref)

                          └──────────────────────── INBOUND (DLR / status) ────────────────┘
 Vonage SMPP               SMPP receiver banks        Buffer table            Buffer processor          smsg_log
 ───────────               ───────────────────      ──────────────         ──────────────────         ────────
  deliver_sm (DLR)      →   smpp:dlr-receiver     →  smsg_receipt_        →  dlr:process-buffer     →  UPDATE
  id:.. stat:DELIVRD        --bank=a0..j0            buffer_new              (match msgref, indexed)     deliverystatus2
                            (10 banks)               (XMLDATA = JSON)        → DeliveryStatusService     deliverytime2
```

**Two independent halves:**
- **Send** = API → `smsg_log` row + RabbitMQ publish → SMPP worker `submit_sm` → update row with message-id + `sentstatus`.
- **DLR** = SMPP receiver → buffer table → buffer processor matches the message-id → updates the same `smsg_log` row's delivery status.

RabbitMQ is the **decoupler**: the API never talks to SMPP directly; it drops a job on a queue and a long-running consumer does the socket work.

---

## 1. Outbound (send) flow — step by step

### 1a. Entry points (`routes/api.php`)
| Endpoint | Controller | Use |
|---|---|---|
| `POST /api/smsg/sms.mes` | `LegacySmsApiController@sendSms` | Legacy OLD-SYSTEM API (query-string params) |
| `POST /api/mobile/sms/send` | `Api\Mobile\SmsController@send` | Mobile app |
| `POST /api/smpp/send` `/send-bulk` | `SmppQueueController@sendSms` | SMPP queue API |

All send paths converge on **`SmsSendingService`** (1,653 lines) — the business logic: validate wallet, compute parts/cost, resolve operator by sender ID, write the `smsg_log` row(s), and publish to RabbitMQ.

### 1b. One `smsg_log` row **per recipient**
- Comma-separated numbers (incl. duplicates) each get their **own** `smsg_log` row, one `submit_sm`, one message-id, one DLR — **no dedup** (OLD-SYSTEM parity).
- The row is created with `sentstatus='pending'`, `timesent='00000000000000'`.
- `smsg_log.text` is stored **URL-encoded** (display layer decodes it).

### 1c. Publish to RabbitMQ (`SmsQueueService` → `RabbitMQService`)
- Payload carries the message + **`smsg_log_id`** in metadata — this row id is threaded end-to-end so the post-send message-id UPDATE hits the exact row.
- Published to queue **`sms.outbound`**.
- `RabbitMQService::publishToQueue()` — JSON-encodes; on malformed UTF-8 it repairs via `deepUtf8()` and **refuses to publish an empty body** (a real bug fix — `£`/Latin-1 was silently dropping messages).

### 1d. Consume + send (`ProcessSmsQueue` = `sms:process-queue`)
- Long-running consumer of `sms.outbound` (`--provider=nexmo|sinch|both`).
- Instantiates **`SMPPService`** (Vonage) or **`SinchSmppService`**.
- Reads `metadata.smsg_log_id` (required) so it can update the right row.
- Calls `SMPPService->sendSMS($to,$msg,$from,...,$smsgLogId)`.

### 1e. SMPP send (`SMPPService`, 4,461 lines)
- **Bind:** `bind_transmitter` (send-only, so the send socket is **not** starved by inbound DLR flood) — `SMPP_SEND_TRANSMITTER_ONLY=true`. Transceiver is the alternate.
- **`submit_sm`** PDU: sender (TON/NPI), encoding (**GSM 03.38 7-bit** for `data_coding=0`, **UCS2** for unicode — split at 132 octets for concat), `registered_delivery=1` to request a DLR, per-worker concat reference slice.
- **Capture** the `submit_sm_resp` → **message_id** (hex).
- **On success**, `storeMessageIdMapping()` updates the `smsg_log` row:
  - `sentstatus='ok'`, `timesent=<now>`
  - `suppliermsgref` / `deliveryreceipt1` = provider message-id
  - **`onesixty_suppliermsgref`** = message-id in **decimal** (the INDEXED column the DLR matches on)
  - charges the wallet (`smsg_server1_sent += userprice`) — **charge happens here, on submit-success**.

### Multi-bank sending (scale)
- `config/smpp_banks.php` defines 10 banks `a0..j0`, each a separate bind with a **partitioned seq_id range**, spread across 3 Vonage EU hosts. Vonage routes each DLR back to the bind whose seq_id sent it. (`SMPP_BANKS_ENABLED=true`.)

---

## 2. RabbitMQ layer

### `RabbitMQService` (1,249 lines) — the shared bus
- `publishToQueue($queue,$data)` — publish with UTF-8 guard.
- `consumeFromQueue($queue,$callback)` — the consumer loop used by **every** consumer.
- **Ack/retry model:** callback returns `true` → ack; `false` → ack + **republish with exponential backoff** (10/20/40…/300s, up to `RABBITMQ_MAX_RETRIES`); **throws** → ack + backoff **and** email alert; exhausted → dead-letter + alert.
- **Per-queue logging:** every event also written to `storage/logs/{date}/rabbitmq/{queue}.log` with a payload summary.
- **Universal error alerts:** any consumer exception → `SmppErrorAlertService::notifyTransient()` (throttled — one email per incident, not per message).

### The queues / consumers (`laravel-rabbitmq.conf`, supervisor)
| Program (supervisor) | Command | Role |
|---|---|---|
| `sms_process_queue` ×5 | `sms:process-queue --queue=sms.outbound --provider=nexmo` | **Send** SMS over SMPP |
| `sms-expert-smpp-dlr-bank-{a0..j0}` ×10 | `smpp:dlr-receiver --bank=a0` | **Receive DLR** over SMPP |
| `dlr_process_buffer` ×1 | `dlr:process-buffer --continuous` | Match DLRs → update `smsg_log` |
| `sms_dlr_consume` ×3 | `dlr:consume --queue=sms.dlr` | (RabbitMQ DLR path — alt to buffer) |
| `campaign_consume` | `campaign:consume` | Campaign fan-out |
| `rabbitmq_consume_emails` | `rabbitmq:consume-emails` | Email queue |
| `queue-webhook-dlr` / `-inbound` | `queue:webhook --type=…` | Provider webhooks |
| `nexmo_process-delivery-queue` | `nexmo:process-delivery-queue` | Vonage Reports-API DLR (paid, backup) |
| `queue-push-notifications` | `queue:push-notifications` | FCM |
| `reports-consume`, `dlr-callback-consume`, `smpp_monitor`, … | | reporting, customer DLR push, health |

---

## 3. Inbound (DLR / delivery status) flow — step by step

### 3a. Receive (`SmppDlrReceiver` = `smpp:dlr-receiver --bank=..`)
- Binds `bind_receiver` per bank, listens for **`deliver_sm`** PDUs with `ESM_CLASS=0x04` (DLR).
- Parses the DLR body: `id:XXX stat:DELIVRD err:000 done_date:...`.
- **Buffers** it into table **`smsg_receipt_buffer_new`** — a fast insert; the `XMLDATA` column holds the DLR as **JSON** (`message_id`, `mobile_number`, `status`, `done_date`). `status='new'`.
- *(Fast insert now, heavy matching later — the OLD-SYSTEM `daemon_dreceipt_inbound_buffer.php` model.)*

### 3b. Process (`ProcessDlrBuffer` = `dlr:process-buffer --continuous`)
1. **Reclaim** rows stuck in `status='doing'` (crashed worker) back to `'new'`.
2. **Claim** a batch: `UPDATE … SET status='doing' WHERE status='new'` (atomic — prevents double-processing).
3. For each: **match** the provider message-id against **`smsg_log.onesixty_suppliermsgref`** (the INDEXED decimal column) and hand to `DeliveryStatusService`.
4. `DeliveryStatusService` (673 lines) maps the SMPP status → OLD-SYSTEM label and **UPDATEs `smsg_log`**:
   - `deliverystatus2` = `Delivered` / `Non Delivered` / `Unknown` / `Lost Notification`
   - `deliverytime2` = DLR `done_date` in **GMT/UTC** (display layer converts to Europe/London)
   - `deliveryreceipt2`, `aggregator_dlrcode/msg`

### The message-id matching chain (critical)
Vonage sends the id as **hex** in `submit_sm_resp` but **decimal** in the DLR. So:
```
submit_sm_resp id (hex)  →  onesixty_suppliermsgref (decimal, indexed)  ←  DLR id (decimal)
```
`DeliveryStatusService::mapToDeliveryStatus()` also maps SMPP status words: `DELIVRD→Delivered`, `EXPIRED/UNDELIV/REJECTD/FAILED→Non Delivered`, unknown→`Unknown` (+warning).

---

## 4. Storage schema

### `smsg_log` — the single source of truth (one row per SMS)
Key columns (of ~60):
| Column | Meaning | Set when |
|---|---|---|
| `id` | PK — threaded through the whole pipeline | on create |
| `bigid` / `userref` | customer refs | on create |
| `mobnum` | recipient | on create |
| `text` | message (**URL-encoded**) | on create |
| `originator` | sender ID | on create |
| `sentstatus` | `pending` → **`ok`** (submitted) / `failed` | at send |
| `timesent` | send timestamp (`00000000000000` until sent) | at send |
| `suppliermsgref` / `deliveryreceipt1` | provider message-id | at send |
| **`onesixty_suppliermsgref`** | message-id **decimal** — DLR matches this (INDEXED) | at send |
| `costprice` / `userprice` / `profit` | pricing | at send |
| `chargetype` | combined user+route billing code | nightly stamp |
| **`deliverystatus2`** | final DLR status | on DLR |
| `deliverytime2` | DLR time (GMT) | on DLR |
| `deliveryreceipt2` | raw DLR | on DLR |
| `dayofyear` (`YYYYMMDD`) | date partition column for reports | on create |
| `suppliername` | e.g. `Vonage SMS` / `sinch` | at send |

- **Archives:** `smsg_log_YYMM` monthly tables hold history (~26M rows across ~24 tables in prod). Reports scan live `smsg_log` + only the overlapping archive months.

### `smsg_receipt_buffer_new` — the DLR inbox
| Column | Meaning |
|---|---|
| `id` | PK |
| `XMLDATA` | the DLR as **JSON** (message_id, mobile, status, done_date) |
| `status` | `new` → `doing` → (processed = row consumed) |

---

## 5. Key files (what to port)

| Concern | File(s) in sms_expert |
|---|---|
| Send business logic | `app/Services/SmsSendingService.php` |
| Queue publish | `app/Services/Queue/SmsQueueService.php` |
| RabbitMQ bus | `app/Services/Queue/RabbitMQService.php` |
| Send consumer | `app/Console/Commands/ProcessSmsQueue.php` (`sms:process-queue`) |
| SMPP protocol | `app/Services/SMPP/SMPPService.php`, `SMPPPoolManager.php`, `config/smpp_banks.php` |
| DLR receiver | `app/Console/Commands/SmppDlrReceiver.php` (`smpp:dlr-receiver`) |
| DLR buffer processor | `app/Console/Commands/ProcessDlrBuffer.php` (`dlr:process-buffer`) |
| Status mapping | `app/Services/DeliveryStatusService.php` |
| Storage | `smsg_log`, `smsg_log_YYMM`, `smsg_receipt_buffer_new` |
| Worker orchestration | `laravel-rabbitmq.conf` (supervisor) |
| Config | `.env` → `SMPP_HOST/PORT/SYSTEM_ID/PASSWORD`, `SMPP_BANKS_ENABLED`, `RABBITMQ_*`, `DLR_USE_BUFFER` |

---

## 6. What the FootFall port needs (bridge to the phases)

| sms_expert piece | FootFall today | Port action |
|---|---|---|
| RabbitMQ (`php-amqplib`) | DB queue | Add RabbitMQ container + lib + `RabbitMQService` |
| SMPP (`php-smpp`, `sockets`, `pcntl`) | Vonage REST | Add extensions + lib + `SMPPService` (port), bind to same Vonage SMPP account |
| `smsg_log` (+ buffer) | `messages`/`message_updates` | Migration for `smsg_log` + `smsg_receipt_buffer_new` |
| `sms:process-queue` consumer | 1 `queue:work` | New consumer command + supervisor/compose worker |
| `smpp:dlr-receiver` + `dlr:process-buffer` | none (REST status) | New receiver + buffer processor |
| PHP 8.x | PHP 7.2 | **Upgrade FootFall Docker base image to PHP 8.x first** |

**Recommended port order:** PHP-8 Docker + extensions + RabbitMQ container (Phase 0) → `smsg_log` schema (1) → `SMPPService` + send (2) → RabbitMQ publish/consume (3) → DLR receiver + buffer + status (4) → parity test on the real SMPP account (5).

---

*Generated as the design reference. Next: decide whether to port the multi-bank SMPP setup from day one or start with a single bind and add banks later.*
