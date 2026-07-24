#!/usr/bin/env bash
# Phase 3.8 audit: mechanical data collection over lib/common.lib.php's
# 111 functions. Static-grep only — see the audit doc's methodology
# section for what this does and doesn't catch (no dynamic-dispatch
# detection, no empirical/curl verification of "0 callers" findings).
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
LIB_FILE="$REPO_ROOT/lib/common.lib.php"
TSV_OUT="$REPO_ROOT/Docs/superpowers/scripts/2026-07-24-phase3.8-audit-data.tsv"
MD_OUT="$REPO_ROOT/Docs/superpowers/scripts/2026-07-24-phase3.8-audit-table.md"

cd "$REPO_ROOT"

# macOS ships bash 3.2 (no `mapfile`/`readarray`, added in bash 4.0) —
# build the array with a read loop instead for portability.
FUNC_LINES=()
while IFS= read -r line; do
  # Only add non-empty lines (bash 3.2 compatibility: avoid spurious empty elements)
  if [ -n "$line" ]; then
    FUNC_LINES+=("$line")
  fi
done < <(grep -n "^function " "$LIB_FILE")
TOTAL=${#FUNC_LINES[@]}
LAST_LINE=$(awk 'END{print NR}' "$LIB_FILE")

printf 'function\tstart_line\tend_line\tlegacy_callers\tmodern_callers\ttest_files\tsql_query_calls\tescape_discard_count\n' > "$TSV_OUT"
printf '| Function (line range) | Legacy callers | Modern callers | Test coverage | SQL execution | Escape-discard bug | Classification (provisional) |\n' > "$MD_OUT"
printf '|---|---|---|---|---|---|---|\n' >> "$MD_OUT"

for i in "${!FUNC_LINES[@]}"; do
  line="${FUNC_LINES[$i]}"
  start_line="${line%%:*}"
  rest="${line#*:}"
  fname=$(echo "$rest" | sed -E 's/^function[[:space:]]+&?([A-Za-z_][A-Za-z0-9_]*)\(.*/\1/')

  if [ $((i + 1)) -lt "$TOTAL" ]; then
    next_line="${FUNC_LINES[$((i + 1))]}"
    end_line=$(( ${next_line%%:*} - 1 ))
  else
    end_line="$LAST_LINE"
  fi

  # Temporarily disable pipefail for grep commands (bash 3.2 compatibility)
  set +o pipefail
  # Use word-boundary pattern to avoid substring collisions (e.g., relocate vs admin_relocate)
  MATCHES=$(grep -rn --include="*.php" -E "(^|[^A-Za-z0-9_])${fname}\(" . \
    --exclude-dir=vendor --exclude-dir=.worktrees --exclude-dir=.phpstan.cache \
    --exclude-dir=.git --exclude-dir=node_modules \
    | grep -v "^\./lib/common\.lib\.php:${start_line}:" || true)

  legacy_count=$(printf '%s\n' "$MATCHES" | grep -Ev "^\./(src|tests)/" | grep -c . || true)
  modern_count=$(printf '%s\n' "$MATCHES" | grep -E "^\./src/" | grep -c . || true)
  test_files=$(printf '%s\n' "$MATCHES" | grep -E "^\./tests/" | cut -d: -f1 | sed 's#^\./tests/##' | sort -u | paste -sd ';' -)
  test_files=${test_files:-none}

  BODY=$(sed -n "${start_line},${end_line}p" "$LIB_FILE")
  sql_calls=$(printf '%s\n' "$BODY" | grep -c "mysqli_query(\|mysqli_multi_query(" || true)
  escape_discard=$(printf '%s\n' "$BODY" | grep -cE "^[[:space:]]*mysqli_real_escape_string\(" || true)
  set -o pipefail

  if [ "$legacy_count" -eq 0 ] && [ "$modern_count" -eq 0 ] && [ "$test_files" = "none" ]; then
    classification="dead-code candidate (unverified)"
  elif [ "$sql_calls" -gt 0 ]; then
    classification="extraction candidate"
  else
    classification="keep-as-legacy-only"
  fi

  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
    "$fname" "$start_line" "$end_line" "$legacy_count" "$modern_count" "$test_files" "$sql_calls" "$escape_discard" \
    >> "$TSV_OUT"

  printf '| `%s()` %s-%s | %s | %s | %s | %s | %s | %s |\n' \
    "$fname" "$start_line" "$end_line" "$legacy_count" "$modern_count" "$test_files" "$sql_calls" "$escape_discard" "$classification" \
    >> "$MD_OUT"
done

echo "Wrote $TOTAL rows to $TSV_OUT and $MD_OUT"
