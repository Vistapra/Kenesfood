FROM php:7.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    nginx \
    default-mysql-client \
    netcat \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mysqli \
    zip \
    gd \
    intl \
    opcache \
    bcmath

# Install Composer
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

# Configure nginx and create required directories
RUN mkdir -p /etc/nginx/conf.d && \
    mkdir -p /var/cache/nginx && \
    mkdir -p /var/log/nginx && \
    mkdir -p /var/run

COPY ./docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY ./docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Set working directory
WORKDIR /var/www/html

# Create non-root user with specific UID/GID
RUN groupadd -g 1000 appgroup && \
    useradd -u 1000 -g appgroup -m -s /bin/bash appuser && \
    usermod -a -G www-data appuser

# Create necessary directories with correct permissions
RUN mkdir -p /var/www/html/application/cache \
    /var/www/html/application/logs \
    /var/www/html/application/sessions \
    /var/www/html/vendor \
    && chown -R appuser:www-data /var/www/html \
    && chmod -R 775 /var/www/html \
    && chmod -R 777 /var/www/html/application/cache \
    && chmod -R 777 /var/www/html/application/logs \
    && chmod -R 777 /var/www/html/application/sessions

# Switch to non-root user
USER appuser

# Copy composer files first
COPY --chown=appuser:www-data composer.json composer.lock* ./

# Install dependencies
RUN composer install --no-scripts --no-autoloader --no-dev

# Copy application files
COPY --chown=appuser:www-data . .

# Generate autoloader and clear cache
RUN composer dump-autoload --optimize && \
    rm -rf /var/www/html/application/cache/*

# Switch back to root for final configurations
USER root

# Set proper permissions for nginx directories
RUN chown -R www-data:www-data /var/run && \
    chown -R www-data:www-data /var/cache/nginx && \
    chown -R www-data:www-data /var/log/nginx && \
    chown -R www-data:www-data /var/lib/nginx

RUN mkdir -p /var/www/html/application/sessions \
    && chown -R www-data:www-data /var/www/html/application/sessions \
    && chmod -R 1733 /var/www/html/application/sessions

# Copy and prepare start script
COPY --chown=root:root ./docker/start.sh /start.sh
RUN chmod +x /start.sh

# Expose port 80
EXPOSE 80

# Start services
CMD ["/start.sh"]