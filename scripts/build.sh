#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD="$ROOT/build"
STAGE="$BUILD/integrate-wpforms-mattermost"

rm -rf "$BUILD"
mkdir -p "$STAGE"
rsync -a --exclude='.git' --exclude='.github' --exclude='build' --exclude='tests' --exclude='.phpunit.cache' --exclude='IMPLEMENTATION-PLAN.md' "$ROOT/" "$STAGE/"
composer install --working-dir="$STAGE" --no-dev --prefer-dist --optimize-autoloader
find "$STAGE" -exec touch -t 202001010000 {} +
(cd "$BUILD" && find "integrate-wpforms-mattermost" -type f -print | LC_ALL=C sort | zip -X -q "integrate-wpforms-mattermost-1.1.1.zip" -@)

echo "$BUILD/integrate-wpforms-mattermost-1.1.1.zip"
