FROM composer:2 AS stage_composer

FROM php:8.2-cli

COPY --from=stage_composer /usr/bin/composer /usr/bin/composer

RUN pecl install xdebug && docker-php-ext-enable xdebug \
    && echo "xdebug.mode=debug" >> $PHP_INI_DIR/conf.d/xdebug.ini \
    && echo "xdebug.discover_client_host=true" >> $PHP_INI_DIR/conf.d/xdebug.ini \
    && echo "xdebug.start_with_request=trigger" >> $PHP_INI_DIR/conf.d/xdebug.ini
