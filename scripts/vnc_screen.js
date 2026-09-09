#!/usr/bin/env node
/**
 * vnc_screen.js - RFB 3.8 client that authenticates and dumps the current
 * framebuffer to a PNG (built-ins only: net, crypto, zlib).
 *
 * Strategy: after ServerInit do NOT negotiate anything. QEMU pushes a full
 * raw framebuffer update right after ClientInit. Parse the message stream,
 * paint rects into a canvas, re-request incrementally until fully covered,
 * then encode PNG.
 *
 * Usage: node vnc_screen.js <outfile.png>
 * Env: VNC_HOST, VNC_PORT, VNC_PASSWORD
 */
const net = require('net');
const crypto = require('crypto');
const zlib = require('zlib');
const fs = require('fs');

const HOST = process.env.VNC_HOST || '107.155.75.218';
const PORT = parseInt(process.env.VNC_PORT || '9579', 10);
const VNC_PASSWORD = process.env.VNC_PASSWORD || '';
const OUT = process.argv[2] || '.ops/screen.png';

if (!VNC_PASSWORD) { console.error('[FATAL] set VNC_PASSWORD'); process.exit(1); }
const log = (...a) => console.log(new Date().toISOString(), ...a);

function vncChallengeResponse(password, challenge) {
  const pw = Buffer.from(String(password), 'latin1').subarray(0, 8);
  const padded = Buffer.alloc(8, 0); pw.copy(padded, 0, 0, Math.min(pw.length, 8));
  const key8 = Buffer.alloc(8);
  for (let i = 0; i < 8; i++) {
    let b = padded[i], r = 0;
    for (let bit = 0; bit < 8; bit++) if (b & (1 << bit)) r |= 1 << (7 - bit);
    key8[i] = r;
  }
  const c = crypto.createCipheriv('des-ede3', Buffer.concat([key8, key8, key8]), null);
  c.setAutoPadding(false);
  return Buffer.concat([c.update(challenge), c.final()]).subarray(0, 16);
}

function makeReader(sock) {
  let buf = Buffer.alloc(0);
  let wake = null;
  let closed = false;
  sock.on('data', (d) => { buf = Buffer.concat([buf, d]); if (wake) { const w = wake; wake = null; w(); } });
  const done = () => { closed = true; if (wake) { const w = wake; wake = null; w(); } };
  sock.on('close', done); sock.on('error', done);
  return {
    async read(n, timeoutMs, label) {
      if (n === 0) return Buffer.alloc(0);
      const start = Date.now();
      while (buf.length < n) {
        if (closed || sock.destroyed) throw new Error('socket-closed:' + label);
        const rem = timeoutMs - (Date.now() - start);
        if (rem <= 0) throw new Error('timeout:' + label + ' have=' + buf.length + ' want=' + n);
        await new Promise((res) => { wake = res; setTimeout(res, Math.min(rem, 150)); });
      }
      const out = buf.subarray(0, n); buf = buf.subarray(n);
      return Buffer.from(out);
    },
    get buffered() { return buf.length; },
  };
}

// ---- minimal PNG encoder (RGB8) ----
const CRC_TABLE = (() => {
  const t = new Uint32Array(256);
  for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; }
  return t;
})();
function crc32(buf) {
  let c = 0xffffffff;
  for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}
function chunk(type, data) {
  const len = Buffer.alloc(4); len.writeUInt32BE(data.length);
  const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
  const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(body));
  return Buffer.concat([len, body, crc]);
}
function encodePng(width, height, rgb) {
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(width, 0); ihdr.writeUInt32BE(height, 4);
  ihdr[8] = 8; ihdr[9] = 2;
  const stride = width * 3;
  const raw = Buffer.alloc((stride + 1) * height);
  for (let y = 0; y < height; y++) rgb.copy(raw, y * (stride + 1) + 1, y * stride, (y + 1) * stride);
  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', ihdr),
    chunk('IDAT', zlib.deflateSync(raw, { level: 6 })),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}

