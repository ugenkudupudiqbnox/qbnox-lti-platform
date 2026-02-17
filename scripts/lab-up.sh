#!/usr/bin/env bash
set -e

# Load environment configuration
source "$(dirname "$0")/load-env.sh"

# Set default Moodle version and convert to Moodle branch format (e.g. 4.4 -> MOODLE_404_STABLE)
MOODLE_VERSION="${MOODLE_VERSION:-4.4}"
if [[ "$MOODLE_VERSION" =~ ^[0-9]+\.[0-9]+$ ]]; then
    MAJOR=$(echo $MOODLE_VERSION | cut -d. -f1)
    MINOR=$(echo $MOODLE_VERSION | cut -d. -f2)
    export MOODLE_BRANCH="MOODLE_${MAJOR}0${MINOR}_STABLE"
else
    export MOODLE_BRANCH="${MOODLE_VERSION}"
fi
echo "🚀 Using Moodle version $MOODLE_VERSION (Branch: $MOODLE_BRANCH)"

# === Installation Steps ===

# 1. Check for Docker Engine and install if missing
if ! command -v docker &> /dev/null; then
    echo "🐳 Docker not found. Attempting to install..."
    
    # OS Detection
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS_NAME=$NAME
    else
        OS_NAME="unknown Linux"
    fi

    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        echo "📡 Detected OS: $OS_NAME"
        
        # Kali Linux specific logic
        if [[ "$OS_NAME" == *"Kali"* ]]; then
             echo "⚠️  Kali Linux detected. Using native apt-get installation to avoid repository conflicts."
             sudo rm -f /etc/apt/sources.list.d/docker.list
             sudo apt-get update
             sudo apt-get install -y docker.io
        
        # Ubuntu/Common Debian-based logic
        elif [[ "$OS_NAME" == *"Ubuntu"* ]] || [[ "$OS_NAME" == *"Debian"* ]] || command -v apt-get &> /dev/null; then
             echo "📦 Ubuntu/Debian-based system detected."
             if command -v curl &> /dev/null; then
                 echo "Using official Docker installation script..."
                 curl -fsSL https://get.docker.com | sudo sh
             else
                 echo "curl not found, using native apt-get installation..."
                 sudo apt-get update
                 sudo apt-get install -y docker.io
             fi
        
        # Other common (RPM-based) logic
        elif command -v curl &> /dev/null; then
            echo "Using official Docker installation script for $OS_NAME..."
            curl -fsSL https://get.docker.com | sudo sh
        elif command -v wget &> /dev/null; then
            echo "Using official Docker installation script for $OS_NAME..."
            wget -qO- https://get.docker.com | sudo sh
        else
            echo "❌ Error: curl or wget required to install Docker on $OS_NAME."
            exit 1
        fi
        
        sudo systemctl start docker || true
        sudo systemctl enable docker || true
        echo "✅ Docker Engine installed successfully."
    else
        echo "❌ Error: Automated Docker installation only supported on Linux. Please install Docker manually."
        exit 1
    fi
fi

# 2. Check for Docker Compose (V2 plugin or V1 standalone) and install if missing
if ! docker compose version &> /dev/null && ! command -v docker-compose &> /dev/null; then
    echo "🐙 Docker Compose not found. Detected OS: $OS_NAME"
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        if command -v apt-get &> /dev/null; then
            echo "Attempting to install docker-compose via apt-get..."
            sudo apt-get update
            # Try docker-compose-plugin (V2) first, fall back to standalone docker-compose
            if ! sudo apt-get install -y docker-compose-plugin &> /dev/null; then
                echo "⚠️  docker-compose-plugin not found in repos. Trying standalone docker-compose..."
                sudo apt-get install -y docker-compose
            fi
        elif command -v yum &> /dev/null; then
            echo "Attempting to install docker-compose via yum..."
            sudo yum install -y docker-compose-plugin || sudo yum install -y docker-compose
        elif command -v dnf &> /dev/null; then
            echo "Attempting to install docker-compose via dnf..."
            sudo dnf install -y docker-compose-plugin || sudo dnf install -y docker-compose
        else
            echo "❌ Error: Could not determine package manager to install Docker Compose."
            echo "Please install it manually: https://docs.docker.com/compose/install/linux/"
            exit 1
        fi
        echo "✅ Docker Compose installed successfully."
    else
        echo "❌ Error: Automated Docker Compose installation only supported on Linux."
        exit 1
    fi
fi

