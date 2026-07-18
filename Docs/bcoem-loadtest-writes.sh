#!/usr/bin/env bash
#
# bcoem-loadtest-writes.sh — WRITE-mode load test for a THROWAWAY BCOE&M
# instance (e.g. gatlin.horsemenofthehops.com).
#
# Each simulated user, in a loop until the duration ends:
#   1. GETs the entrant registration page (establishes a real PHP session
#      and scrapes the per-session CSRF token, user_session_token, that
#      includes/process.inc.php requires on every POST to this endpoint)
#   2. POSTs a complete account + demographic registration with a
#      unique email — a genuine MariaDB write path, same as a real
#      entrant filling in the demographic form
#
# This POINTS REAL WRITES AT THE DATABASE. Only run it against an
# instance you're happy to wipe. Reset afterwards with:
#
#   mysql -e "DROP DATABASE bcoem_gatlin;
#             CREATE DATABASE bcoem_gatlin
#             CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
#   # then re-run the setup wizard at https://gatlin.../setup.php
#
# TARGET PREREQUISITES (one-time, in the gatlin admin panel):
#   - Registration window must be OPEN
#   - CAPTCHA must be DISABLED (Preferences), or every POST fails
#
# Usage:
#   bash bcoem-loadtest-writes.sh                          # defaults
#   bash bcoem-loadtest-writes.sh -u https://gatlin.horsemenofthehops.com -c 300 -d 120
#
#   -u  base URL            (default: https://gatlin.horsemenofthehops.com)
#   -c  concurrent users    (default: 300)
#   -d  duration, seconds   (default: 60)
#   -p  DB table prefix     (default: "" — matches a default install;
#                            set to whatever $prefix is in site/config.php)
#
# Run from a different machine than the server.
#
set -euo pipefail

BASE_URL="https://gatlin.horsemenofthehops.com"
CONCURRENCY=300
DURATION=60
DB_PREFIX=""

while getopts "u:c:d:p:" opt; do
  case $opt in
    u) BASE_URL="${OPTARG%/}" ;;
    c) CONCURRENCY="$OPTARG" ;;
    d) DURATION="$OPTARG" ;;
    p) DB_PREFIX="$OPTARG" ;;
    *) echo "Usage: $0 [-u url] [-c concurrency] [-d seconds] [-p dbprefix]"; exit 1 ;;
  esac
done

REGISTER_PAGE="${BASE_URL}/index.php?section=register&go=entrant"
# Same endpoint the real registration form posts to (sections/register.sec.php)
POST_URL="${BASE_URL}/includes/process.inc.php?action=add&dbTable=${DB_PREFIX}users&section=register&go=entrant&view=default"

RUN_ID=$(date +%s)
RESULTS_DIR=$(mktemp -d)
END_TIME=$(( $(date +%s) + DURATION ))

echo "=========================================================="
echo " BCOE&M WRITE load test  (registrations -> MariaDB)"
echo "   Target:      ${BASE_URL}"
echo "   Users:       ${CONCURRENCY} concurrent"
echo "   Duration:    ${DURATION}s"
echo "   Run ID:      ${RUN_ID} (embedded in test emails)"
echo "=========================================================="

FIRST_NAMES=(Alan Barb Carl Dana Eric Fiona Greg Hana Ivan Jill Kurt Lena Marc Nora Omar Pat Quin Rosa Stan Tina)
LAST_NAMES=(Ale Lager Stout Porter Saison Gose Helles Dunkel Tripel Kolsch Marzen Wit Bock Mead Cyser Melomel Braggot Pils Alt Barleywine)

