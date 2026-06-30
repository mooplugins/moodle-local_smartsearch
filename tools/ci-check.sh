#!/usr/bin/env bash
# Run the same moodle-plugin-ci steps as .github/workflows/moodle-ci.yml (from plugin root).
# Requires moodle-plugin-ci on PATH (see README).
set -euo pipefail

PLUGIN_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SKIP_GRUNT=0
STRICT=0

usage() {
    cat <<'EOF'
Usage: tools/ci-check.sh [options]

Runs moodle-plugin-ci lint steps against this plugin directory.
Matches GitHub Actions (mustache + grunt are required unless --skip-grunt).

Options:
  --skip-grunt   Skip Grunt/ESLint (not recommended before PR)
  --strict       Stop on first failure (default: continue, exit non-zero at end)
  -h, --help     Show this help

Environment:
  MOODLE_DIR     Moodle root with config.php (for phpunit via plugin-ci if needed)
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-grunt) SKIP_GRUNT=1; shift ;;
        --strict) STRICT=1; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage; exit 1 ;;
    esac
done

if ! command -v moodle-plugin-ci >/dev/null 2>&1; then
    echo "moodle-plugin-ci not found. Install with:" >&2
    echo "  composer create-project -n --no-dev --prefer-dist moodlehq/moodle-plugin-ci ~/moodle-plugin-ci ^4.5" >&2
    echo "  export PATH=\"\$HOME/moodle-plugin-ci/bin:\$PATH\"" >&2
    exit 1
fi

FAILED=0

run_step() {
    local label="$1"
    shift
    echo "==> $label"
    set +e
    "$@"
    local status=$?
    set -e
    if [[ $status -eq 0 ]]; then
        echo "OK: $label"
        return 0
    fi
    FAILED=1
    echo "FAIL: $label" >&2
    if [[ $STRICT -eq 1 ]]; then
        exit $status
    fi
    return $status
}

run_step "phplint" moodle-plugin-ci phplint "$PLUGIN_DIR" || true
run_step "phpmd (informational)" moodle-plugin-ci phpmd "$PLUGIN_DIR" || true
run_step "codechecker" moodle-plugin-ci codechecker --max-warnings 0 "$PLUGIN_DIR"
run_step "phpdoc" moodle-plugin-ci phpdoc "$PLUGIN_DIR"
run_step "validate" moodle-plugin-ci validate "$PLUGIN_DIR"
run_step "savepoints" moodle-plugin-ci savepoints "$PLUGIN_DIR"
run_step "quality-gate" bash "$PLUGIN_DIR/tools/quality-gate.sh"
run_step "mustache" moodle-plugin-ci mustache "$PLUGIN_DIR"

if [[ $SKIP_GRUNT -eq 0 ]]; then
    run_step "grunt" bash "$PLUGIN_DIR/tools/ci-grunt.sh"
else
    echo "SKIP: grunt (--skip-grunt)"
fi

if [[ $FAILED -ne 0 ]]; then
    echo "" >&2
    echo "CI check failed. Fix errors above before opening a PR." >&2
    exit 1
fi

echo ""
echo "CI check complete."
