# FootFall SMS Service

## Initial setup

1. Clone this repo into your code folder, like ` ~/code/service-sms`.
2. Duplicate the `.env.example` file, rename it to `.env` and fill out any missing details. Required: database variables, mail settings and `DEFUSE_KEY` for sensitive data encryption.
3. Start Docker from the command line by `cd ~/code/service-sms && docker-compose up -d`. This should initialise five new containers in the `footfall` network:
    - PHP (`silicon-sms-php`)
    - Nginx (`silicon-sms-nginx`)
    - MySQL (`silicon-sms-mysql`)
    - Service worker (`silicon-sms-worker`)
    - Mailhog (`silicon-sms-mailhog`)
4. Install Composer dependencies by running `docker exec -it silicon-sms-php composer install`
5. Run migrations by `docker exec -it silicon-sms-php php artisan migrate`.
6. Don't forget to create and populate the database for the `crm` connection too. Ask a dump of it from someone else or back it up from the live database. The required tables are: `nhs_gp_prac_map` and `sp_domain_ods_map`.
7. If you need to compile any assets or components run `npm install`, `npm run dev` or `npm run watch` on your local machine.

### Testing

You can run existing unit and feature tests by

`docker exec -it silicon-dashboard-php ./vendor/bin/phpunit`

If you need some test data to be seeded in the database just use:

`docker exec -it silicon-sms-php php artisan migrate:fresh --seed`

## Documentation

https://siliconpractice.atlassian.net/wiki/spaces/DEVLPR/pages/1374683143/SMS+Service

## Existing providers

- Vonage API (vonage)
- BT Smart Messaging (bt)

## Dependencies

| Dependency                                        | Purpose                                                        |
| ------------------------------------------------- | -------------------------------------------------------------- |
| https://vonage.com      | Vonage SMS gateway  

---

## SMPP SMS Pipeline (Aug 2026)

_Implemented by **Anand Karthik**._

Sending was migrated from the Vonage **REST API** to a direct **SMPP + RabbitMQ**
pipeline (ported from the `sms_expert` project). The full file-by-file change list and
installed package versions are in **[`SMPP_CHANGES.yaml`](SMPP_CHANGES.yaml)**.

### What was newly implemented

1. **SMPP sending** — raw-socket SMPP 3.4 client (`app/Services/Smpp/SmppService.php`).
2. **RabbitMQ queue** — API publishes to `sms.outbound`; binder workers consume and send.
3. **Delivery tracking** — on the existing `message_updates` table (new columns
   `supplier_message_id`, `delivered_at`, `cost_per_sms`); the old `smsg_log` tables were dropped.
4. **Multi-part / concatenated SMS** — long messages auto-split with UDH.
5. **Vonage per-SMS cost** — captured from the SMPP response (TLV 0x1422).
6. **Per-component logging** — `storage/logs/{date}/{smpp,rabbitmq,api}/…` with 14-day retention (`logs:cleanup`).
7. **App-wide crash-email alerts** — throttled, on any unhandled exception.
8. **15-bind worker fleet** — 5 senders + 10 DLR banks, supervisor-managed.

### New packages

`php-amqplib/php-amqplib` (RabbitMQ), `php-smpp/php-smpp` (GSM encoder),
`predis/predis` (Redis), `ext-sockets`; PHP upgraded to `^7.4 || ^8.0`.

### Extra containers (added to the five above)

- RabbitMQ (`silicon-sms-rabbitmq`) — message broker, dashboard http://localhost:15673
- SMPP workers (`silicon-sms-smpp-workers`) — the 15 SMPP binders (supervisor)

### Running the workers

`docker-compose up -d` starts everything, including RabbitMQ and the binders.
After changing any worker/service code, reload it with:

```bash
docker restart silicon-sms-worker silicon-sms-smpp-workers
```

Binder status:

```bash
docker exec silicon-sms-smpp-workers sh -c 'supervisorctl -c /app/docker/supervisor/supervisord.conf status'
```

See `SMPP_CHANGES.yaml` for the complete list of changed files, config keys and runtime commands.
