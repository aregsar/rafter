FROM gcr.io/rafter/php-7.4-grpc:latest

RUN curl -sL https://deb.nodesource.com/setup_13.x | bash -

# Install production dependencies
RUN apt-get update && apt-get install -y \
    libjpeg-dev \
    nodejs \
    mariadb-client

# Enable PECL and PEAR extensions
RUN docker-php-ext-enable \
    grpc

# Configure php extensions
RUN docker-php-ext-configure gd --with-jpeg

# Install composer
ENV COMPOSER_ALLOW_SUPERUSER 1
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Copy over package manifests so this step can be cached
COPY composer.json composer.lock package*.json /var/www/

WORKDIR /var/www

# Install initial composer dependencies
RUN composer install --prefer-dist --no-scripts --no-dev --no-autoloader

# Install Node dependencies
RUN npm ci

# Copy the rest of the project to the container image.
COPY . /var/www/

# Run composer install again to trigger Laravel's scripts
RUN composer install --no-dev --classmap-authoritative && php artisan event:cache

# Compile node things
RUN npm run prod && rm -rf node_modules

# Copy the /public folder contents to the /html subdirectory so Apache serves them
COPY ./public /var/www/html

# Use the PORT environment variable in Apache configuration files.
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

RUN chmod 755 docker-entrypoint.sh

ENTRYPOINT ["/var/www/docker-entrypoint.sh"]