# 3. Verify Docker Permissions and provide instructions if failed
if ! docker ps &> /dev/null; then
    if [[ "$CI" == "true" || "$NON_INTERACTIVE" == "true" ]]; then
        echo "Non-interactive mode: Using sudo for Docker..."
        SUDO="sudo -E"
    elif [[ -t 0 ]]; then
        echo "⚠️  Permission denied when connecting to Docker daemon."
        echo "Try running the following commands to add your user to the docker group:"
        echo ""
        echo "    sudo usermod -aG docker $USER"
        echo "    newgrp docker"
        echo ""
        read -p "Would you like to try running with sudo for this session? (y/n) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
        SUDO="sudo -E"
    else
        echo "Non-interactive shell detected. Attempting to use sudo..."
        SUDO="sudo -E"
    fi
else
    SUDO=""
fi

# 4. Check for port conflicts (8080, 8081 are used by Moodle/Pressbooks)
if command -v lsof &> /dev/null; then
    for port in 8080 8081; do
        if lsof -Pi :$port -sTCP:LISTEN -t >/dev/null ; then
            echo "❌ Error: Port $port is already in use."
            echo "Please stop the process using port $port and try again."
            lsof -i :$port
            exit 1
        fi
    done
fi

# 5. Check /etc/hosts for local domains
echo "🔍 Checking /etc/hosts for local domain mappings..."
for domain in "$MOODLE_DOMAIN" "$PRESSBOOKS_DOMAIN"; do
    if ! grep -qE "\b$domain\b" /etc/hosts; then
        echo "📝 Adding $domain to /etc/hosts..."
        echo "127.0.0.1 $domain" | sudo tee -a /etc/hosts > /dev/null
    else
        echo "✅ $domain already mapped in /etc/hosts"
    fi
done

# 6. Configure Local Nginx Proxy
if [[ "$MOODLE_DOMAIN" == "moodle.local" && "$PRESSBOOKS_DOMAIN" == "pressbooks.local" ]]; then
    echo "🌐 Setting up local Nginx proxy..."
    sudo "$(dirname "$0")/setup-local-nginx.sh"
fi

# === Setup Lab ===

# Ensure we are in the project root or lti-local-lab exists
if [ ! -d "lti-local-lab" ]; then
    # Try to find it if we are inside the scripts directory
    if [ -d "../lti-local-lab" ]; then
        cd ..
    else
        echo "❌ Error: lti-local-lab directory not found. Please run this script from the project root."
        exit 1
    fi
fi

cd lti-local-lab

# Detect compose command
if command -v docker-compose &> /dev/null; then
  DC="docker-compose"
elif docker compose version &> /dev/null; then
  DC="docker compose"
else
  echo "❌ Error: Docker Compose is not functioning correctly."
  echo "Instructions to install manually: https://docs.docker.com/compose/install/"
  exit 1
fi

echo "🚀 Starting containers with $SUDO $DC..."
$SUDO $DC up -d

echo "⏳ Waiting for services to become healthy..."

# Wait for MySQL to be healthy
echo "➡ Waiting for MySQL"
until $SUDO $DC ps | grep mysql | grep -q healthy; do
  sleep 3
done
echo "✅ MySQL is healthy"

# Wait for Moodle container to be running
echo "➡ Waiting for Moodle"
until $SUDO $DC ps | grep moodle | grep -q "Up"; do
  sleep 3
done
# Wait for Moodle installation to complete
echo "⏳ Checking Moodle status..."
# If config.php doesn't exist yet, we must wait for the installation to finish completely
if ! $SUDO $DC exec -T moodle test -f config.php; then
  echo "⏳ Moodle is performing initial installation, this may take a minute..."
  until $SUDO $DC exec -T moodle test -f .installation_complete; do
    echo "Wait..."
    sleep 5
  done
fi
# Final check to ensure Moodle is responsive
until $SUDO $DC exec -T moodle php admin/cli/check_database_schema.php >/dev/null 2>&1; do
  echo "⏳ Waiting for Moodle database to be ready..."
  sleep 5
done
echo "✅ Moodle is installed and ready"

# Wait for Pressbooks container to be running
echo "➡ Waiting for Pressbooks"
until $SUDO $DC ps | grep pressbooks | grep -q "Up"; do
  sleep 3
done

# Wait for Pressbooks installation to complete (it runs composer install on first run)
echo "⏳ Checking Pressbooks status..."
if ! $SUDO $DC exec -T pressbooks test -f .installation_complete; then
  echo "⏳ Pressbooks is performing initial installation (Composer install might take a few minutes)..."
  until $SUDO $DC exec -T pressbooks test -f .installation_complete; do
    echo "Wait..."
    sleep 5
  done
fi

echo "✅ Pressbooks is installed and ready"

echo "🚀 Local LTI lab is ready"
