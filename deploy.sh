#!/bin/bash
set -e

SSH_HOST="u650398700@82.29.157.171"
SSH_PORT="65002"
REMOTE_PATH="/home/u650398700/domains/royalblue-porcupine-625519.hostingersite.com/public_html/wp-content/plugins/wp-creatorreactor"

LOCAL_DIR="$(dirname "$0")"

echo "Deploying to $SSH_HOST (port $SSH_PORT)..."
echo "Target: $REMOTE_PATH"

rsync -avz --delete \
    -e "ssh -p $SSH_PORT" \
    --exclude='*.zip' \
    --exclude='.git' \
    --exclude='.gitignore' \
    --exclude='deploy.sh' \
    --exclude='build-zip.sh' \
    --exclude='README.md' \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    --exclude='*.log' \
    --exclude='*.cache' \
    --exclude='composer.lock' \
    --exclude='composer.json' \
    --exclude='tests/' \
    --exclude='docs/' \
    --exclude='.cursor/' \
    --exclude='.idea/' \
    "$LOCAL_DIR/" \
    "$SSH_HOST:$REMOTE_PATH"

echo "Deployment complete!"
