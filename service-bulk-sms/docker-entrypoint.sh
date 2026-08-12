#!/bin/sh

cd /app

echo hi

composer install

mkdir -p /run/php/

# Start PHP
exec php-fpm7.3 -F
