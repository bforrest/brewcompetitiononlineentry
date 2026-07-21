#!/usr/bin/env bash
#
# bcoem-loadtest.sh — simulate N concurrent users browsing a BCOE&M site.
#
# Each simulated user loops through a realistic page flow (home ->
# registration page -> entry info -> style list), with a short random
# think-time between pages, until the test duration ends. Latency and
# error stats are reported at the end.
#
# SAFE BY DESIGN: read-only GET requests — it will not create accounts
# or entries, so it's safe to aim at a configured site. Run it from a
# DIFFERENT machine than the server (e.g. your laptop), otherwise you
# are benchmarking the server competing with itself.
#
# Usage:
#   bash bcoem-loadtest.sh                                # defaults
#   bash bcoem-loadtest.sh -u https://gatlin.horsemenofthehops.com -c 300 -d 120
#
#   -u  base URL           (default: https://gatlin.horsemenofthehops.com)
#   -c  concurrent users   (default: 300)
#   -d  duration, seconds  (default: 60)
#
set -euo pipefail

BASE_URL="https://gatlin.horsemenofthehops.com"
CONCURRENCY=300
DURATION=60

while getopts "u:c:d:" opt; do
  case $opt in
    u) BASE_URL="${OPTARG%/}" ;;
    c) CONCURRENCY="$OPTARG" ;;
    d) DURATION="$OPTARG" ;;
    *) echo "Usage: $0 [-u url] [-c concurrency] [-d seconds]"; exit 1 ;;
  esac
done

# Pages a real entrant hits while registering / entering.
PAGES=(
  "/"
  "/index.php?section=register"
  "/index.php?section=entry"
  "/index.php?section=styles"
  "/index.php?section=schedule"
)

RESULTS_DIR=$(mktemp -d)
END_TIME=$(( $(date +%s) + DURATION ))

echo "=========================================================="
echo " BCOE&M load test"
echo "   Target:      ${BASE_URL}"
echo "   Users:       ${CONCURRENCY} concurrent"
echo "   Duration:    ${DURATION}s"
echo "=========================================================="

# --- one simulated user: loop the page flow until time is up ---------
simulate_user() {
  local id=$1
  local out="${RESULTS_DIR}/user_${id}.log"
  # Each user keeps a cookie jar, like a real browser session.
  local jar="${RESULTS_DIR}/cookies_${id}.txt"
  while [[ $(date +%s) -lt $END_TIME ]]; do
    for page in "${PAGES[@]}"; do
      [[ $(date +%s) -ge $END_TIME ]] && break
      # time_total in seconds, http_code (curl emits "000 <t>" on failure)
      line=$(curl -s -o /dev/null \
           -b "$jar" -c "$jar" \
           --max-time 30 \
           -w "%{http_code} %{time_total}" \
           "${BASE_URL}${page}" 2>/dev/null) || true
      echo "${line:-000 30.0}" >> "$out"
      # think time: 0.5–2.5s, like a human reading the page
      sleep "$(awk -v seed=$RANDOM 'BEGIN{srand(seed); printf "%.2f", 0.5+rand()*2}')"
    done
  done
}

# --- launch all users -------------------------------------------------
echo "Starting ${CONCURRENCY} users..."
for i in $(seq 1 "$CONCURRENCY"); do
  simulate_user "$i" &
  # stagger startup slightly so all users don't fire at the same ms
  (( i % 25 == 0 )) && sleep 0.2
done

# --- progress ticker while waiting ------------------------------------
while [[ $(date +%s) -lt $END_TIME ]]; do
  sleep 5
  done_reqs=$(cat "${RESULTS_DIR}"/user_*.log 2>/dev/null | wc -l)
  remaining=$(( END_TIME - $(date +%s) )); (( remaining < 0 )) && remaining=0
  echo "  ${done_reqs} requests completed, ${remaining}s remaining..."
done

wait
echo "Test finished. Crunching numbers..."

# --- report ------------------------------------------------------------
cat "${RESULTS_DIR}"/user_*.log | awk '
  {
    total++
    lat[total] = $2 + 0
    sum += $2
    if ($1 >= 200 && $1 < 400) ok++
    else if ($1 == 000)        timeout++
    else                       err++
  }
  END {
    if (total == 0) { print "No requests completed."; exit 1 }
    # sort latencies (simple insertion sort is fine at this scale)
    for (i = 2; i <= total; i++) {
      v = lat[i]; j = i - 1
      while (j > 0 && lat[j] > v) { lat[j+1] = lat[j]; j-- }
      lat[j+1] = v
    }
    p50 = lat[int(total*0.50)+ (total*0.50==int(total*0.50)?0:1)]
    p95 = lat[int(total*0.95)+ (total*0.95==int(total*0.95)?0:1)]
    p99 = lat[int(total*0.99)+ (total*0.99==int(total*0.99)?0:1)]
    printf "\n"
    printf "==========================================================\n"
    printf " Results\n"
    printf "   Total requests:   %d\n", total
    printf "   Successful:       %d (%.1f%%)\n", ok, ok/total*100
    printf "   HTTP errors:      %d\n", err
    printf "   Timeouts/failed:  %d\n", timeout
    printf "   Avg latency:      %.3fs\n", sum/total
    printf "   p50 latency:      %.3fs\n", p50
    printf "   p95 latency:      %.3fs\n", p95
    printf "   p99 latency:      %.3fs\n", p99
    printf "==========================================================\n"
    printf "\n"
    printf " Rule of thumb: p95 under ~2s with <1%% errors means the\n"
    printf " server will feel fine during a registration rush.\n"
  }'

rm -rf "$RESULTS_DIR"
