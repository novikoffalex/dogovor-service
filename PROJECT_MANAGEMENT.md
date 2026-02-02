# Управление проектом

## Где что лежит
- Форма: `/contract`
- Шаблон договора: `resources/contracts/contract.docx`
- Контроллер: `app/Http/Controllers/ContractController.php`
- Логи: Laravel Cloud панель (канал `laravel-cloud-socket`)

## Как обновлять шаблон договора
1. Открой `resources/contracts/contract.docx` и внеси правки.
2. Сохрани файл с тем же именем.
3. Закоммить и запушь в `main`.
4. Запусти деплой (см. ниже).
5. Сгенерируй **новый** договор — старые не перегенерируются.

## Деплой
### Быстрый деплой с локалки (deploy hook)
```bash
./deploy.sh
```

### Ручной деплой в панели
Laravel Cloud → Deploy → выбрать `main`.

## Рабочий домен
Используем Laravel Cloud домен:
```
https://dogovor-service-main-srtt1t.laravel.cloud/contract
```

## Переменные окружения (критично)
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://dogovor-service-main-srtt1t.laravel.cloud`
- `ZAMZAR_API_KEY=...`
- `LOG_CHANNEL=laravel-cloud-socket` (логи в панели)

При изменении `.env`:
```bash
php artisan config:clear
php artisan config:cache
```

## Проверка PDF (Zamzar)
- Статус: `/api/contract/check-pdf-status/{filename}.docx`
- Скачивание: `/api/contract/download-pdf/{filename}.pdf`

## Типовые проблемы
### PDF “готовится” бесконечно
- Проверь `ZAMZAR_API_KEY`.
- Проверь статус через `check-pdf-status`.

### Старый шаблон
- Убедись, что `contract.docx` обновлён в `main`.
- Деплой после пуша.
- Формируй **новый** договор.

## Полезные команды на сервере
```bash
cd /var/www/html
php artisan config:clear
php artisan config:cache
php artisan route:list | grep contract
```

## Локальная проверка статуса (SSL)
Скрипт `scripts/server-manager.php` сам пытается найти системный CA сертификат для HTTPS:
- `/etc/ssl/cert.pem`
- `/usr/local/etc/openssl@3/cert.pem`
- `/opt/homebrew/etc/openssl@3/cert.pem`
