FROM php:7.4-alpine

RUN docker-php-ext-install bcmath pdo_mysql pcntl posix mysqli

RUN curl --silent --show-error https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

WORKDIR /book