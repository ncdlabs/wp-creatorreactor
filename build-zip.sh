#!/bin/bash
set -e

VERSION=$(grep -E "^ \* Version:" creatorreactor.php | sed 's/.*Version: //')
OUTPUT="creatorreactor-${VERSION}.zip"

echo "Packaging CreatorReactor v${VERSION}..."

cd "$(dirname "$0")"

rm -f "$OUTPUT"

zip -r "$OUTPUT" creatorreactor.php includes/ js/ css/ templates/ img/ assets/ languages/ -x "*.DS_Store" "*/.git/*"

echo "Created: $OUTPUT"
ls -lh "$OUTPUT"
