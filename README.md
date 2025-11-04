# Elvin Mock Project

Веб-приложение с фронтендом на Angular, бэкендом на PHP Symfony, внешним сервисом на Java и базой данных PostgreSQL. Все компоненты интегрированы с OpenTelemetry для трейсинга в Jaeger.

## Архитектура

- **Frontend**: Angular 17
- **Backend**: PHP Symfony 6.3
- **Внешний сервис**: Java Spring Boot 3.1
- **База данных**: PostgreSQL 15
- **Трейсинг**: OpenTelemetry + Jaeger

## Требования

- Docker и Docker Compose
- Maven (для локальной сборки Java сервиса, опционально)
- Node.js и npm (для локальной разработки Angular, опционально)

## Быстрый старт

1. Клонируйте репозиторий и перейдите в директорию проекта:
```bash
cd /home/tamerlan/Elvin_mock_project
```

2. Запустите все сервисы через Docker Compose:
```bash
docker-compose up --build
```

3. Приложение будет доступно по следующим адресам:
   - **Angular Frontend**: http://localhost:4200
   - **PHP Backend API**: http://localhost:8000
   - **Java Service**: http://localhost:8080
   - **Jaeger UI**: http://localhost:16686

## Структура проекта

```
.
├── angular-frontend/      # Angular фронтенд
├── php-backend/          # PHP Symfony бэкенд
├── java-service/         # Java внешний сервис
├── docker-compose.yml    # Оркестрация всех сервисов
└── README.md
```

## Настройка OpenTelemetry

Все сервисы настроены для отправки трейсов в Jaeger через OpenTelemetry:

- **Angular**: Автоматическая инструментация HTTP запросов
- **PHP**: Инструментация через OpenTelemetry SDK
- **Java**: Инструментация через OpenTelemetry SDK

Трейсы можно просматривать в Jaeger UI по адресу http://localhost:16686

## API Endpoints

### PHP Backend

- `GET /api/users` - Получить список пользователей
- `GET /api/users/{id}` - Получить пользователя по ID (с вызовом Java сервиса)
- `POST /api/users` - Создать нового пользователя

### Java Service

- `GET /api/external/user/{id}` - Получить внешние данные пользователя
- `GET /api/external/health` - Проверка здоровья сервиса

## Разработка

### PHP Backend

Для разработки PHP бэкенда локально:

```bash
cd php-backend
composer install
php bin/console doctrine:migrations:migrate
php -S localhost:8000 -t public
```

### Java Service

Для разработки Java сервиса локально:

```bash
cd java-service
mvn clean install
mvn spring-boot:run
```

### Angular Frontend

Для разработки Angular фронтенда локально:

```bash
cd angular-frontend
npm install
ng serve
```

## База данных

PostgreSQL запускается автоматически через Docker Compose. Миграции выполняются при первом запуске.

Для ручного выполнения миграций:

```bash
docker-compose exec php-backend php bin/console doctrine:migrations:migrate
```

## Troubleshooting

### Проблемы с подключением к базе данных

Убедитесь, что PostgreSQL запущен и готов к подключениям:

```bash
docker-compose ps
```

### Проблемы с трейсингом

Проверьте, что Jaeger запущен и доступен:

```bash
docker-compose logs jaeger
```

Откройте Jaeger UI: http://localhost:16686

### Проблемы с CORS

CORS настроен в `php-backend/config/packages/cors.yaml`. Убедитесь, что фронтенд использует правильный URL API.

## Лицензия

Proprietary

