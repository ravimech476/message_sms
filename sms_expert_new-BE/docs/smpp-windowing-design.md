# SMPP Windowed Sending — Design (Part 2)

**Status:** Design / not implemented
**Depends on:** Part 1 (transmitter-only send bind — `SMPP_SEND_TRANSMITTER_ONLY`), async publish (`SMS_ASYNC_PUBLISH`)
**Owner:** _tbd_  ·  **Last updated:** 2026-07-15

> Only build this if a **single bind** must push more than a few tens of SMS/sec.
> At the current ~0.80/s inbound, Part 1 (~4/s/worker) + Part 3 (N workers) already
> suffice. Windowing is the high-throughput, higher-risk option — hundreds/sec on one bind.

---

## 1. Problem

Today `SMPPService::sendSMS()` is **synchronous per message**:

```
consume 1 (prefetch=1) → build submit_sm → send → BLOCK waiting for submit_sm_resp → price/wallet → ack → next
```

Throughput ceiling ≈ `1 / round_trip_latency`. Even with a healthy bind (~200 ms RTT) that's ~5/sec; when the response is delayed it collapses. SMPP is designed to **pipeline**: keep many `submit_sm` in flight and match each `submit_sm_resp` back by `sequence_number`.

## 2. Goal

Send up to **W** `submit_sm` PDUs without waiting for each response, matching responses asynchronously. Target: hundreds/sec on one bind, bounded memory, no lost messages, no double-charge, correct `deliveryreceipt1` capture.

Non-goals: changing the DLR path (Part 1 already split it onto a separate receiver bind), changing pricing rules (2c already defers charge to send-time).

## 3. Model change: per-message blocking → single-process event loop

PHP is single-threaded per worker, so "reader" and "sender" are **cooperative in one loop**, not threads. The worker becomes an event loop:

```
loop:
  1. FILL:   while (inflight < W and queue has a message):  pull msg, send submit_sm, record in inflight map
  2. DRAIN:  read all currently-available PDUs (NON-blocking):
               submit_sm_resp  -> resolve inflight[seq]: price+wallet+deliveryreceipt1, ACK the AMQP msg
               enquire_link    -> reply enquire_link_resp
               (deliver_sm     -> only if transceiver; with Part 1 transmitter bind this never arrives)
  3. SWEEP:  fail/requeue any inflight past its per-message deadline
  4. idle a few ms if nothing happened
```

The heart of Part 2 is replacing the inline blocking wait in `sendSMS()` with `FILL` + a shared `DRAIN`/`SWEEP` owned by the worker loop.

## 4. Data structures (extend what already exists)

`SMPPService` already has the right primitives — reuse them:

| Existing field | Role in windowing |
|---|---|
| `$pendingMessages[seq]` | The **in-flight map**. Extend the payload (below). |
| `$pendingSubmitResponses[seq]` | Out-of-order `submit_sm_resp` buffer — already handles responses arriving before we look. |
| `$deferredDeliverSm[]` | DLR buffer — irrelevant on a transmitter bind (Part 1); keep for transceiver fallback. |
| `nextSequenceNumber()` | Monotonic seq per submit — the correlation key. |

**In-flight entry** (`$pendingMessages[$seq]`):
```php
[
  'seq'           => int,
  'smsg_log_id'   => int,      // exact row to price/update (already threaded from the queue)
  'bigid'         => string,   // reference_id
  'mobile'        => string,
  'from'          => string,
  'country_code'  => string,
  'num_parts'     => int,
  'initiator'     => string,
  'amqp_tag'      => mixed,     // RabbitMQ delivery tag -> ack THIS message when its resp lands
  'submitted_at'  => float,     // microtime for the deadline sweep
]
```

## 5. Flow control / backpressure = RabbitMQ prefetch

Set the AMQP **prefetch = W**. RabbitMQ then never hands the worker more than W un-acked messages. So:

- Window size `W` == prefetch == max in-flight. One knob.
- When the window is full, `FILL` naturally blocks on "no more deliveries" until a `submit_sm_resp` lets us ACK one and free a slot.
- No custom credit accounting needed — the broker enforces it.

