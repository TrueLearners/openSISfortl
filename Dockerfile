FROM php:8.4.27-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y --no-install-recommends \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    libzip-dev libicu-dev libxml2-dev libonig-dev libcurl4-openssl-dev \
    unzip curl ca-certificates && \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j"$(nproc)" mysqli gd zip intl mbstring xml soap opcache && \
    a2enmod rewrite headers expires && \
    sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf && \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .

# Install the AWS SDK for the optional Backblaze B2 integration.
RUN composer require --no-interaction --no-progress --prefer-dist aws/aws-sdk-php:^3.300

COPY docker/apache-opensis.conf /etc/apache2/conf-available/opensis.conf
RUN a2enconf opensis &&     mkdir -p /var/www/html/assets/studentfiles /var/www/html/assets/userphotos /var/www/html/assets/schoollogo &&     chown -R www-data:www-data /var/www/html &&     chmod -R u+rwX,g+rwX /var/www/html/assets

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
  CMD curl -fsS http://127.0.0.1/healthz.php || exit 1

CMD ["apache2-foreground"]
