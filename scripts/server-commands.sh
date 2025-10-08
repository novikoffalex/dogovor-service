#!/bin/bash

# Server Management Script for Cursor IDE
# Usage: ./scripts/server-commands.sh [command]

SERVER_URL="https://dogovor-service-main-srtt1t.laravel.cloud"
API_BASE="$SERVER_URL/api/server"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to make API calls
api_call() {
    local method=$1
    local endpoint=$2
    local data=$3
    
    if [ -n "$data" ]; then
        curl -s -X $method \
            -H "Content-Type: application/json" \
            -d "$data" \
            "$API_BASE$endpoint"
    else
        curl -s -X $method "$API_BASE$endpoint"
    fi
}

# Function to display server status
status() {
    echo -e "${BLUE}🔍 Checking server status...${NC}"
    response=$(api_call "GET" "/status")
    echo "$response" | jq '.' 2>/dev/null || echo "$response"
}

# Function to setup contract counter
setup_counter() {
    echo -e "${YELLOW}⚙️  Setting up contract counter...${NC}"
    response=$(api_call "POST" "/setup-counter")
    echo "$response" | jq '.' 2>/dev/null || echo "$response"
}

# Function to execute Artisan commands
execute() {
    local command=$1
    echo -e "${YELLOW}🚀 Executing: $command${NC}"
    response=$(api_call "POST" "/execute" "{\"command\":\"$command\"}")
    echo "$response" | jq '.' 2>/dev/null || echo "$response"
}

# Function to clear all caches
clear_cache() {
    echo -e "${YELLOW}🧹 Clearing all caches...${NC}"
    execute "cache:clear"
    execute "config:clear"
    execute "route:clear"
    execute "view:clear"
}

# Function to run migrations
migrate() {
    echo -e "${YELLOW}📦 Running migrations...${NC}"
    execute "migrate"
}

# Function to check Zamzar jobs
check_zamzar() {
    echo -e "${BLUE}📋 Checking Zamzar jobs...${NC}"
    status | jq '.status.zamzar_jobs' 2>/dev/null || echo "Could not parse Zamzar status"
}

# Function to show help
help() {
    echo -e "${GREEN}🛠️  Server Management Commands:${NC}"
    echo ""
    echo "Usage: $0 [command]"
    echo ""
    echo "Commands:"
    echo "  status          - Show server status"
    echo "  setup-counter   - Setup contract counter"
    echo "  clear-cache     - Clear all caches"
    echo "  migrate         - Run migrations"
    echo "  check-zamzar    - Check Zamzar jobs"
    echo "  execute [cmd]   - Execute custom Artisan command"
    echo "  help            - Show this help"
    echo ""
    echo "Examples:"
    echo "  $0 status"
    echo "  $0 setup-counter"
    echo "  $0 execute \"cache:clear\""
}

# Main script logic
case "$1" in
    "status")
        status
        ;;
    "setup-counter")
        setup_counter
        ;;
    "clear-cache")
        clear_cache
        ;;
    "migrate")
        migrate
        ;;
    "check-zamzar")
        check_zamzar
        ;;
    "execute")
        if [ -z "$2" ]; then
            echo -e "${RED}❌ Error: Command required${NC}"
            echo "Usage: $0 execute \"command\""
            exit 1
        fi
        execute "$2"
        ;;
    "help"|"--help"|"-h"|"")
        help
        ;;
    *)
        echo -e "${RED}❌ Unknown command: $1${NC}"
        help
        exit 1
        ;;
esac

