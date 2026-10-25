#!/usr/bin/env bash
# Computes the next release version, updates the version files and the
# CHANGELOG, and writes the release notes. Used by .github/workflows/release.yml
# on every push to main; can be run locally with DRY_RUN=1.
#
# Version rule:
#   - header version newer than the latest tag  -> release the header version;
#   - otherwise bump from Conventional Commits since the latest tag:
#       "BREAKING CHANGE" or "type!:" -> major, "feat" -> minor, anything else -> patch.
#
# Outputs (also appended to $GITHUB_OUTPUT when set):
#   version=X.Y.Z   bumped=true|false   changed=true|false (files to commit)   notes=<path>
set -euo pipefail
cd "$(dirname "$0")/.."

# ── Repository-specific configuration ───────────────────────────────────────
MAIN_FILE="sciflow-wp.php"                       # holds " * Version: X.Y.Z"
CONST_FILE="sciflow-wp.php"                      # holds define('SCIFLOW_VERSION', 'X.Y.Z')
CONST_NAME="SCIFLOW_VERSION"
HEADER_REGEX='^ \* Version:[[:space:]]*'
# ────────────────────────────────────────────────────────────────────────────

header_version() { sed -n "s/${HEADER_REGEX}//p" "$MAIN_FILE" | head -1 | tr -d '[:space:]'; }
ver_gt() { [ "$1" != "$2" ] && [ "$(printf '%s\n%s\n' "$1" "$2" | sort -V | tail -1)" = "$1" ]; }

current=$(header_version)
last_tag=$(git tag --list 'v[0-9]*.[0-9]*.[0-9]*' --sort=-v:refname | head -1)
last=${last_tag#v}
range=${last_tag:+$last_tag..}HEAD

if [ -z "$last_tag" ] || ver_gt "$current" "$last"; then
  version="$current"
  bumped=false
else
  log=$(git log --format='%s%n%b%x1e' "$range" | grep -v '^chore(release)' || true)
  IFS=. read -r major minor patch <<< "$last"
  if printf '%s' "$log" | grep -qE '(^|[[:space:]])BREAKING[ -]CHANGE|^[a-z]+(\([^)]*\))?!:'; then
    version="$((major + 1)).0.0"
  elif printf '%s' "$log" | grep -qE '^feat(\([^)]*\))?:'; then
    version="${major}.$((minor + 1)).0"
  else
    version="${major}.${minor}.$((patch + 1))"
  fi
  bumped=true
fi

notes=$(mktemp)
today=$(date -u +%Y-%m-%d)

# Release notes: the CHANGELOG section when it already exists, otherwise the
# commits since the latest tag grouped by type.
if grep -q "^## ${version//./\\.}\b" CHANGELOG.md 2>/dev/null; then
  awk -v v="$version" 'index($0, "## " v) == 1 {p=1; next} /^## / && p {exit} p' CHANGELOG.md > "$notes"
else
  {
    for group in "feat|Novidades" "fix|Correções" "perf|Desempenho" "sec|Segurança"; do
      type=${group%%|*}; title=${group#*|}
      lines=$(git log --format='%s' "$range" | grep -E "^${type}(\([^)]*\))?!?:" | sed -E 's/^[a-z]+(\(([^)]*)\))?!?:[[:space:]]*/- \2: /; s/- : /- /' || true)
      [ -n "$lines" ] && printf '### %s\n%s\n\n' "$title" "$lines"
    done
    other=$(git log --format='%s' "$range" | grep -vE '^(feat|fix|perf|sec|chore\(release\))(\([^)]*\))?!?:' | sed 's/^/- /' || true)
    [ -n "$other" ] && printf '### Outras mudanças\n%s\n\n' "$other"
  } > "$notes"
  [ -s "$notes" ] || echo "- Manutenção." > "$notes"
fi

has_section=false
grep -q "^## ${version//./\\.}\b" CHANGELOG.md 2>/dev/null && has_section=true

if [ "${DRY_RUN:-0}" != "1" ]; then
  # Header and constant always follow the released version (the constant
  # drives the plugin's upgrade routine, so they must never diverge).
  sed -i -E "s/(${HEADER_REGEX})[0-9]+\.[0-9]+\.[0-9]+/\1${version}/" "$MAIN_FILE"
  sed -i -E "s/(define\('${CONST_NAME}', ')[0-9]+\.[0-9]+\.[0-9]+('\))/\1${version}\2/" "$CONST_FILE"
  if [ "$has_section" = "false" ] && [ -f CHANGELOG.md ]; then
    { sed -n '1,/^# /p' CHANGELOG.md | head -1; echo; echo "## ${version} — ${today}"; cat "$notes"; sed '1d' CHANGELOG.md; } > CHANGELOG.md.new
    mv CHANGELOG.md.new CHANGELOG.md
  fi
fi

# Files to commit?
if [ -n "$(git status --porcelain -- "$MAIN_FILE" "$CONST_FILE" CHANGELOG.md)" ]; then
  changed=true
elif [ "${DRY_RUN:-0}" = "1" ] && { [ "$bumped" = "true" ] || [ "$has_section" = "false" ]; }; then
  changed=true
else
  changed=false
fi

echo "version=${version}"
echo "bumped=${bumped}"
echo "changed=${changed}"
echo "notes=${notes}"
if [ -n "${GITHUB_OUTPUT:-}" ]; then
  { echo "version=${version}"; echo "bumped=${bumped}"; echo "changed=${changed}"; echo "notes=${notes}"; } >> "$GITHUB_OUTPUT"
fi
