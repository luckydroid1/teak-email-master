#!/usr/bin/env bash
set -euo pipefail

# Script to audit and update Node MCP server dependencies safely
MCP_DIR="/opt/codeinbox/mcp-server"

echo "=== Updating MCP Server Dependencies in $MCP_DIR ==="
if [ -d "$MCP_DIR" ]; then
    cd "$MCP_DIR"
    npm update @modelcontextprotocol/sdk express zod
    npm audit fix --omit=dev || true
    chown -R www-data:www-data "$MCP_DIR"
    echo "MCP packages updated successfully."
else
    echo "Directory $MCP_DIR not found. Skipping."
fi
