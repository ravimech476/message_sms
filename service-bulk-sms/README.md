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
