#!/bin/bash
echo "Waiting for database to be ready..."
sleep 5

echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "Database initialized!"

