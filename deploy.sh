#!/bin/bash

# Скрипт деплоя для Laravel проекта Dogovor Service
# Использование: ./deploy.sh [server_host] [server_user] [server_path]

set -e

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Параметры сервера
SERVER_HOST=${1:-"your-server.com"}
SERVER_USER=${2:-"root"}
SERVER_PATH=${3:-"/var/www/dogovor-service"}

echo -e "${GREEN}🚀 Начинаем деплой на сервер: ${SERVER_USER}@${SERVER_HOST}:${SERVER_PATH}${NC}"

# Проверяем SSH соединение
echo -e "${YELLOW}📡 Проверяем SSH соединение...${NC}"
ssh -o ConnectTimeout=10 ${SERVER_USER}@${SERVER_HOST} "echo 'SSH соединение успешно'"

# Подключаемся к серверу и выполняем деплой
echo -e "${YELLOW}📦 Обновляем код на сервере...${NC}"
ssh ${SERVER_USER}@${SERVER_HOST} << EOF
    set -e
    
    echo "Переходим в директорию проекта..."
    cd ${SERVER_PATH}
    
    echo "Создаем бэкап текущей версии..."
    if [ -d "backup" ]; then
        rm -rf backup
    fi
    mkdir backup
    cp -r . backup/ 2>/dev/null || true
    
    echo "Получаем последнюю версию из Git..."
    git fetch origin
    git reset --hard origin/main
    git checkout v1.1.0
    
    echo "Устанавливаем зависимости..."
    composer install --no-dev --optimize-autoloader --no-interaction
    
    echo "Очищаем кеш..."
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear
    
    echo "Создаем оптимизированный кеш..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    
    echo "Проверяем права доступа..."
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R 775 storage bootstrap/cache
    
    echo "Проверяем работу команды Zamzar..."
    php artisan zamzar:check-status
    
    echo "Проверяем расписание задач..."
    php artisan schedule:list
    
    echo "Перезапускаем веб-сервер..."
    systemctl reload nginx || service nginx reload || true
    
    echo "✅ Деплой завершен успешно!"
EOF

echo -e "${GREEN}✅ Деплой завершен!${NC}"
echo -e "${YELLOW}📋 Следующие шаги:${NC}"
echo "1. Настроить cron job: * * * * * cd ${SERVER_PATH} && php artisan schedule:run"
echo "2. Обновить webhook URL в Zamzar: https://${SERVER_HOST}/api/zamzar/webhook"
echo "3. Протестировать генерацию договора на сайте"

echo -e "${GREEN}🎉 Готово!${NC}"