`W` from env: `SMPP_WINDOW_SIZE` (default **1** = today's behavior; e.g. 20–50 to enable).

## 6. Correlated ACK (the critical correctness rule)

**Never ACK a RabbitMQ message until its `submit_sm_resp` is resolved.** The `amqp_tag` is stored in the in-flight entry; on resp:

1. `storeMessageIdMapping(...)` — price, wallet, `deliveryreceipt1 = real message_id`, `sentstatus='ok'`.
2. `rabbitMQ->ackMessage(entry['amqp_tag'])`.
3. `unset($pendingMessages[$seq])`.

If the worker dies mid-window, all un-acked (un-resolved) messages are redelivered by RabbitMQ → **at-least-once**, no loss. Idempotency guard below prevents a double-send from double-charging.

## 7. Failure modes

| Case | Handling |
|---|---|
| `submit_sm_resp` = ESME error (e.g. `ESME_RTHROTTLED`) | mark that `smsg_log` failed; **nack+requeue** (transient: throttle/backoff) or dead-letter (permanent: invalid dest). |
| No `submit_sm_resp` before deadline (`SMPP_SUBMIT_TIMEOUT`, e.g. 15s) | SWEEP: **nack+requeue** the AMQP msg (retry via another pass). Do **not** fabricate an id (Fix A). Leave `deliveryreceipt1` empty; reconciliation (`nexmo:process-delivery-queue`) backstops. |
| Connection drop mid-window | all in-flight are un-acked → RabbitMQ redelivers. On reconnect, rebuild bind, resume. |
| Throttling (`ESME_RTHROTTLED`) | dynamic window shrink + submit-rate limiter (§8). |
| Duplicate delivery (redelivery after crash) | idempotency (§9). |

## 8. TPS / throttle control

Vonage enforces a per-second submit limit. Add a token-bucket submit limiter (`SMPP_MAX_TPS`) in `FILL`. On repeated `ESME_RTHROTTLED`, **halve W** (AIMD); recover slowly. Prevents the window from stampeding the SMSC.

## 9. Idempotency (prevents double-charge on redelivery)

At-least-once delivery means a message can be re-sent after a crash. Guard in the worker before `FILL`:

- If `smsg_log[smsg_log_id].sentstatus === 'ok'` **and** `deliveryreceipt1 <> ''` → already sent; **ACK and skip** (don't re-submit, don't re-charge).
- Charge (`increment smsg_server1_sent`) happens exactly once, keyed off the transition to `ok` in `storeMessageIdMapping`. Make that update conditional (`WHERE sentstatus <> 'ok'`) so a duplicate resolve can't double-deduct.

## 10. DLR interaction

Unchanged by windowing. With Part 1 (transmitter send bind), DLRs arrive on the **separate `smpp:dlr-receiver`** and match by the real `deliveryreceipt1` that windowing now reliably captures. If a transmitter bind is *not* used (transceiver + windowing), the `DRAIN` step must also route `deliver_sm` to the DLR handler and buffer it via `$deferredDeliverSm` — supported, but Part 1 is the cleaner pairing and strongly recommended.

## 11. Config (all default to current behavior)

| Env | Default | Meaning |
|---|---|---|
| `SMPP_WINDOW_SIZE` | `1` | Max in-flight = AMQP prefetch. `1` = today's serial behavior. |
| `SMPP_SUBMIT_TIMEOUT` | `15` | Per-message resp deadline (s) before requeue. |
| `SMPP_MAX_TPS` | `0` (off) | Submit token-bucket rate cap. |
| `SMPP_SEND_TRANSMITTER_ONLY` | `false` | Part 1 — pairs with windowing. |

`SMPP_WINDOW_SIZE=1` must be **behaviorally identical** to today (safety valve / instant rollback).

## 12. Code touch-points

1. `SMPPService`
   - Split `sendSMS()` into `submitAsync($msg): int seq` (build + send + record in-flight, **no wait**) and keep `storeMessageIdMapping()` as the resolve step.
   - Add `pumpResponses(): array` — non-blocking read of all available PDUs; resolve `submit_sm_resp` (use `$pendingSubmitResponses` for out-of-order), reply `enquire_link`, buffer/deliver `deliver_sm`. Returns resolved seqs.
   - Add `sweepTimeouts(int $timeoutSec): array` — return seqs past deadline.
   - Make the smsg_log price/wallet update conditional on `sentstatus <> 'ok'` (idempotency).
2. `ProcessSmsQueue`
   - Set `basic_qos(prefetch = SMPP_WINDOW_SIZE)`.
   - Replace the per-message callback with the event loop (§3): keep AMQP tags, ACK/NACK per resolved/failed seq.
   - Token-bucket + AIMD window shrink on throttle.
3. `RabbitMQService`
   - Already has `getNextMessage()` (basic_get, unacked), `ackMessage()`, `nackMessage()` — the windowing loop uses those instead of the push callback so it controls pull + per-tag ack.

## 13. Rollout & testing

1. Implement behind `SMPP_WINDOW_SIZE` (default 1). Prove `=1` == current behavior (regression).
2. **Local load test** with the existing `load_test_sms_5000.py` / `load_test_sms_50000.py`:
   - `W=1` baseline, then `W=20`, `W=50`. Measure sends/sec, `submit_sm_resp` capture rate, `deliveryreceipt1` populated %, DLR match %, wallet deduction correctness (sum of `userprice` for `ok` rows == expected).
   - Kill the worker mid-run → confirm redelivery, **no double-charge**, no loss.
   - Force `ESME_RTHROTTLED` (small `SMPP_MAX_TPS`) → confirm AIMD backoff.
3. Staging against Vonage before production. Roll `W` up gradually (1 → 10 → 30) watching capture rate + throttle errors.
4. Rollback = `SMPP_WINDOW_SIZE=1` + `config:clear`.

## 14. Risks

| Risk | Mitigation |
|---|---|
| Double-charge on redelivery | idempotency guard + conditional wallet update (§9) |
| Message loss on crash | correlated ACK — never ack before resolve (§6) |
| SMSC throttling / bind drop | token bucket + AIMD window shrink (§8) |
| Out-of-order / late responses | `$pendingSubmitResponses` buffer (already exists) |
| Regression | `W=1` == today; flag-gated; load-tested before prod |

## 15. Effort

Medium code, **verification-heavy**. Core loop + `submitAsync`/`pumpResponses` ≈ 1–2 days; load-testing + throttle tuning + crash/idempotency validation ≈ the larger share. Do **not** ship without the local load-test matrix in §13.

---

### TL;DR
Turn the send worker into a small **event loop**: keep `W` `submit_sm` in flight (prefetch=W), match `submit_sm_resp` by `sequence_number`, **ack RabbitMQ only when the response resolves** (no loss), guard idempotency (no double-charge), and pace with a token bucket. Pairs with Part 1's transmitter bind. Flag-gated on `SMPP_WINDOW_SIZE` (default 1). Only worth it for hundreds/sec on one bind.
