#!/usr/bin/env bash
set -euo pipefail

source "$(dirname "$0")/load-env.sh"

echo "📦 Installing H5P libraries into Pressbooks"

if docker compose version &>/dev/null 2>&1; then
    DC="docker compose -f lti-local-lab/docker-compose.yml"
else
    DC="docker-compose -f lti-local-lab/docker-compose.yml"
fi

H5P_FILE="/tmp/H5P.ArithmeticQuiz.h5p"
H5P_URL="https://api.h5p.org/v1/content-types/H5P.ArithmeticQuiz"

# Download the ArithmeticQuiz package inside the container.
# It bundles ~29 libraries; installing it as content registers them all.
echo "⬇️  Downloading H5P.ArithmeticQuiz from Hub CDN..."
if ! sudo -E $DC exec -T pressbooks bash -c \
    "curl -sfL '$H5P_URL' -o '$H5P_FILE' && echo 'Downloaded: '\$(wc -c < '$H5P_FILE')' bytes'"; then
    echo "❌ Download failed. Check that the container has outbound internet access."
    exit 1
fi

# Copy the install script into the container and run it
echo "🔧 Running H5P library installer..."
sudo $DC cp scripts/h5p-install-libraries.php pressbooks:/tmp/h5p-install-libraries.php
sudo -E $DC exec -T pressbooks php /tmp/h5p-install-libraries.php

# Fix permissions and ensure folders exist for all blogs
echo "🔧 Fixing H5P directory permissions..."
bash scripts/fix-h5p-data.sh

echo "✅ H5P library installation complete"