(async () => {
  const sock = net.createConnection({ host: HOST, port: PORT });
  const r = makeReader(sock);

  const ver = await r.read(12, 15000, 'ver'); log('server:', JSON.stringify(ver.toString('latin1')));
  sock.write('RFB 003.008\n');
  const cnt = (await r.read(1, 15000, 'cnt'))[0];
  const types = cnt ? await r.read(cnt, 15000, 'types') : Buffer.alloc(0);
  const sec = [...types].includes(2) ? 2 : 1;
  sock.write(Buffer.from([sec]));
  if (sec === 2) {
    const ch = await r.read(16, 15000, 'chal');
    sock.write(vncChallengeResponse(VNC_PASSWORD, ch));
    const res = await r.read(4, 15000, 'result');
    if (res.readUInt32BE(0) !== 0) throw new Error('auth failed');
    log('VNC auth OK');
  }

  sock.write(Buffer.from([1])); // ClientInit shared
  const head = await r.read(24, 15000, 'serverinit');
  const W = head.readUInt16BE(0), H = head.readUInt16BE(2);
  const nameLen = head.readUInt32BE(20);
  const name = nameLen ? (await r.read(nameLen, 15000, 'name')).toString('latin1') : '';
  log(`ServerInit ${W}x${H} "${name}" bpp=${head[4]} depth=${head[5]} be=${head[6]} tc=${head[7]}`);

  // Use the SERVER's declared pixel format (no SetPixelFormat). Default QEMU:
  // 32bpp little-endian truecolour -> wire bytes per pixel: B,G,R,pad.
  const fb = Buffer.alloc(W * H * 4); // BGRX canvas, zero-filled
  const covered = Buffer.alloc(W * H); // per-paint coverage map
  let coveredCount = 0;

  function sendIncremental() {
    const req = Buffer.alloc(10); req[0] = 3; req[1] = 1;
    req.writeUInt16BE(0, 2); req.writeUInt16BE(0, 4); req.writeUInt16BE(W, 6); req.writeUInt16BE(H, 8);
    try { sock.write(req); } catch (_) {}
  }
  function sendFull() {
    const req = Buffer.alloc(10); req[0] = 3; req[1] = 0;
    req.writeUInt16BE(0, 2); req.writeUInt16BE(0, 4); req.writeUInt16BE(W, 6); req.writeUInt16BE(H, 8);
    try { sock.write(req); } catch (_) {}
  }

  const deadline = Date.now() + 45000;
  let nextReqAt = Date.now() + 5000;
  let rectCount = 0;
  let fullReqs = 0;

  // First: one explicit NON-incremental request (server does not push spontaneously)
  sendFull(); fullReqs++;
  log('sent full FB request #' + fullReqs);

  while (Date.now() < deadline && coveredCount < W * H) {
    if (Date.now() > nextReqAt && coveredCount === 0 && fullReqs < 5) {
      sendFull(); fullReqs++;
      log('re-sent full FB request #' + fullReqs);
      nextReqAt = Date.now() + 6000;
    }
    let mt;
    try { mt = (await r.read(1, Math.max(500, deadline - Date.now()), 'msgtype'))[0]; }
    catch (e) { log('read ended:', e.message); break; }
    if (mt === 0) { // FramebufferUpdate
      await r.read(1, 10000, 'pad');
      const nRects = (await r.read(2, 10000, 'nrects')).readUInt16BE(0);
      for (let i = 0; i < nRects; i++) {
        const rh = await r.read(12, 10000, 'recthdr');
        const rx = rh.readUInt16BE(0), ry = rh.readUInt16BE(2), rw = rh.readUInt16BE(4), rhh = rh.readUInt16BE(6);
        const encd = rh.readInt32BE(8);
        if (encd !== 0) { log('WARN non-raw encoding', encd, '- aborting capture'); i = nRects; continue; }
        const px = rw * rhh * 4;
        const data = await r.read(px, 60000, 'pixdata');
        for (let yy = 0; yy < rhh; yy++) {
          const dstOff = ((ry + yy) * W + rx) * 4;
          data.copy(fb, dstOff, yy * rw * 4, yy * rw * 4 + rw * 4);
          const covOff = (ry + yy) * W + rx;
          for (let xx = 0; xx < rw; xx++) {
            const idx = covOff + xx;
            if (!covered[idx]) { covered[idx] = 1; coveredCount++; }
          }
        }
        rectCount++;
      }
      log(`update: ${nRects} rects, coverage ${(100 * coveredCount / (W * H)).toFixed(1)}%`);
    } else if (mt === 1) { // SetColourMapEntries
      await r.read(5, 10000, 'cm-pad');
      const n = (await r.read(2, 10000, 'cm-n')).readUInt16BE(0);
      await r.read(n * 6, 10000, 'cm-colors');
      log('SetColourMapEntries skipped:', n);
    } else if (mt === 2) { // Bell
      log('Bell');
    } else if (mt === 3) { // ServerCutText
      await r.read(3, 10000, 'ct-pad');
      const len = (await r.read(4, 10000, 'ct-len')).readInt32BE(0);
      if (len > 0) await r.read(len, 10000, 'ct-text');
      log('ServerCutText len', len);
    } else {
      log('unknown message type', mt, '- stopping');
      break;
    }
  }

  log('total raw rects captured:', rectCount, 'coverage:', (100 * coveredCount / (W * H)).toFixed(1) + '%');
  if (coveredCount === 0) { console.error('[FATAL] no framebuffer data received'); try { sock.destroy(); } catch (_) {} process.exit(4); }

  const rgb = Buffer.alloc(W * H * 3);
  for (let p = 0, q = 0; p < W * H * 4; p += 4, q += 3) {
    rgb[q] = fb[p + 2]; rgb[q + 1] = fb[p + 1]; rgb[q + 2] = fb[p];
  }
  fs.writeFileSync(OUT, encodePng(W, H, rgb));
  log('wrote', OUT, fs.statSync(OUT).size, 'bytes');
  try { sock.end(); setTimeout(() => sock.destroy(), 500); } catch (_) {}
  process.exit(0);
})().catch((e) => { console.error('[FATAL]', e.message); process.exit(3); });
