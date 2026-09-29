# Local development image (see compose.yaml): PHP 8.4 with the extensions CI
# uses. The working copy is bind-mounted, so the image holds no app code.
FROM php:8.4-cli

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath gd intl pcntl zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
