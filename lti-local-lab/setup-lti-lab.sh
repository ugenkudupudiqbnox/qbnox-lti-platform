#!/usr/bin/env bash
set -e

### CONFIG ###
if [[ -d $HOME/lti-local-lab ]] ; then
LAB_DIR="$HOME/lti-local-lab"
else
LAB_DIR="$PWD"
fi
MOODLE_HOST="moodle.local"
PRESSBOOKS_HOST="pressbooks.local"
PLUGIN_REPO="https://github.com/<YOUR_ORG>/qbnox-lti-platform.git"

echo "🚀 Setting up Pressbooks + Moodle LTI local lab"

### 1. Prerequisites ###
if ! command -v docker >/dev/null 2>&1; then
  echo "🐳 Installing Docker..."
  sudo apt update
  sudo apt install -y docker.io docker-compose-plugin
  sudo systemctl enable docker
  sudo systemctl start docker
fi

if ! command -v mkcert >/dev/null 2>&1; then
  echo "🔐 Installing mkcert..."
  sudo apt install -y libnss3-tools mkcert
  mkcert -install
fi

### 2. Hosts file ###
echo "🧩 Configuring /etc/hosts"
sudo sed -i "/$MOODLE_HOST/d" /etc/hosts
sudo sed -i "/$PRESSBOOKS_HOST/d" /etc/hosts
echo "127.0.0.1 $MOODLE_HOST $PRESSBOOKS_HOST" | sudo tee -a /etc/hosts >/dev/null

### 3. Create lab directory ###
mkdir -p "$LAB_DIR"
cd "$LAB_DIR"

### 4. TLS certs ###
echo "🔐 Generating local TLS certificates"
mkcert "$MOODLE_HOST" "$PRESSBOOKS_HOST"

### 5. Docker Compose ###
echo "🐳 Writing docker-compose.yml"

cat > docker-compose.yml <<EOF
version: "3.9"

networks:
  lti-net:

services:
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root
    networks: [lti-net]

  moodle:
    image: bitnami/moodle:5.1
    environment:
      MOODLE_DATABASE_HOST: mysql
      MOODLE_DATABASE_USER: root
      MOODLE_DATABASE_PASSWORD: root
      MOODLE_DATABASE_NAME: moodle
      MOODLE_SITE_NAME: "Moodle Local"
      MOODLE_HOST: $MOODLE_HOST
      MOODLE_ENABLE_SSL: "yes"
    ports:
      - "8080:8080"
    depends_on: [mysql]
    networks: [lti-net]

  pressbooks:
    image: pressbooks/pressbooks:latest
    environment:
      WORDPRESS_DB_HOST: mysql
      WORDPRESS_DB_USER: root
      WORDPRESS_DB_PASSWORD: root
      WORDPRESS_DB_NAME: pressbooks
    ports:
      - "8081:80"
    depends_on: [mysql]
    networks: [lti-net]
EOF

### 6. Start stack ###
echo "▶️ Starting containers"
docker compose up -d

echo "⏳ Waiting for services to initialize..."
sleep 60

### 7. Install plugin ###
echo "📦 Installing Pressbooks LTI Platform plugin"

PRESSBOOKS_CONTAINER=$(docker ps --filter "ancestor=pressbooks/pressbooks" --format "{{.ID}}")

docker exec "$PRESSBOOKS_CONTAINER" bash -c "
  cd /var/www/html/wp-content/plugins &&
  if [ ! -d qbnox-lti-platform ]; then
    git clone $PLUGIN_REPO qbnox-lti-platform
  fi
  wp plugin activate qbnox-lti-platform --network
"

### 8. Final output ###
echo ""
echo "✅ Local LTI Lab Ready"
echo ""
echo "🌐 Moodle:     https://$MOODLE_HOST:8080"
echo "📚 Pressbooks: https://$PRESSBOOKS_HOST:8081"
echo ""
echo "➡ Next steps:"
echo "  1. Finish Moodle admin setup wizard"
echo "  2. Create LTI 1.3 tool in Moodle"
echo "  3. Register platform + deployment in Pressbooks"
echo "  4. Run your LTI test checklist"
echo ""
echo "🎉 Done"

