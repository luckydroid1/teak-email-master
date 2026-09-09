#!/usr/bin/env node
/**
 * vnc_login.js - Login at the VNC console (root + password), install SSH key,
 * then read back a thin screen strip to VERIFY the result visually.
 * Avoids the proxy bandwidth cap: only tiny incremental FB requests.
 *
 * Env: VNC_HOST, VNC_PORT, VNC_PASSWORD, ROOT_PW, PUBKEY
 * Args: outfile.png
 */
const net = require('net');
const crypto = require('crypto');
const zlib = require('zlib');
const fs = require('fs');

const HOST = process.env.VNC_HOST || '107.155.75.218';
const PORT = parseInt(process.env.VNC_PORT || '9579', 10);
const VNC_PASSWORD = process.env.VNC_PASSWORD || '';
const ROOT_PW = process.env.ROOT_PW || '';
const PUBKEY = process.env.PUBKEY || '';
const OUT = process.argv[2] || '.ops/login_result.png';

if (!VNC_PASSWORD || !ROOT_PW || !PUBKEY) { console.error('[FATAL] need VNC_PASSWORD, ROOT_PW, PUBKEY env'); process.exit(1); }
const log = (...a) => console.log(new Date().toISOString(), ...a);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function vncChallengeResponse(password, challenge) {
  const pw = Buffer.from(String(password), 'latin1').subarray(0, 8);
  const padded = Buffer.alloc(8, 0); pw.copy(padded, 0, 0, Math.min(pw.length, 8));
  const key8 = Buffer.alloc(8);
  for (let i = 0; i < 8; i++) { let b = padded[i], r = 0; for (let bit = 0; bit < 8; bit++) if (b & (1 << bit)) r |= 1 << (7 - bit); key8[i] = r; }
  const c = crypto.createCipheriv('des-ede3', Buffer.concat([key8, key8, key8]), null);
  c.setAutoPadding(false);
  return Buffer.concat([c.update(challenge), c.final()]).subarray(0, 16);
}

function makeReader(sock) {
  let buf = Buffer.alloc(0); let wake = null; let closed = false;
  sock.on('data', (d) => { buf = Buffer.concat([buf, d]); if (wake) { const w = wake; wake = null; w(); } });
  const done = () => { closed = true; if (wake) { const w = wake; wake = null; w(); } };
  sock.on('close', done); sock.on('error', done);
  return { async read(n, timeoutMs, label) {
    if (n === 0) return Buffer.alloc(0);
    const start = Date.now();
    while (buf.length < n) {
      if (closed || sock.destroyed) throw new Error('socket-closed:' + label);
      const rem = timeoutMs - (Date.now() - start);
      if (rem <= 0) throw new Error('timeout:' + label);
      await new Promise((res) => { wake = res; setTimeout(res, Math.min(rem, 100)); });
    }
    const out = buf.subarray(0, n); buf = buf.subarray(n);
    return Buffer.from(out);
  }};
}

const CRC_TABLE = (() => { const t = new Uint32Array(256); for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } return t; })();
function crc32(b) { let c = 0xffffffff; for (let i = 0; i < b.length; i++) c = CRC_TABLE[(c ^ b[i]) & 0xff] ^ (c >>> 8); return (c ^ 0xffffffff) >>> 0; }
function chunk(type, data) { const len = Buffer.alloc(4); len.writeUInt32BE(data.length); const body = Buffer.concat([Buffer.from(type, 'ascii'), data]); const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(body)); return Buffer.concat([len, body, crc]); }
function encodePng(w, h, rgb) {
  const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(w, 0); ihdr.writeUInt32BE(h, 4); ihdr[8] = 8; ihdr[9] = 2;
  const stride = w * 3; const raw = Buffer.alloc((stride + 1) * h);
  for (let y = 0; y < h; y++) rgb.copy(raw, y * (stride + 1) + 1, y * stride, (y + 1) * stride);
  return Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), chunk('IHDR', ihdr), chunk('IDAT', zlib.deflateSync(raw)), chunk('IEND', Buffer.alloc(0))]);
}

