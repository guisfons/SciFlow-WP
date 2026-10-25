#!/usr/bin/env bash
# Builds the installable ZIP from the committed tree (git archive honours
# .gitattributes export-ignore, so tests/, docs/ and bin/ are left out).
#   bin/build.sh [ref]   (default: HEAD)  ->  dist/sciflow-wp-<version>.zip
set -euo pipefail
cd "$(dirname "$0")/.."
REF="${1:-HEAD}"
VERSION=$(git show "$REF:sciflow-wp.php" | sed -n 's/^ \* Version:[[:space:]]*//p' | tr -d '[:space:]')
mkdir -p dist
OUT="dist/sciflow-wp-${VERSION}.zip"
git archive --format=zip --prefix=SciFlow-WP/ -o "$OUT" "$REF"
# Sanity checks: no VCS data, tests or secrets inside the package.
if unzip -l "$OUT" | grep -E '\.git/|/tests/|/docs/|\.env|\.sql|debug\.log|test-cron\.php' ; then
  echo "ERRO: arquivo indevido no pacote" >&2; exit 1
fi
unzip -l "$OUT" | grep -q 'SciFlow-WP/sciflow-wp.php' || { echo "ERRO: arquivo principal ausente" >&2; exit 1; }
sha256sum "$OUT" > "$OUT.sha256"
echo "OK $OUT ($(unzip -l "$OUT" | tail -1 | awk '{print $2}') arquivos)"
