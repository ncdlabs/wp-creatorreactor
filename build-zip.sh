#!/bin/bash
set -e

# Bump plugin version in creatorreactor.php before packaging, or package the current tree as-is.
# Usage:
#   ./build-zip.sh [--major|--minor|--patch]
#   ./build-zip.sh --package-only
# Default (without --package-only):
#   --patch
#
# Version schema:
#   {major}.{minor}.{patch}
#
# --package-only: do not bump CREATORREACTOR_VERSION; build wp-creatorreactor-<current>.zip for CI/releases.

PACKAGE_ONLY=0
BUMP_TYPE="patch"
for arg in "$@"; do
	case "$arg" in
		--package-only)
			if [ "$BUMP_TYPE" != "patch" ]; then
				echo "Error: --package-only cannot be combined with version bump flags" >&2
				exit 1
			fi
			if [ "$PACKAGE_ONLY" -eq 1 ]; then
				echo "Error: duplicate --package-only" >&2
				exit 1
			fi
			PACKAGE_ONLY=1
			;;
		--major)
			if [ "$PACKAGE_ONLY" -eq 1 ]; then
				echo "Error: --package-only cannot be combined with version bump flags" >&2
				exit 1
			fi
			if [ "$BUMP_TYPE" != "patch" ]; then
				echo "Error: only one of --major/--minor/--patch may be provided" >&2
				exit 1
			fi
			BUMP_TYPE="major"
			;;
		--minor)
			if [ "$PACKAGE_ONLY" -eq 1 ]; then
				echo "Error: --package-only cannot be combined with version bump flags" >&2
				exit 1
			fi
			if [ "$BUMP_TYPE" != "patch" ]; then
				echo "Error: only one of --major/--minor/--patch may be provided" >&2
				exit 1
			fi
			BUMP_TYPE="minor"
			;;
		--patch)
			if [ "$PACKAGE_ONLY" -eq 1 ]; then
				echo "Error: --package-only cannot be combined with version bump flags" >&2
				exit 1
			fi
			# Explicitly request patch bump.
			BUMP_TYPE="patch"
			;;
		*)
			echo "Error: unknown argument: $arg" >&2
			echo "Usage: $0 [--major|--minor|--patch] | $0 --package-only" >&2
			exit 1
			;;
	esac
done

# WordPress treats the top-level directory inside the zip as the plugin folder. A flat zip
# (creatorreactor.php at the zip root) often installs as a second plugin instead of updating.
# This script packages as: <PLUGIN_SLUG>/creatorreactor.php ...
#
# Match PLUGIN_SLUG to the folder under wp-content/plugins/ on the server (must match for updates).
# This repo deploys as wp-creatorreactor/ (see deploy.sh). Override if your install uses another name:
#   PLUGIN_SLUG=creatorreactor ./build-zip.sh

CURRENT_VERSION="$(sed -nE "s/^define\\( 'CREATORREACTOR_VERSION', '([0-9]+\\.[0-9]+\\.[0-9]+)'.*/\\1/p" creatorreactor.php)"
if ! echo "$CURRENT_VERSION" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$'; then
	echo "Error: could not parse current version from creatorreactor.php (got: '$CURRENT_VERSION')" >&2
	exit 1
fi

IFS='.' read -r VERSION_MAJOR VERSION_MINOR VERSION_PATCH <<< "$CURRENT_VERSION"

if [ "$PACKAGE_ONLY" -eq 1 ]; then
	VERSION="$CURRENT_VERSION"
	echo "Packaging current version (no bump): $VERSION"
else
	case "$BUMP_TYPE" in
		major)
			VERSION_MAJOR=$((VERSION_MAJOR + 1))
			VERSION_MINOR=0
			VERSION_PATCH=0
			;;
		minor)
			VERSION_MINOR=$((VERSION_MINOR + 1))
			VERSION_PATCH=0
			;;
		patch)
			VERSION_PATCH=$((VERSION_PATCH + 1))
			;;
		*)
			echo "Error: unknown BUMP_TYPE '$BUMP_TYPE'" >&2
			exit 1
			;;
	esac

	VERSION="${VERSION_MAJOR}.${VERSION_MINOR}.${VERSION_PATCH}"

	# Update both the docblock version and the runtime constant to keep everything consistent.
	perl -pi -e "s/\\* Version: \\d+\\.\\d+\\.\\d+/\\* Version: $VERSION/;" creatorreactor.php
	perl -pi -e "s/define\\( 'CREATORREACTOR_VERSION', '\\d+\\.\\d+\\.\\d+' \\);/define( 'CREATORREACTOR_VERSION', '$VERSION' );/;" creatorreactor.php

	echo "Bumped CreatorReactor version: $CURRENT_VERSION -> $VERSION"
fi

PLUGIN_SLUG="${PLUGIN_SLUG:-wp-creatorreactor}"
# Name the zip after PLUGIN_SLUG so it is obvious which folder WordPress will create (avoids mixing with creatorreactor-*.zip).
OUTPUT="${PLUGIN_SLUG}-${VERSION}.zip"

echo "Packaging CreatorReactor v${VERSION} as ${PLUGIN_SLUG}/..."
echo "WordPress will install to: wp-content/plugins/${PLUGIN_SLUG}/creatorreactor.php"

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

rm -f "$OUTPUT"

STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$STAGE/${PLUGIN_SLUG}"
cp creatorreactor.php uninstall.php "$STAGE/${PLUGIN_SLUG}/"
if [ -f README.md ]; then
	cp README.md "$STAGE/${PLUGIN_SLUG}/"
fi
for d in includes js css templates img assets languages; do
	if [ -e "$d" ]; then
		cp -R "$d" "$STAGE/${PLUGIN_SLUG}/"
	fi
done

(
	cd "$STAGE"
	zip -r "$ROOT/$OUTPUT" "$PLUGIN_SLUG" -x "*.DS_Store" "*/.git/*"
)

echo "Created: $OUTPUT"
ls -lh "$OUTPUT"
