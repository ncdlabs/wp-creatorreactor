#!/usr/bin/env bash
set -euo pipefail

SSH_HOST="109.106.251.207"
SSH_PORT="65002"
SSH_USER="u589315944"
SSH_KEY="${HOME}/.ssh/id_rsa"
REMOTE_PATH="/home/u589315944/domains/darkslategrey-zebra-176435.hostingersite.com/public_html/wp-content/plugins/wp-creatorreactor"
LOCAL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "Deploying plugin via rsync..."
echo "Source: ${LOCAL_DIR}/"
echo "Target: ${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}"

rsync -azv --delete \
  -e "ssh -p ${SSH_PORT} -i ${SSH_KEY}" \
  --exclude=".git/" \
  --exclude=".gitignore" \
  --exclude=".cursor/" \
  --exclude=".idea/" \
  --exclude="*.zip" \
  --exclude="*.log" \
  --exclude=".DS_Store" \
  --exclude="Thumbs.db" \
  --exclude="tests/" \
  --exclude="docs/" \
  --exclude="README.md" \
  --exclude="composer.lock" \
  --exclude="composer.json" \
  "${LOCAL_DIR}/" \
  "${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}/"

echo "Deployment complete."
