#!/bin/bash

# Script to generate traces by sending requests to all services

set -e

echo "🚀 Generating traces for observability testing..."

echo ""
echo "📡 Testing Frontend service (20 requests)..."
for i in {1..20}; do
  curl -s -o /dev/null -w "Frontend request $i completed (HTTP %{http_code})\n" http://localhost/ 2>/dev/null || echo "Frontend request $i failed"
  sleep 0.1
done

echo ""
echo "📡 Testing Java service (20 requests)..."
for i in {1..20}; do
  curl -s -o /dev/null -w "Java request $i completed (HTTP %{http_code})\n" http://localhost:8080/ 2>/dev/null || echo "Java request $i failed"
  sleep 0.1
done

echo ""
echo "📡 Testing Java service health endpoint (20 requests)..."
for i in {1..20}; do
  curl -s -o /dev/null -w "Java health request $i completed (HTTP %{http_code})\n" http://localhost:8080/actuator/health 2>/dev/null || echo "Java health request $i failed"
  sleep 0.1
done

echo ""
echo "📡 Testing PHP Web service (20 requests)..."
for i in {1..20}; do
  curl -s -o /dev/null -w "PHP Web request $i completed (HTTP %{http_code})\n" http://localhost:8000/ 2>/dev/null || echo "PHP Web request $i failed"
  sleep 0.1
done

echo ""
echo "📊 Generated 80 total requests across all services"
echo "📈 Traces are now available in Jaeger UI: http://localhost:16686"
echo "💡 Check for services: frontend, java-service, php-backend"
echo ""
echo "📝 Note: Database traces will appear when database operations are performed by the PHP service"
echo "   The database tracing listener is implemented and ready to capture queries"