#!/usr/bin/env bash
set -e

cd lti-local-lab

if command -v docker-compose &> /dev/null; then
  DC="docker-compose"
else
  DC="docker compose"
fi

$DC up -d

echo "⏳ Waiting for services to become healthy..."

# Wait for MySQL to be healthy
echo "➡ Waiting for MySQL"
until docker-compose ps | grep mysql | grep -q healthy; do
  sleep 3
done
echo "✅ MySQL is healthy"

# Wait for Moodle container to be running
echo "➡ Waiting for Moodle"
until docker-compose ps | grep moodle | grep -q "Up"; do
  sleep 3
done
echo "✅ Moodle container is up"

# Wait for Pressbooks container to be running
echo "➡ Waiting for Pressbooks"
until docker-compose ps | grep pressbooks | grep -q "Up"; do
  sleep 3
done
echo "✅ Pressbooks container is up"

echo "🚀 Local LTI lab is ready"

