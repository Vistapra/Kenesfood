#!/bin/bash

# Create cache directories if they don't exist
mkdir -p /var/www/html/application/cache
mkdir -p /var/www/html/application/logs
mkdir -p /var/www/html/application/sessions

# Set proper permissions
chmod -R 777 /var/www/html/application/cache
chmod -R 777 /var/www/html/application/logs
chmod -R 777 /var/www/html/application/sessions

# Wait for database
until nc -z -v -w30 db 3306
do
  echo "Waiting for database connection..."
  sleep 5
done
echo "Database is ready!"

# Clear PHP cache
rm -rf /var/www/html/application/cache/*

# Start nginx
nginx

# Start PHP-FPM
php-fpm