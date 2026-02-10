FROM php:8.5-fpm-alpine
ENV REFRESHED_AT="2026–02–06"
ARG UGNAME=www-data
STOPSIGNAL SIGTERM

RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    supervisor \
    bash \
    shadow \
    util-linux \
    libxml2-dev \
    libxslt-dev

RUN docker-php-ext-install \
    pdo_mysql \
    zip \
    intl \
    bcmath \
    pcntl \
    xsl

# Install Redis extension
RUN apk add --no-cache pcre-dev $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && chmod 0640 /etc/supervisord.conf

COPY --from=composer/composer:latest /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/php.ini
COPY zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf
COPY ./supervisord.conf /etc/supervisor.d/docker.ini
COPY ./supervisor.app.ini /etc/supervisor.d/supervisor.app.ini
COPY ./entrypoint.sh /entrypoint.sh

RUN chmod 0550 /entrypoint.sh

WORKDIR /app
