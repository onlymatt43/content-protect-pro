#!/usr/bin/env bash
# E2E test via admin-ajax fallback when REST endpoints are blocked by WAF/CDN
# Usage:
#   SITE=https://example.com VIDEO_ID=123 CODE=VIP2024 ./tools/e2e_playback_test_ajax.sh
# Steps:
#  - fetch homepage HTML to extract localized nonce (cpp_public_ajax.nonce)
#  - POST to admin-ajax.php?action=cpp_validate_giftcode to redeem code
#  - POST to admin-ajax.php?action=cpp_get_video_token to request playback/embed

set -euo pipefail

SITE=${SITE:-}
VIDEO_ID=${VIDEO_ID:-}
CODE=${CODE:-}
COOKIEJAR="/tmp/cpp_e2e_ajax_cookies.txt"
TMPHTML="/tmp/cpp_homepage.html"

if [[ -z "$SITE" || -z "$VIDEO_ID" || -z "$CODE" ]]; then
  echo "Missing required env vars. Example:" >&2
  echo "  SITE=https://example.com VIDEO_ID=123 CODE=VIP2024 $0" >&2
  exit 2
fi

echo "Using SITE=$SITE" 
echo "VIDEO_ID=$VIDEO_ID" 

rm -f "$COOKIEJAR" "$TMPHTML"

UA="Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117 Safari/537.36"

# 1) fetch homepage HTML
curl -s -L -A "$UA" -o "$TMPHTML" "$SITE/" || true

# 2) extract cpp_public_ajax nonce using python
NONCE=$(python3 - "$TMPHTML" <<'PY'
import re,sys,json
html=open(sys.argv[1]).read()
# try to find var cpp_public_ajax = {...}; or window.cpp_public_ajax = {...};
m=re.search(r"(?:var|window\.)\s*cpp_public_ajax\s*=\s*(\{.*?\})\s*;", html, re.S)
if not m:
    # try locating wp_localize_script inline: cpp_public_ajax: { ... }
    m=re.search(r"cpp_public_ajax\s*[:=]\s*(\{.*?\})", html, re.S)
if m:
    try:
        obj=json.loads(m.group(1))
        print(obj.get('nonce',''))
    except Exception:
        print('')
else:
    print('')
PY
)

if [[ -z "$NONCE" ]]; then
  echo "Could not extract nonce from homepage. The page may be heavily minified or the localization variable not present."
  echo "Open $SITE in a browser and check for 'cpp_public_ajax' JS variable (or run the script from a browser session)."
  exit 3
fi

echo "Extracted nonce: $NONCE"

AJAX_URL="$SITE/wp-admin/admin-ajax.php"

# 3) Redeem code via admin-ajax
echo "\n1) Redeem code ($CODE) via admin-ajax -> $AJAX_URL"
REDEEM_RESP=$(curl -s -w "\n%{http_code}" -c "$COOKIEJAR" -A "$UA" -e "$SITE/" -d "action=cpp_validate_giftcode&nonce=$NONCE&code=$CODE" "$AJAX_URL") || true
REDEEM_BODY=$(echo "$REDEEM_RESP" | sed '$d')
REDEEM_CODE=$(echo "$REDEEM_RESP" | tail -n1)

echo "Redeem HTTP status: $REDEEM_CODE"
echo "Redeem response body:\n$REDEEM_BODY"

# 4) Request video token via admin-ajax
echo "\n2) Request video token via admin-ajax -> $AJAX_URL"
REQUEST_RESP=$(curl -s -w "\n%{http_code}" -b "$COOKIEJAR" -A "$UA" -e "$SITE/" -d "action=cpp_get_video_token&nonce=$NONCE&video_id=$VIDEO_ID" "$AJAX_URL") || true
REQUEST_BODY=$(echo "$REQUEST_RESP" | sed '$d')
REQUEST_CODE=$(echo "$REQUEST_RESP" | tail -n1)

echo "Request-playback HTTP status: $REQUEST_CODE"
echo "Request-playback response body:\n$REQUEST_BODY"

# 5) try to extract presto_embed or playback_url
PLAYBACK_URL=$(echo "$REQUEST_BODY" | python3 - <<'PY'
import sys,json
try:
    d=json.loads(sys.stdin.read())
    if isinstance(d,dict):
        # WP ajax success wrapper
        if d.get('success') and isinstance(d.get('data'), dict):
            dd=d.get('data')
            if 'playback_url' in dd:
                print(dd['playback_url']);
            elif 'presto_embed' in dd:
                print('EMBED_FOUND');
            elif 'presto_embed' in d:
                print(d['presto_embed']);
        else:
            if 'playback_url' in d:
                print(d['playback_url'])
            elif 'presto_embed' in d:
                print('EMBED_FOUND')
except Exception:
    pass
PY
)

if [[ -z "$PLAYBACK_URL" ]]; then
  echo "No playback_url found in response. If embed HTML was returned, inspect the 'presto_embed' or 'embed' fields above." 
    # Additional diagnostic: try fetching the post page for the Presto Player ID to see if the shortcode renders
    echo "\nDiagnostic: fetch the post page for video id $VIDEO_ID (without and with cookie) to look for Presto embed HTML"

    echo "\nA) Fetching page without cookie:\n"
    curl -s -L -A "$UA" -e "$SITE/" "$SITE/?p=$VIDEO_ID" | sed -n '1,200p'

    echo "\nB) Fetching page with cookie (sent cookie jar):\n"
    curl -s -L -A "$UA" -e "$SITE/" -b "$COOKIEJAR" "$SITE/?p=$VIDEO_ID" | sed -n '1,200p'

    echo "\nLooking for Presto markers in the (with-cookie) page..."
    FOUND=$(curl -s -L -A "$UA" -e "$SITE/" -b "$COOKIEJAR" "$SITE/?p=$VIDEO_ID" | grep -E -i 'presto_player|presto-player|presto_embed|data-presto' || true)
    if [[ -n "$FOUND" ]]; then
        echo "Presto embed markers found (snippet):"
        echo "$FOUND" | sed -n '1,5p'
    else
        echo "No Presto embed markers found on the page with cookie. This suggests the shortcode did not render in that context or the protected video DB entry lacks presto_player_id/direct_url."
    fi

    exit 0
fi

if [[ "$PLAYBACK_URL" == "EMBED_FOUND" ]]; then
  echo "Embed HTML returned in response (presto_embed). Open page and verify player loads in browser." 
  exit 0
fi

# 6) Probe playback URL
echo "\n3) Probe playback_url: $PLAYBACK_URL"
curl -I -s -S "$PLAYBACK_URL" || true

echo "\nCookie jar contents (may include cpp_playback_token):"
cat "$COOKIEJAR" || true

echo "\nDone. Paste the outputs if you want further debugging."

exit 0
