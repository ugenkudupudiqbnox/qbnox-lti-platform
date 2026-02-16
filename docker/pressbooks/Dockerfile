FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip curl mariadb-client \
    libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libxml2-dev libonig-dev libicu-dev libxslt1-dev \
    ghostscript imagemagick poppler-utils \
    libmagickwand-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli zip intl xsl \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && a2enmod rewrite headers ssl \
    && rm -rf /var/lib/apt/lists/*

# PHP tuning
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini \
 && echo "upload_max_filesize=256M" >> /usr/local/etc/php/conf.d/memory.ini \
 && echo "post_max_size=256M" >> /usr/local/etc/php/conf.d/memory.ini \
 && echo "max_execution_time=600" >> /usr/local/etc/php/conf.d/memory.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install WP-CLI
RUN curl -sSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    -o /usr/local/bin/wp \
    && chmod +x /usr/local/bin/wp

# Set WP-CLI package path and install dotenv command
ENV WP_CLI_PACKAGES_DIR /root/.wp-cli/packages
RUN mkdir -p $WP_CLI_PACKAGES_DIR \
    && composer --working-dir=$WP_CLI_PACKAGES_DIR require aaemnnosttv/wp-cli-dotenv-command:^2.0

# Set working directory
WORKDIR /var/www/pressbooks

# Clone Pressbooks Bedrock
RUN git clone https://github.com/pressbooks/pressbooksoss-bedrock.git . \
    && composer install --no-dev --prefer-dist --optimize-autoloader

# Apache DocumentRoot → Bedrock web/
ENV APACHE_DOCUMENT_ROOT /var/www/pressbooks/web

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf

RUN chown -R www-data:www-data /var/www/pressbooks

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
