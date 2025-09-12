#!/bin/bash
# Запуск Queue Worker для Laravel Cloud

cd /var/www/html
php artisan queue:work --daemon --tries=3 --timeout=300
