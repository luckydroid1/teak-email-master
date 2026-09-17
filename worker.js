/**
 * Teak Email — Cloudflare Worker Reverse Proxy
 * Proxies all requests transparently to the VPS origin backend.
 */

const ORIGIN = "http://94.100.26.189";

export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    const targetUrl = new URL(url.pathname + url.search, ORIGIN);

    const headers = new Headers(request.headers);
    headers.set("Host", "teak.email");
    headers.set("X-Forwarded-Host", url.host);
    headers.set("X-Forwarded-Proto", url.protocol.replace(":", ""));
    const clientIp = request.headers.get("CF-Connecting-IP") || request.headers.get("X-Forwarded-For");
    if (clientIp) {
      headers.set("X-Forwarded-For", clientIp);
    }

    const init = {
      method: request.method,
      headers: headers,
      redirect: "manual"
    };

    if (request.method !== "GET" && request.method !== "HEAD") {
      init.body = request.body;
      init.duplex = "half";
    }

    try {
      const response = await fetch(targetUrl.toString(), init);
      return response;
    } catch (err) {
      return new Response("Backend gateway timeout: " + err.message, {
        status: 502,
        headers: { "Content-Type": "text/plain" }
      });
    }
  }
};
