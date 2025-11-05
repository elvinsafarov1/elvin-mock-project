# Elvin Mock Project - Docker Setup with Observability

This project contains multiple services with full observability stack including Jaeger for distributed tracing, Tempo for log aggregation, Prometheus for metrics, and Grafana for visualization.

## Services Architecture

- **Angular Frontend** (`angular-frontend`): Serves the user interface
- **Java Service** (`java-service`): Backend service with Spring Boot
- **PHP Backend** (`php-backend`): Symfony-based backend service (PHP-FPM)
- **PHP Web Server** (`php-web`): Apache server for PHP application
- **PostgreSQL** (`db`): Database for PHP backend

## Observability Stack

- **Jaeger**: Distributed tracing visualization
- **Tempo**: Log aggregation and storage
- **Prometheus**: Metrics collection
- **Grafana**: Metrics, logs, and traces visualization

## Quick Start

### Prerequisites
- Docker Engine 20.10 or later
- Docker Compose v2.12 or later

### Running the Application

1. From the project root directory, run:

```bash
docker-compose up -d
```

2. Wait for all services to start (about 2-3 minutes)

3. Access the services:
   - **Frontend**: http://localhost
   - **Java Service API**: http://localhost:8080
   - **PHP Backend**: http://localhost:8000
   - **Jaeger UI**: http://localhost:16686
   - **Grafana**: http://localhost:3000 (login: admin/admin)
   - **Tempo**: http://localhost:3200
   - **Prometheus**: http://localhost:9090

### Stopping the Application

```bash
docker-compose down
```

To remove volumes as well (will delete all data):

```bash
docker-compose down -v
```

## OpenTelemetry Configuration

All services are configured to export telemetry data (traces, metrics, logs) to the observability stack:

- **Jaeger** endpoint: `http://jaeger:4317` (gRPC) and `http://jaeger:4318` (HTTP)
- **Tempo** endpoint: `http://tempo:4317` (gRPC) and `http://tempo:4318` (HTTP)

## Services Configuration

### Angular Frontend
- Built with Node.js 18 and Angular CLI
- Served through nginx
- Configured with OpenTelemetry for web tracing

### Java Service
- Spring Boot application with Java 17
- Includes OpenTelemetry auto-instrumentation
- Exposes health check at `/actuator/health`
- Exposes Prometheus metrics at `/actuator/prometheus`

### PHP Backend
- Symfony application with PHP 8.2
- Runs as PHP-FPM service
- Includes OpenTelemetry instrumentation
- Connects to PostgreSQL database

### PHP Web Server
- Apache server that serves the Symfony application
- Communicates with PHP-FPM backend

## Observability Tools

### Jaeger
- Provides distributed tracing visualization
- Exposes OTLP endpoints for trace collection
- UI available at http://localhost:16686

### Tempo
- Log aggregation and storage
- Compatible with OpenTelemetry protocol
- API available at http://localhost:3200

### Prometheus
- Metrics collection from services
- Scrapes metrics from configured endpoints
- Available at http://localhost:9090

### Grafana
- Visualization dashboard
- Pre-configured with Jaeger, Tempo, and Prometheus datasources
- Login credentials: admin/admin
- Add custom dashboards by placing JSON files in `grafana-dashboards/` folder

## Troubleshooting

### Common Issues

1. **Services fail to start**: Check if all Docker images are pulled successfully
2. **Jaeger UI not accessible**: Wait longer for initialization, check jaeger logs
3. **Grafana not showing data**: Ensure services are generating traffic, check connections
4. **PHP service not working**: Make sure both php-backend and php-web containers are running
5. **Prometheus not scraping metrics**: Check if Java service is exposing metrics at the correct path

### Useful Commands

- Check all logs: `docker-compose logs -f`
- Check specific service: `docker-compose logs -f <service-name>`
- Check resource usage: `docker stats`

## Development

For development, you can mount volumes to enable hot reload:

```bash
# For PHP backend development, modify the docker-compose.yml volume sections
# to map your local source code to the container
```

## Environment Variables

- `OTEL_SERVICE_NAME`: Service name for OpenTelemetry
- `OTEL_EXPORTER_OTLP_ENDPOINT`: Endpoint for OpenTelemetry data export
- `DATABASE_URL`: Connection string for PostgreSQL (PHP backend)