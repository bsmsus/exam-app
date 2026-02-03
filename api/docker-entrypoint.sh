#!/bin/bash
set -e

# If a command is passed, execute it directly
if [ $# -gt 0 ]; then
    exec "$@"
fi

# Otherwise, run the full setup
echo "Waiting for database to be ready..."
until php -r "new PDO('pgsql:host=db;port=5432;dbname=postgres', 'exam', 'exam');" 2>/dev/null; do
    sleep 1
done
echo "Database is ready!"

echo "Running composer install..."
composer install --no-interaction

echo "Creating dev database..."
php bin/console doctrine:database:create --if-not-exists --no-interaction

echo "Creating test database..."
APP_ENV=test php bin/console doctrine:database:create --if-not-exists --no-interaction

echo "Running migrations (dev)..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "Running migrations (test)..."
APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction

echo "Clearing cache..."
php bin/console cache:clear
rm -rf var/cache/*

echo "Setup complete! Starting server..."
exec php -S 0.0.0.0:8000 -t public
