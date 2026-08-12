# Pinned to 3.15: last Alpine release shipping php7 packages
FROM alpine:3.15

# Set the working directory
WORKDIR /app

RUN apk add --update libcurl curl libxml2-dev libsodium-dev

# Install PHP and various php dependencies
RUN apk add php7 php7-fileinfo php7-intl php7-bz2 php7-apcu php7-fpm php7-openssl php7-pdo_mysql \
    php7-mbstring php7-tokenizer php7-xml php7-ctype php7-pdo_sqlite php7-sqlite3 \
    php7-json php7-iconv php7-session php7-dom  php7-curl php7-bcmath \
    php7-xmlwriter zlib php7-phar php7-zip php7-mysqli php7-simplexml php7-sodium php7-pecl-imagick php7-imagick imagemagick openssh-client

# Configure php.ini
RUN sed -i "s|;*max_execution_time\s*=\s*30|max_execution_time = 60|g" /etc/php7/php.ini

# Configure php-fpm.conf
RUN sed -i "s|;*daemonize\s*=\s*yes|daemonize = no|g" /etc/php7/php-fpm.conf && \
    sed -i "s|;*error_log\s*=\s*log/php7/error.log|error_log = /proc/self/fd/2|g" /etc/php7/php-fpm.conf

# Configure www.conf
RUN sed -i "s|;*listen\s*=\s*127.0.0.1:9000|listen = 0.0.0.0:9000|g" /etc/php7/php-fpm.d/www.conf && \
    sed -i "s|;*clear_env\s*=\s*no|clear_env = no|g" /etc/php7/php-fpm.d/www.conf && \
    echo "catch_workers_output = yes" >> /etc/php7/php-fpm.d/www.conf


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

# Run composer install
RUN composer install --no-dev --no-scripts --no-autoloader

RUN composer dump-autoload --optimize

# Expose the PHP-fpm port
EXPOSE 9000

# Run php fpm in the foreground
CMD [ "php-fpm7", "-F" ]
