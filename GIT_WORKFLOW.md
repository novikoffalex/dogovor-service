# Git Workflow для проекта Dogovor Service

## Структура веток

### `main` (production)
- Стабильная рабочая версия
- Только проверенный и протестированный код
- Готов к деплою в production

### `develop` (development) 
- Ветка для разработки и тестирования
- Интеграция новых фич перед merge в main
- Тестирование изменений

### `feature/*` (feature branches)
- Ветки для отдельных фич
- Создаются от develop
- После завершения merge в develop

## Workflow

### Для новых фич:
1. `git checkout develop`
2. `git pull origin develop`
3. `git checkout -b feature/название-фичи`
4. Разработка...
5. `git add . && git commit -m "Описание изменений"`
6. `git push origin feature/название-фичи`
7. Создать Merge Request в GitLab: develop ← feature/название-фичи

### Для релиза в production:
1. `git checkout develop`
2. `git pull origin develop`
3. Тестирование...
4. `git checkout main`
5. `git merge develop`
6. `git tag -a v1.1.0 -m "Описание релиза"`
7. `git push origin main --tags`

### Быстрый откат к рабочей версии:
```bash
# К последней стабильной версии
git checkout v1.0.0

# К конкретному коммиту
git checkout <commit-hash>

# К предыдущему коммиту
git checkout HEAD~1
```

## Теги (Tags)

- `v1.0.0` - Стабильная версия с ManyChat JSON интеграцией
- Создавать теги для каждой стабильной версии
- Теги помогают быстро откатиться к рабочему состоянию

## Полезные команды

```bash
# Посмотреть все ветки
git branch -a

# Посмотреть все теги
git tag -l

# Посмотреть историю коммитов
git log --oneline -10

# Сравнить ветки
git diff main..develop

# Откатиться к тегу
git checkout v1.0.0

# Создать ветку от тега
git checkout -b hotfix v1.0.0
```
