#!/usr/bin/env bash
# bneo.sh — BrowserOS Neo MCP helper with PERSISTENT session
# Usage:
#   bneo.sh init                 -> fresh session (drops page ownership)
#   bneo.sh call <tool> <args-json-file> [timeout]
set -euo pipefail
CMD="${1:-call}"
TMP="${TEMP:-/tmp}"
SID_FILE="$TMP/bneo_sid.txt"
HFILE="$TMP/bneo_h.txt"

init_session() {
  > "$HFILE"
  curl -s -D "$HFILE" -X POST http://127.0.0.1:9010/mcp \
    -H "Content-Type: application/json" \
    -H "Accept: application/json, text/event-stream" \
    -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2024-11-05","capabilities":{},"clientInfo":{"name":"zcode","version":"1.0"}}}' \
    --max-time 10 -o /dev/null
  SID=$(grep -oE '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}' "$HFILE" | head -1)
  if [ -z "$SID" ]; then echo "NO_SESSION" >&2; exit 1; fi
  echo "$SID" > "$SID_FILE"
}

case "$CMD" in
  init)
    init_session
    echo "SESSION=$(cat "$SID_FILE")"
    ;;
  call)
    TOOL="$2"; ARGS_FILE="$3"; TIMEOUT="${4:-25}"
    if [ ! -s "$SID_FILE" ]; then init_session; fi
    SID=$(cat "$SID_FILE")
    python - "$TOOL" "$ARGS_FILE" > "$TMP/bneo_payload.json" << 'PYEOF'
import json, sys
tool, args_file = sys.argv[1], sys.argv[2]
with open(args_file) as f:
    args = json.load(f)
print(json.dumps({"jsonrpc": "2.0", "id": 2, "method": "tools/call",
                  "params": {"name": tool, "arguments": args}}))
PYEOF
    RESP=$(curl -s -X POST http://127.0.0.1:9010/mcp \
      -H "Content-Type: application/json" \
      -H "Accept: application/json, text/event-stream" \
      -H "mcp-session-id: $SID" \
      -d @"$TMP/bneo_payload.json" \
      --max-time "$TIMEOUT")
    if echo "$RESP" | grep -q "Session not found"; then
      echo "REINIT" >&2
      init_session
      SID=$(cat "$SID_FILE")
      RESP=$(curl -s -X POST http://127.0.0.1:9010/mcp \
        -H "Content-Type: application/json" \
        -H "Accept: application/json, text/event-stream" \
        -H "mcp-session-id: $SID" \
        -d @"$TMP/bneo_payload.json" \
        --max-time "$TIMEOUT")
    fi
    echo "$RESP"
    ;;
  *)
    echo "usage: bneo.sh init | call <tool> <args-json> [timeout]" >&2
    exit 1
    ;;
esac
