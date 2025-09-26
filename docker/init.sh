#!/bin/bash
set -e

echo "⚡ Importing unilis.sql into MySQL..."

# Wait for MySQL to be ready
until mysql -h localhost -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SELECT 1;" &> /dev/null; do
  echo "⏳ Waiting for database connection..."
  sleep 3
done

# Import the SQL file every time container restarts
mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" < /docker-entrypoint-initdb.d/unilis.sql

echo "✅ Database import completed."
