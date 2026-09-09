#!/usr/bin/env node
/* vnc_probe_type.js - connect, type text, disconnect. NO reads after typing. */
const net = require('net');
const crypto = require('crypto');
const HOST = process.env.VNC_HOST || '107.155.75.218';
const PORT = parseInt(process.env.VNC_PORT || '9579', 10);
const VNC_PASSWORD = process.env.VNC_PASSWORD || '';
const TEXT = process.argv[2] || 'probeXYZ\n';
const log = (...a) => console.log(new Date().toISOString(), ...a);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
function vncCh(pw, ch) {
  const p = Buffer.from(String(pw), 'latin1').subarray(0, 8);
  const pd = Buffer.alloc(8, 0); p.copy(pd, 0, 0, Math.min(p.length, 8));
  const k8 = Buffer.alloc(8);
  for (let i = 0; i < 8; i++) { let b = pd[i], r = 0; for (let bit = 0; bit < 8; bit++) if (b & (1 << bit)) r |= 1 << (7 - bit); k8[i] = r; }
  const c = crypto.createCipheriv('des-ede3', Buffer.concat([k8, k8, k8]), null);
  c.setAutoPadding(false);
  return Buffer.concat([c.update(ch), c.final()]).subarray(0, 16);
}
(async () => {
  const sock = net.createConnection({ host: HOST, port: PORT });
  let buf = Buffer.alloc(0);
  const readN = (n, t) => new Promise((res, rej) => {
    const start = Date.now();
    const iv = setInterval(() => {
      if (buf.length >= n) { clearInterval(iv); const o = buf.subarray(0, n); buf = buf.subarray(n); res(Buffer.from(o)); }
      else if (sock.destroyed) { clearInterval(iv); rej(new Error('closed')); }
      else if (Date.now() - start > t) { clearInterval(iv); rej(new Error('timeout')); }
    }, 30);
  });
  sock.on('data', (d) => { buf = Buffer.concat([buf, d]); });
  await new Promise((res, rej) => { sock.on('connect', res); sock.on('error', rej); });
  const ver = await readN(12, 15000); log('server:', JSON.stringify(ver.toString('latin1')));
  sock.write('RFB 003.008\n');
  const cnt = (await readN(1, 15000))[0];
  const types = cnt ? await readN(cnt, 15000) : Buffer.alloc(0);
  const sec = [...types].includes(2) ? 2 : 1;
  sock.write(Buffer.from([sec]));
  if (sec === 2) {
    const ch = await readN(16, 15000);
    sock.write(vncCh(VNC_PASSWORD, ch));
    const res = await readN(4, 15000);
    if (res.readUInt32BE(0) !== 0) throw new Error('auth failed');
    log('auth OK');
  }
  sock.write(Buffer.from([1]));
  const head = await readN(24, 15000);
  const W = head.readUInt16BE(0), H = head.readUInt16BE(2);
  const nameLen = head.readUInt32BE(20);
  if (nameLen) await readN(nameLen, 15000);
  log(`init ${W}x${H} ok — now typing`);
  const SPECIAL = { '\n': 0xff0d, '\r': 0xff0d, ' ': 0x0020 };
  for (const ch of TEXT) {
    const ks = ch in SPECIAL ? SPECIAL[ch] : (ch.charCodeAt(0) >= 32 && ch.charCodeAt(0) <= 126 ? ch.charCodeAt(0) : null);
    if (ks === null) continue;
    const k = Buffer.alloc(4); k.writeUInt32BE(ks, 0);
    sock.write(Buffer.concat([Buffer.from([4, 1, 0]), k])); await sleep(25);
    sock.write(Buffer.concat([Buffer.from([4, 0, 0]), k])); await sleep(70);
  }
  log('typed all — waiting 2s then closing WITHOUT reading');
  await sleep(2000);
  try { sock.end(); setTimeout(() => sock.destroy(), 500); } catch (_) {}
  log('DONE (clean close, no read-after-type)');
  process.exit(0);
})().catch((e) => { console.error('[FATAL]', e.message); process.exit(3); });
