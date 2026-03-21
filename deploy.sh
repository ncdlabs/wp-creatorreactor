#!/bin/bash
set -e

SSH_HOST="u650398700@82.29.157.171"
SSH_PORT="65002"
REMOTE_PATH="/home/u650398700/domains/lightskyblue-lyrebird-554318.hostingersite.com/public_html/wp-content/plugins/creatorreactor-2.0.1/"

LOCAL_DIR="$(dirname "$0")"

echo "Deploying to $SSH_HOST (port $SSH_PORT)..."
echo "Target: $REMOTE_PATH"

rsync -avz --delete \
    -e "ssh -p $SSH_PORT" \
    --exclude='*.zip' \
    --exclude='.git' \
    --exclude='deploy.sh' \
    --exclude='build-zip.sh' \
    --exclude='README.md' \
    "$LOCAL_DIR/" \
    "$SSH_HOST:$REMOTE_PATH"

echo "Deployment complete!"
