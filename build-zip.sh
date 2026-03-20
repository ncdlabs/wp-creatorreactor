#!/bin/bash
set -e

VERSION=$(grep -E "^ \* Version:" fanbridge.php | sed 's/.*Version: //')
OUTPUT="fanbridge-${VERSION}.zip"

echo "Packaging FanBridge v${VERSION}..."

cd "$(dirname "$0")"

rm -f "$OUTPUT"

zip -r "$OUTPUT" fanbridge.php includes/ languages/ -x "*.DS_Store" "*/.git/*"

echo "Created: $OUTPUT"
ls -lh "$OUTPUT"
