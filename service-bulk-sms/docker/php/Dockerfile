# Alpine 3.15 ships PHP 8.0 (php8 packages). PHP 8.0 = the sweet spot:
# Laravel 6 supports up to 8.0, and php-smpp/php-amqplib both run on it.
FROM alpine:3.15

# Set the working directory
WORKDIR /app

RUN apk add --update libcurl curl libxml2-dev libsodium-dev

# Install PHP 8 and extensions.
#  - sockets : required by php-smpp (SMPP binary protocol over TCP)
#  - pcntl + posix : required by the long-running SMPP DLR receivers (signal handling)
#  - bcmath : required by php-amqplib (large-number handling)
#  NOTE: json is built into PHP 8 core (no separate php8-json package).
RUN apk add php8 php8-fpm php8-sockets php8-pcntl php8-posix \
    php8-fileinfo php8-intl php8-bz2 php8-apcu php8-openssl php8-pdo_mysql \
    php8-mbstring php8-tokenizer php8-xml php8-ctype php8-pdo_sqlite php8-sqlite3 \
    php8-iconv php8-session php8-dom php8-curl php8-bcmath \
    php8-xmlwriter zlib php8-phar php8-zip php8-mysqli php8-simplexml php8-sodium \
    php8-pecl-imagick imagemagick openssh-client

# Alpine installs the PHP 8 CLI as `php8`. Symlink `php` so composer (env php shebang),
# `php artisan`, and every `php` invocation work.
RUN ln -sf /usr/bin/php8 /usr/bin/php

# Configure php.ini
RUN sed -i "s|;*max_execution_time\s*=\s*30|max_execution_time = 60|g" /etc/php8/php.ini

# Configure php-fpm.conf
RUN sed -i "s|;*daemonize\s*=\s*yes|daemonize = no|g" /etc/php8/php-fpm.conf && \
    sed -i "s|;*error_log\s*=\s*log/php8/error.log|error_log = /proc/self/fd/2|g" /etc/php8/php-fpm.conf

# Configure www.conf
RUN sed -i "s|;*listen\s*=\s*127.0.0.1:9000|listen = 0.0.0.0:9000|g" /etc/php8/php-fpm.d/www.conf && \
    sed -i "s|;*clear_env\s*=\s*no|clear_env = no|g" /etc/php8/php-fpm.d/www.conf && \
    echo "catch_workers_output = yes" >> /etc/php8/php-fpm.d/www.conf


# Clean apk cache
RUN rm -rf /var/cache/apk/*

# Copy composer files
COPY composer.* /app/

# Install composer
RUN curl -o /tmp/composer-setup.php https://getcomposer.org/installer && \
    curl -o /tmp/composer-setup.sig https://composer.github.io/installer.sig && \
    php -r "if (hash('SHA384', file_get_contents('/tmp/composer-setup.php')) !== trim(file_get_contents('/tmp/composer-setup.sig'))) { unlink('/tmp/composer-setup.php'); echo 'Invalid installer' . PHP_EOL; exit(1); }" && \
    php /tmp/composer-setup.php --quiet --2.2 && \
    rm /tmp/composer-setup.php && \
    mv composer.phar /usr/local/bin/composer

# Copy the application
COPY . /app

# NOTE: composer dependencies are installed at RUNTIME inside the container
# (the compose volume `.:/app` shadows any vendor baked in here). This also lets us
# regenerate the lock for PHP 8.0 without failing the image build.

# Expose the PHP-fpm port
EXPOSE 9000

# Run php fpm in the foreground
CMD [ "php-fpm8", "-F" ]