const SPECIAL = { '\n': 0xff0d, '\r': 0xff0d, '\t': 0xff09, ' ': 0x0020 };
function keysymFor(ch) { if (ch in SPECIAL) return SPECIAL[ch]; const c = ch.charCodeAt(0); return c >= 32 && c <= 126 ? c : null; }
async function typeString(sock, text, delayMs = 55) {
  for (const ch of text) {
    const ks = keysymFor(ch); if (ks === null) continue;
    const k = Buffer.alloc(4); k.writeUInt32BE(ks, 0);
    sock.write(Buffer.concat([Buffer.from([4, 1, 0]), k])); await sleep(20);
    sock.write(Buffer.concat([Buffer.from([4, 0, 0]), k])); await sleep(delayMs);
  }
}

(async () => {
  const sock = net.createConnection({ host: HOST, port: PORT });
  const r = makeReader(sock);
  const ver = await r.read(12, 15000, 'ver');
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
  sock.write(Buffer.from([1]));
  const head = await r.read(24, 15000, 'serverinit');
  const W = head.readUInt16BE(0), H = head.readUInt16BE(2);
  const nameLen = head.readUInt32BE(20);
  if (nameLen) await r.read(nameLen, 15000, 'name');
  log(`ServerInit ${W}x${H}`);

  // STEP 1: wake + login
  await typeString(sock, '\n'); await sleep(1500);
  log('typing username root');
  await typeString(sock, 'root\n'); await sleep(2500);
  log('typing password');
  await typeString(sock, ROOT_PW + '\n', 70); await sleep(5000);

  // STEP 2: install SSH key (idempotent, harmless if login failed)
  log('typing authorized_keys command');
  const cmd = `mkdir -p /root/.ssh && echo '${PUBKEY}' >> /root/.ssh/authorized_keys && chmod 700 /root/.ssh && chmod 600 /root/.ssh/authorized_keys\n`;
  await typeString(sock, cmd, 45);
  await sleep(4000);

  // STEP 3: read a THIN strip (top 170 rows) to verify visually — small payload
  const req = Buffer.alloc(10);
  req[0] = 3; req[1] = 0; // non-incremental, region only
  req.writeUInt16BE(0, 2); req.writeUInt16BE(0, 4);
  req.writeUInt16BE(W, 6); req.writeUInt16BE(Math.min(170, H), 8);
  sock.write(req);
  log('requested strip 1024x170');

  // parse one FramebufferUpdate
  let pngBuf = null;
  const deadline = Date.now() + 25000;
  while (Date.now() < deadline) {
    let mt;
    try { mt = (await r.read(1, 8000, 'mt'))[0]; } catch (e) { log('read ended:', e.message); break; }
    if (mt === 0) {
      await r.read(1, 10000, 'pad');
      const nRects = (await r.read(2, 10000, 'nrects')).readUInt16BE(0);
      log('update rects:', nRects);
      for (let i = 0; i < nRects; i++) {
        const rh = await r.read(12, 10000, 'recthdr');
        const rx = rh.readUInt16BE(0), ry = rh.readUInt16BE(2), rw = rh.readUInt16BE(4), rhh = rh.readUInt16BE(6);
        if (rh.readInt32BE(8) !== 0) { log('non-raw rect, skip'); continue; }
        const data = await r.read(rw * rhh * 4, 30000, 'pix');
        // render strip to RGB
        const rgb = Buffer.alloc(rw * rhh * 3);
        for (let p = 0, q = 0; p < rw * rhh * 4; p += 4, q += 3) { rgb[q] = data[p + 2]; rgb[q + 1] = data[p + 1]; rgb[q + 2] = data[p]; }
        pngBuf = encodePng(rw, rhh, rgb);
        log(`rect ${rw}x${rhh} at (${rx},${ry}) captured`);
      }
      break;
    } else if (mt === 1) { await r.read(5, 10000, 'p'); const n = (await r.read(2, 10000, 'n')).readUInt16BE(0); await r.read(n * 6, 10000, 'c'); }
    else if (mt === 2) { log('bell'); }
    else if (mt === 3) { await r.read(3, 10000, 'p'); const l = (await r.read(4, 10000, 'l')).readInt32BE(0); if (l > 0) await r.read(l, 10000, 't'); }
    else { log('unknown mt', mt); break; }
  }

  if (pngBuf) { fs.writeFileSync(OUT, pngBuf); log('wrote', OUT, pngBuf.length, 'bytes'); }
  else log('NO STRIP CAPTURED (connection likely closed after typing — keys may still have gone through)');

  try { sock.end(); setTimeout(() => sock.destroy(), 800); } catch (_) {}
  process.exit(0);
})().catch((e) => { console.error('[FATAL]', e.message); process.exit(3); });
