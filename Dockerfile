FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

CMD ["php", "-S", "0.0.0.0:8001", "-t", "public"]
