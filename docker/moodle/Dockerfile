FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git unzip curl default-mysql-client \
    libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    libxml2-dev libonig-dev libicu-dev libxslt1-dev \
    libldap2-dev libgd-dev \
    ghostscript \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required by Moodle
RUN docker-php-ext-install \
    mysqli \
    pdo \
    pdo_mysql \
    zip \
    intl \
    xsl \
    soap \
    exif \
    opcache

# Configure GD with JPEG and FreeType support
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd

# Configure and install LDAP
RUN docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install ldap

# PHP configuration for Moodle
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/moodle.ini \
    && echo "upload_max_filesize=256M" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "post_max_size=256M" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "max_execution_time=600" >> /usr/local/etc/php/conf.d/moodle.ini \
    && echo "max_input_vars=5000" >> /usr/local/etc/php/conf.d/moodle.ini

# Enable Apache modules
RUN a2enmod rewrite headers ssl

# Set working directory
WORKDIR /var/www/html

# Download Moodle
ARG MOODLE_BRANCH=MOODLE_404_STABLE
RUN echo "Downloading Moodle branch: ${MOODLE_BRANCH}" \
    && curl -L https://github.com/moodle/moodle/archive/refs/heads/${MOODLE_BRANCH}.tar.gz -o moodle.tar.gz \
    && tar -xzf moodle.tar.gz --strip-components=1 \
    && rm moodle.tar.gz \
    && chown -R www-data:www-data /var/www/html

# Create moodledata directory
RUN mkdir -p /var/moodledata && chown -R www-data:www-data /var/moodledata

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]

# Expose port 80
EXPOSE 80

CMD ["apache2-foreground"]