simulate_user() {
  local id=$1
  local out="${RESULTS_DIR}/user_${id}.log"
  local jar="${RESULTS_DIR}/cookies_${id}.txt"
  local n=0
  while [[ $(date +%s) -lt $END_TIME ]]; do
    n=$((n+1))
    local email="loadtest+${RUN_ID}.u${id}.n${n}@example.com"
    local fn=${FIRST_NAMES[$(( (id + n) % ${#FIRST_NAMES[@]} ))]}
    local ln=${LAST_NAMES[$(( (id * 7 + n) % ${#LAST_NAMES[@]} ))]}

    # 1) GET the registration page — starts the PHP session, loads prefs,
    #    and renders the per-session CSRF token (user_session_token) that
    #    includes/process.inc.php requires on every POST to this endpoint.
    local reg_page="${RESULTS_DIR}/regpage_${id}.html"
    local get_line
    get_line=$(curl -s -o "$reg_page" -b "$jar" -c "$jar" --max-time 30 \
        -w "GET %{http_code} %{time_total}" "$REGISTER_PAGE" 2>/dev/null) || true
    echo "${get_line:-GET 000 30.0}" >> "$out"

    local csrf_token
    csrf_token=$(grep -oE 'name="user_session_token" value ="[a-f0-9]{64}"' "$reg_page" 2>/dev/null | grep -oE '[a-f0-9]{64}') || true

    [[ $(date +%s) -ge $END_TIME ]] && break

    if [[ -z "$csrf_token" ]]; then
      # Couldn't scrape a token (page layout changed, or GET failed) — the
      # POST would just bounce off the CSRF check in process.inc.php, so
      # record it distinctly instead of sending a request we know will fail.
      echo "POST 000 0.0 no-csrf-token" >> "$out"
    else
    # 2) POST the full registration (account + demographic form)
    local post_line
    post_line=$(curl -s -o /dev/null -b "$jar" -c "$jar" --max-time 30 \
        -w "POST %{http_code} %{time_total} %{redirect_url}" \
        -e "$REGISTER_PAGE" \
        --data-urlencode "user_session_token=${csrf_token}" \
        --data-urlencode "user_name=${email}" \
        --data-urlencode "user_name2=${email}" \
        --data-urlencode "password=LoadTest123!" \
        --data-urlencode "userLevel=2" \
        --data-urlencode "userQuestion=1" \
        --data-urlencode "userQuestionAnswer=hops" \
        --data-urlencode "brewerFirstName=${fn}" \
        --data-urlencode "brewerLastName=${ln}" \
        --data-urlencode "brewerAddress=123 Fermentation Way" \
        --data-urlencode "brewerCity=Anytown" \
        --data-urlencode "brewerStateUS=TX" \
        --data-urlencode "brewerState=TX" \
        --data-urlencode "brewerZip=75001" \
        --data-urlencode "brewerCountry=United States" \
        --data-urlencode "brewerPhone1=555-0100" \
        --data-urlencode "brewerClubs=Horsemen of the Hops" \
        --data-urlencode "brewerJudge=N" \
        --data-urlencode "brewerSteward=N" \
        --data-urlencode "submit=Submit" \
        "$POST_URL" 2>/dev/null) || true
    echo "${post_line:-POST 000 30.0}" >> "$out"
    fi
    rm -f "$reg_page"

    # A successful registration logs the session in and redirects to the
    # entry list — the anonymous registration form (and its CSRF token)
    # is gone from that session after that. Reset the cookie jar so the
    # next iteration starts as a fresh anonymous visitor, same as a real
    # new entrant would.
    : > "$jar"

    # brief pause between a user's registrations (real users don't re-register,
    # but this keeps each worker from being a pure tight loop)
    sleep "$(awk -v seed=$RANDOM 'BEGIN{srand(seed); printf "%.2f", 0.3+rand()*1.2}')"
  done
}

echo "Starting ${CONCURRENCY} users..."
for i in $(seq 1 "$CONCURRENCY"); do
  simulate_user "$i" &
  (( i % 25 == 0 )) && sleep 0.2
done

while [[ $(date +%s) -lt $END_TIME ]]; do
  sleep 5
  done_reqs=$(cat "${RESULTS_DIR}"/user_*.log 2>/dev/null | wc -l)
  remaining=$(( END_TIME - $(date +%s) )); (( remaining < 0 )) && remaining=0
  echo "  ${done_reqs} requests completed, ${remaining}s remaining..."
done

wait
echo "Test finished. Crunching numbers..."

cat "${RESULTS_DIR}"/user_*.log | awk '
  {
    total++
    lat[total] = $3 + 0
    sum += $3
    if ($1 == "POST") posts++
    if ($2 >= 200 && $2 < 400) ok++
    else if ($2 == 000)        fail++
    else                       err++
    # BCOE&M redirects carry a msg= code; msg=4 means CAPTCHA rejected
    if ($1 == "POST" && $4 ~ /msg=4/)  captcha_fail++
    if ($1 == "POST" && $4 ~ /msg=2/)  dup++
  }
  END {
    if (total == 0) { print "No requests completed."; exit 1 }
    for (i = 2; i <= total; i++) {
      v = lat[i]; j = i - 1
      while (j > 0 && lat[j] > v) { lat[j+1] = lat[j]; j-- }
      lat[j+1] = v
    }
    p50 = lat[int(total*0.50)+1 > total ? total : int(total*0.50)+1]
    p95 = lat[int(total*0.95)+1 > total ? total : int(total*0.95)+1]
    p99 = lat[int(total*0.99)+1 > total ? total : int(total*0.99)+1]
    printf "\n"
    printf "==========================================================\n"
    printf " Results\n"
    printf "   Total requests:     %d  (%d registration POSTs)\n", total, posts
    printf "   Successful (2xx/3xx): %d (%.1f%%)\n", ok, ok/total*100
    printf "   HTTP errors:        %d\n", err
    printf "   Failed/timeout:     %d\n", fail
    if (captcha_fail > 0)
      printf "   CAPTCHA rejects:    %d  <-- disable CAPTCHA in admin prefs!\n", captcha_fail
    if (dup > 0)
      printf "   Duplicate emails:   %d\n", dup
    printf "   Avg latency:        %.3fs\n", sum/total
    printf "   p50 latency:        %.3fs\n", p50
    printf "   p95 latency:        %.3fs\n", p95
    printf "   p99 latency:        %.3fs\n", p99
    printf "==========================================================\n"
    printf "\n"
    printf " Verify writes landed:\n"
    printf "   mysql bcoem_gatlin -e \"SELECT COUNT(*) FROM users\n"
    printf "     WHERE user_name LIKE '\''loadtest+%%'\'';\"\n"
    printf " Then drop/recreate the DB and re-run setup to reset.\n"
  }'

rm -rf "$RESULTS_DIR"
