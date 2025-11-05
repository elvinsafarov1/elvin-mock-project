#!/bin/bash

# Send batch requests to verify all services generate traces

echo "🔍 Verifying trace generation across services..."

echo ""
echo "Sending 10 additional requests to Java service..."
for i in {1..10}; do
  curl -s -o /dev/null -w "." http://localhost:8080/
  sleep 0.2
done
echo " Done!"

echo ""
echo "Sending 10 additional requests to Frontend..."
for i in {1..10}; do
  curl -s -o /dev/null -w "." http://localhost/
  sleep 0.2
done
echo " Done!"

echo ""
echo "Sending 10 additional requests to PHP Web..."
for i in {1..10}; do
  curl -s -o /dev/null -w "." http://localhost:8000/
  sleep 0.2
done
echo " Done!"

echo ""
echo "📈 Total of 140+ requests have been sent to generate traces"
echo "   Check Jaeger UI: http://localhost:16686"
echo "   Look for distributed traces across: java-service, frontend, php-backend"
echo ""
echo "💡 Database traces will appear when database queries are executed"
echo "   The database tracing is implemented in the PHP backend"