# План безопасного деплоя ManyChat интеграции

## Текущее состояние
- ✅ Система работает в продакшене
- ✅ Создан тег `v1.1.2-stable` для отката
- ✅ Новая функциональность в ветке `feature/manychat-integration`

## Новая функциональность
1. **API для загрузки подписанных договоров**
   - `POST /api/contract/upload-signed` - загрузка файла
   - `GET /api/contract/download-signed/{filename}` - скачивание файла
   - `POST /api/contract/send-to-manychat` - отправка в ManyChat

2. **Обновленный фронтенд**
   - Показывает информацию о ManyChat после загрузки
   - Отображает поле и значение для ManyChat

## План безопасного деплоя

### Этап 1: Подготовка (выполнено)
- [x] Создан тег `v1.1.2-stable`
- [x] Создана ветка `feature/manychat-integration`
- [x] Изменения закоммичены и отправлены в GitLab

### Этап 2: Тестирование на сервере
```bash
# 1. Подключиться к серверу
ssh root@dogovor-service-main-srtt1t.laravel.cloud

# 2. Создать бэкап текущей версии
cd /var/www/html
cp -r . ../backup-$(date +%Y%m%d-%H%M%S)

# 3. Переключиться на ветку с ManyChat
git fetch origin
git checkout feature/manychat-integration

# 4. Обновить зависимости
composer install --no-dev --optimize-autoloader

# 5. Очистить кэш
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 6. Проверить маршруты
php artisan route:list | grep -E "(upload-signed|download-signed|send-to-manychat)"
```

### Этап 3: Проверка работоспособности
```bash
# 1. Проверить API endpoints
curl -X POST https://dogovor-service-main-srtt1t.laravel.cloud/api/contract/upload-signed \
  -F "signed_contract=@test.pdf" \
  -F "contract_number=TEST-001" \
  -H "Accept: application/json"

# 2. Проверить веб-интерфейс
# Открыть https://dogovor-service-main-srtt1t.laravel.cloud/contract
```

### Этап 4: Мерж в main (если все работает)
```bash
# На сервере
git checkout main
git merge feature/manychat-integration
git push origin main

# Создать новый тег
git tag v1.2.0-manychat
git push origin v1.2.0-manychat
```

### Этап 5: Откат (если что-то пошло не так)
```bash
# Быстрый откат к стабильной версии
git checkout v1.1.2-stable
git checkout -b hotfix-rollback
git push origin hotfix-rollback

# Или полный откат
git reset --hard v1.1.2-stable
php artisan config:clear
php artisan route:clear
```

## Проверочный список
- [ ] Все API endpoints отвечают корректно
- [ ] Фронтенд работает без ошибок
- [ ] Загрузка файлов работает
- [ ] Скачивание файлов работает
- [ ] ManyChat интеграция работает
- [ ] Старая функциональность не сломана
- [ ] Нет ошибок в логах Laravel

## Контакты для экстренного отката
- Тег стабильной версии: `v1.1.2-stable`
- Ветка отката: `hotfix-rollback`
- Бэкап директории: `/var/www/html/backup-YYYYMMDD-HHMMSS`

## Новые API endpoints
1. `POST /api/contract/upload-signed` - загрузка подписанного договора
2. `GET /api/contract/download-signed/{filename}` - скачивание подписанного договора
3. `POST /api/contract/send-to-manychat` - отправка ссылки в ManyChat

Все endpoints работают без CSRF токенов для интеграции с внешними системами.

