#!/usr/bin/env bash
# E2E test script for Content Protect Pro playback flow
# Usage:
#   SITE=https://example.com VIDEO_ID=123 CODE=VIP2024 ./tools/e2e_playback_test.sh
# The script will:
#  - call the redeem REST endpoint to validate a code and capture cookies
#  - call the request-playback REST endpoint to get a playback_url or embed
#  - HEAD the playback_url to verify CDN / stub response

set -euo pipefail

SITE=${SITE:-}
VIDEO_ID=${VIDEO_ID:-}
CODE=${CODE:-}
COOKIEJAR="/tmp/cpp_e2e_cookies.txt"

if [[ -z "$SITE" || -z "$VIDEO_ID" || -z "$CODE" ]]; then
  echo "Missing required env vars. Example:" >&2
  echo "  SITE=https://example.com VIDEO_ID=123 CODE=VIP2024 $0" >&2
  exit 2
fi

echo "Using SITE=$SITE" 
echo "VIDEO_ID=$VIDEO_ID" 

# Cleanup previous cookie jar
rm -f "$COOKIEJAR"

REDEEM_URL="$SITE/wp-json/smartvideo/v1/redeem"
REQUEST_URL="$SITE/wp-json/smartvideo/v1/request-playback"

echo "1) Redeem code ($CODE) -> $REDEEM_URL"
REDEEM_RESP=$(curl -s -w "\n%{http_code}" -c "$COOKIEJAR" -H "Content-Type: application/json" -d "{\"code\": \"$CODE\"}" "$REDEEM_URL") || true
REDEEM_BODY=$(echo "$REDEEM_RESP" | sed '$d')
REDEEM_CODE=$(echo "$REDEEM_RESP" | tail -n1)

echo "Redeem HTTP status: $REDEEM_CODE"
echo "Redeem response body:\n$REDEEM_BODY"

# Show cookie token if present (not visible if HttpOnly) - we can inspect cookiejar
if [[ -f "$COOKIEJAR" ]]; then
  echo "Cookie jar contents:" 
  cat "$COOKIEJAR"
fi

echo "\n2) Request playback for video $VIDEO_ID -> $REQUEST_URL"
REQUEST_RESP=$(curl -s -w "\n%{http_code}" -b "$COOKIEJAR" -H "Content-Type: application/json" -d "{\"video_id\": \"$VIDEO_ID\"}" "$REQUEST_URL") || true
REQUEST_BODY=$(echo "$REQUEST_RESP" | sed '$d')
REQUEST_CODE=$(echo "$REQUEST_RESP" | tail -n1)

echo "Request-playback HTTP status: $REQUEST_CODE"
echo "Request-playback response body:\n$REQUEST_BODY"

# Try to extract playback_url
PLAYBACK_URL=$(echo "$REQUEST_BODY" | python3 -c "import sys, json
try:
    d=json.load(sys.stdin)
    if isinstance(d, dict) and 'playback_url' in d:
        print(d['playback_url'])
    elif isinstance(d, dict) and 'data' in d and isinstance(d['data'], dict) and 'playback_url' in d['data']:
        print(d['data']['playback_url'])
except Exception:
    pass
" 2>/dev/null || true)

if [[ -z "$PLAYBACK_URL" ]]; then
  echo "No playback_url found in response. If embed HTML was returned, inspect the 'presto_embed' or 'embed' fields." 
  exit 0
fi

echo "\n3) Probe playback_url: $PLAYBACK_URL"
echo "HEAD response:" 
curl -I -s -S "$PLAYBACK_URL" || true

echo "\nE2E test finished. Check the outputs above for HTTP statuses, JSON bodies and cookie jar." 
exit 0
