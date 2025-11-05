#!/bin/bash

# Startup script for Elvin-Mock Project with Enhanced Observability Stack
# This script starts the complete stack with:
# - Jaeger configured for persistent storage using Badger DB and 10% sampling
# - Tempo configured with persistent storage
# - All services properly integrated with OpenTelemetry
# - Fixed permission issues for storage containers (running as root where needed)

set -e

echo "🚀 Starting Elvin-Mock Project with Enhanced Observability Stack..."
echo "📁 Project directory: $(pwd)"

# Check if docker-compose is available
if ! command -v docker-compose &> /dev/null; then
    echo "❌ docker-compose is not installed or not in PATH"
    exit 1
fi

# Check if required config files exist
if [ ! -f "./sampling-strategies.json" ]; then
    echo "❌ sampling-strategies.json not found"
    exit 1
fi

if [ ! -f "./docker-compose.yml" ]; then
    echo "❌ docker-compose.yml not found"
    exit 1
fi

echo "✅ All required files found"

# Start the services
echo "🐳 Starting docker-compose services..."
docker-compose up -d

# Wait for services to start
echo "⏱️  Waiting for services to start..."
sleep 10

# Check the status of services
echo "✅ Checking service status..."
docker-compose ps

# Print connection information
echo ""
echo "📊 Observability Stack Information:"
echo "   Jaeger UI: http://localhost:16686"
echo "   Grafana: http://localhost:3000 (admin/admin)"
echo "   Tempo: http://localhost:3200"
echo "   Prometheus: http://localhost:9090"
echo "   Java Service: http://localhost:8080"
echo "   PHP Backend: http://localhost:8000"
echo "   Frontend: http://localhost:80"
echo ""
echo "📈 Sampling Configuration: 10% probabilistic sampling"
echo "💾 Storage: Persistent Badger DB for Jaeger and persistent storage for Tempo"
echo "🔍 Database Tracing: SQL queries are now traced and visible in Jaeger UI"
echo ""
echo "🧪 To generate sample traces and verify full trace capture:"
echo "   # Generate traffic to test tracing across all services"
echo "   curl -s http://localhost:8080/"
echo "   # To test PHP backend with database (once API routing is fixed):"
echo "   # curl -X POST http://localhost:8000/api/users -H \"Content-Type: application/json\" -d '{\"name\":\"Test User\",\"email\":\"test@example.com\"}'"
echo "   curl -s http://localhost/"
echo ""
echo "   # Check Jaeger UI at http://localhost:16686 for traces"
echo "   # Look for services: java-service, php-backend, frontend and db.query spans"
echo "   # Database queries will appear as 'db.query' child spans in your requests"
echo ""
echo "✅ Elvin-Mock Project is now running with enhanced observability!"
echo "   Persistent storage, 10% sampling, and database tracing configured for Jaeger"