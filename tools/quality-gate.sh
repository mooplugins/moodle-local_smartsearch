#!/usr/bin/env bash
# Architecture, N+1 regression, and compliance checks for Smart Search.
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"

echo "==> quality-gate (static)"
php "$PLUGIN_DIR/tools/quality-gate.php"
