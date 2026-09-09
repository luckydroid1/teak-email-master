#!/usr/bin/env node
/**
 * CodeInbox MCP Server
 *
 * Transport: Streamable HTTP (POST /mcp)
 * Auth: Bearer token = CodeInbox API key (dari env CODEINBOX_API_KEY)
 *
 * Tools:
 *   list_inboxes        → GET /api/inboxes
 *   create_inbox        → POST /api/inboxes {domain, local_part}
 *   list_emails         → GET /api/inboxes/{email}/emails
 *   read_email          → GET /api/inboxes/{email}/emails/{uid}
 *   get_otp             → GET /api/inboxes/{email}/otp/{uid}
 *   get_balance         → GET /api/balance
 *   list_domains        → GET /api/domains
 */
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { z } from "zod";
import express from "express";

const API_URL = process.env.CODEINBOX_API_URL || "https://inbox.pesat.ai/api";
const API_KEY = process.env.CODEINBOX_API_KEY || "";

// ---- API helper -------------------------------------------------
async function callApi(path, { method = "GET", body } = {}) {
  const headers = { "Content-Type": "application/json" };
  if (API_KEY) headers.Authorization = `Bearer ${API_KEY}`;
  const res = await fetch(`${API_URL}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
  return data;
}

// ---- MCP Server builder (per-request, stateless) ---------------
function buildServer() {
  const server = new McpServer({ name: "codeinbox", version: "0.1.0" });

  server.registerTool("list_inboxes", {
    description: "List all inboxes owned by the current user.",
    inputSchema: {},
  }, async () => {
    const data = await callApi("/inboxes");
    return { content: [{ type: "text", text: JSON.stringify(data.inboxes, null, 2) }] };
  });

  server.registerTool("create_inbox", {
    description: "Create a new inbox. Requires a domain from the pool and a local part (e.g. agent.2026). Returns email + password.",
    inputSchema: {
      domain: z.string().describe("Domain from the pool, e.g. jetdigitalpro.com"),
      local_part: z.string().describe("Local part, lowercase letters/numbers/dots/hyphens"),
    },
  }, async ({ domain, local_part }) => {
    const data = await callApi("/inboxes", { method: "POST", body: { domain, local_part } });
    return { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] };
  });

  server.registerTool("list_emails", {
    description: "List emails in a specific inbox. Returns array of {uid, from, subject, date}.",
    inputSchema: {
      email: z.string().describe("Full inbox address, e.g. agent.2026@jetdigitalpro.com"),
    },
  }, async ({ email }) => {
    const data = await callApi(`/inboxes/${encodeURIComponent(email)}/emails`);
    return { content: [{ type: "text", text: JSON.stringify(data.emails, null, 2) }] };
  });

  server.registerTool("read_email", {
    description: "Read the full content of an email by UID.",
    inputSchema: {
      email: z.string().describe("Full inbox address"),
      uid: z.number().describe("Email UID from list_emails"),
    },
  }, async ({ email, uid }) => {
    const data = await callApi(`/inboxes/${encodeURIComponent(email)}/emails/${uid}`);
    return { content: [{ type: "text", text: data.raw || "" }] };
  });

  server.registerTool("get_otp", {
    description: "Extract the one-time password/code from a specific email.",
    inputSchema: {
      email: z.string().describe("Full inbox address"),
      uid: z.number().describe("Email UID from list_emails"),
    },
  }, async ({ email, uid }) => {
    const data = await callApi(`/inboxes/${encodeURIComponent(email)}/otp/${uid}`);
    return {
      content: [{ type: "text", text: JSON.stringify({ otp: data.otp, email: data.email, uid: data.uid }, null, 2) }],
    };
  });

  server.registerTool("get_balance", {
    description: "Get current credit balance and tier.",
    inputSchema: {},
  }, async () => {
    const data = await callApi("/balance");
    return { content: [{ type: "text", text: JSON.stringify(data, null, 2) }] };
  });

  server.registerTool("list_domains", {
    description: "List available domains for creating inboxes.",
    inputSchema: {},
  }, async () => {
    const data = await callApi("/domains");
    return { content: [{ type: "text", text: JSON.stringify(data.domains, null, 2) }] };
  });

  return server;
}

// ---- HTTP transport --------------------------------------------
const app = express();
app.use(express.json());

app.post("/mcp", async (req, res) => {
  try {
    const transport = new StreamableHTTPServerTransport({
      sessionIdGenerator: undefined, // stateless per request
    });
    const server = buildServer();
    await server.connect(transport);
    await transport.handleRequest(req, res, req.body);
  } catch (err) {
    if (!res.headersSent) {
      res.status(500).json({ error: String(err && err.message || err) });
    }
  }
});

app.get("/healthz", (_req, res) => res.json({ ok: true }));

const PORT = Number(process.env.PORT || 3456);
app.listen(PORT, () => {
  console.log(`[codeinbox-mcp] listening on :${PORT}`);
  if (!API_KEY) console.warn("[codeinbox-mcp] WARNING: CODEINBOX_API_KEY not set — set via env");
});
