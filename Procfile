web: heroku-php-apache2 public/
release: php artisan migrate --force && php artisan contract:setup-counter && php artisan config:cache && php artisan route:cache
