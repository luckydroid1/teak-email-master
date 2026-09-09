#!/usr/bin/env node
/**
 * vnc_type.js - Minimal RFB 3.8 client that authenticates to the SSDNodes VNC
 * proxy and blindly types a shell command to install an SSH public key.
 *
 * Node built-ins only (net + crypto).
 *
 * Usage: node vnc_type.js <pubkey-one-line>
 *   Env: VNC_HOST (default 107.155.75.218), VNC_PORT (default 9579),
 *        VNC_PASSWORD (required), SKIP_LOGIN=1 to omit user/password typing.
 *
 * Evidence-based notes:
 *  - Auth + ServerInit reads are known-good on this proxy.
 *  - The proxy historically DROPS connections when framebuffer updates flow,
 *    so we request only a single 1x1 pixel incrementally, attempt ONE read
 *    with a 10s timeout, and never let a read failure kill the session.
 *    If the probe kills the socket we reconnect and skip the FB probe.
 */

const net = require('net');

const HOST = process.env.VNC_HOST || '107.155.75.218';
const PORT = parseInt(process.env.VNC_PORT || '9579', 10);
const VNC_PASSWORD = process.env.VNC_PASSWORD || '';
const PUBKEY = process.argv[2] || '';

if (!VNC_PASSWORD) { console.error('[FATAL] VNC_PASSWORD env not set'); process.exit(1); }
if (!PUBKEY || /["'\\]/.test(PUBKEY)) { console.error('[FATAL] pubkey missing or contains quote chars'); process.exit(1); }

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...a) => console.log(new Date().toISOString(), ...a);

// ---- VNC DES auth -----------------------------------------------------------
// VNC: take first 8 bytes of password, reverse bits of each byte -> DES key,
// encrypt the 16-byte challenge with DES (ECB over two 8-byte blocks).
// Node's legacy provider blocks des-ecb, but des-ede3 with K1=K2=K3 == single DES.
function vncChallengeResponse(password, challenge) {
  const pw = Buffer.from(String(password), 'latin1').subarray(0, 8);
  const padded = Buffer.alloc(8, 0); // pad with NULs to 8 bytes
  pw.copy(padded, 0, 0, Math.min(pw.length, 8));
  const key8 = Buffer.alloc(8);
  for (let i = 0; i < 8; i++) {
    let b = padded[i], r = 0;
    for (let bit = 0; bit < 8; bit++) if (b & (1 << bit)) r |= 1 << (7 - bit);
    key8[i] = r;
  }
  const key24 = Buffer.concat([key8, key8, key8]);
  const cipher = require('crypto').createCipheriv('des-ede3', key24, null);
  cipher.setAutoPadding(false);
  return Buffer.concat([cipher.update(challenge), cipher.final()]).subarray(0, 16);
}

// ---- Exact-length stream reader ---------------------------------------------
function makeReader(sock) {
  let buf = Buffer.alloc(0);
  let wake = null;
  sock.on('data', (d) => { buf = Buffer.concat([buf, d]); if (wake) { const w = wake; wake = null; w(); } });
  sock.on('close', () => { if (wake) { const w = wake; wake = null; w(); } });
  sock.on('error', () => { if (wake) { const w = wake; wake = null; w(); } });
  return async function read(n, timeoutMs, label) {
    const start = Date.now();
    while (buf.length < n) {
      if (sock.destroyed || sock.readyState === 'closed') throw new Error(`socket-closed-while-awaiting:${label || n}`);
      const remaining = timeoutMs ? timeoutMs - (Date.now() - start) : 60000;
      if (remaining <= 0) throw new Error(`timeout-waiting:${label || n}`);
      await new Promise((res) => { wake = res; setTimeout(res, Math.min(remaining, 500)); });
      if (buf.length >= n) break;
    }
    const out = buf.subarray(0, n); buf = buf.subarray(n);
    return Buffer.from(out);
  };
}

// ---- Connect + authenticate --------------------------------------------------
async function connectRFB({ doFramebufferProbe }) {
  const sock = net.createConnection({ host: HOST, port: PORT }, () => {});
  sock.setTimeout(120000);
  sock.on('error', (e) => log('[sock-error]', e.message));

  const read = makeReader(sock);

  const ver = await read(12, 15000, 'server-version');
  log('server version:', JSON.stringify(ver.toString('latin1')));
  sock.write('RFB 003.008\n');

  const cntBuf = await read(1, 15000, 'sec-type-count');
  const count = cntBuf[0];
  const types = count > 0 ? await read(count, 15000, 'sec-types') : Buffer.alloc(0);
  log('security types:', [...types].join(','));

  if (count === 0) {
    const reasonLen = (await read(4, 15000, 'reason-len')).readUInt32BE(0);
    const reason = await read(reasonLen, 15000, 'reason');
    throw new Error('server rejected connection: ' + reason.toString('latin1'));
  }

  let secType = 1;
  if ([...types].includes(2)) secType = 2;
  else if ([...types].includes(1)) secType = 1;
  else throw new Error('no supported security type');
  log('chose security type', secType);
  sock.write(Buffer.from([secType]));

  if (secType === 2) {
    const challenge = await read(16, 15000, 'vnc-challenge');
    const resp = vncChallengeResponse(VNC_PASSWORD, challenge);
    sock.write(resp);
    const result = await read(4, 15000, 'security-result');
    const code = result.readUInt32BE(0);
    if (code !== 0) throw new Error('VNC auth failed, SecurityResult=' + code);
    log('VNC auth OK');
  } else {
    log('None security selected (no challenge)');
  }

  // ClientInit(shared=1)
  sock.write(Buffer.from([1]));
  // ServerInit: u16 w, u16 h, 16B pixelformat, u32 namelen, name
  const head = await read(24, 15000, 'serverinit-head');
  const width = head.readUInt16BE(0), height = head.readUInt16BE(2);
  const nameLen = head.readUInt32BE(20);
  const name = nameLen > 0 ? await read(nameLen, 15000, 'serverinit-name') : Buffer.alloc(0);
  log(`ServerInit: ${width}x${height} name="${name.toString('latin1')}"`);

  function sendMsg(buf) { return sock.writable ? sock.write(buf) : false; }

  if (doFramebufferProbe) {
    // SetPixelFormat (msg 0): type,pad3, bpp,depth,bigendian,truecolour,
    // redmax,greenmax,bluemax (u16), redshift,greenshift,blueshift (u16), pad3
    const pf = Buffer.alloc(20);
    pf[0] = 0;
    pf[4] = 32; pf[5] = 32; pf[6] = 0; pf[7] = 1;
    pf.writeUInt16BE(255, 8); pf.writeUInt16BE(255, 10); pf.writeUInt16BE(255, 12);
    pf.writeUInt16BE(16, 14); pf.writeUInt16BE(8, 16); pf.writeUInt16BE(0, 18);
    sendMsg(pf);
    // SetEncodings (msg 2): type, pad, numEncodings u16, encodings s32[]
    const enc = Buffer.alloc(8);
    enc[0] = 2; enc.writeUInt16BE(1, 2); enc.writeInt32BE(0, 4); // raw
    sendMsg(enc);
    // FramebufferUpdateRequest (msg 3): type, incremental, x,y,w,h (u16)
    const req = Buffer.alloc(10);
    req[0] = 3; req[1] = 1; req.writeUInt16BE(0, 2); req.writeUInt16BE(0, 4); req.writeUInt16BE(1, 6); req.writeUInt16BE(1, 8);
    sendMsg(req);
    log('FB probe sent (1x1 incremental). Attempting ONE read, 10s budget...');
    try {
      const chunk = await read(1, 10000, 'fb-update-first-byte');
      log('FB probe READ SUCCESS: got first update byte 0x' + chunk[0].toString(16) + '; draining briefly');
      await read(0, 2000, 'drain').catch(() => {});
    } catch (e) {
      log('FB probe read outcome:', e.message, '(continuing regardless)');
    }
  }

  return { sock, read };
}

// ---- Key events ----------------------------------------------------------------
const SPECIAL = { '\n': 0xff0d, '\r': 0xff0d, '\t': 0xff09, '\b': 0xff08, '\x1b': 0xff1b, ' ': 0x0020 };

function keysymFor(ch) {
  if (ch in SPECIAL) return SPECIAL[ch];
  const c = ch.charCodeAt(0);
  if (c >= 32 && c <= 126) return c;
  return null;
}

async function typeString(sock, text, delayMs = 40) {
  for (const ch of text) {
    const ks = keysymFor(ch);
    if (ks === null) { log('[skip-char]', JSON.stringify(ch)); continue; }
    const down = Buffer.from([4, 1, 0]); const up = Buffer.from([4, 0, 0]);
    const k = Buffer.alloc(4); k.writeUInt32BE(ks, 0);
    sock.write(Buffer.concat([down, k]));
    await sleep(15);
    sock.write(Buffer.concat([up, k]));
    await sleep(delayMs);
  }
}

// ---- Main -------------------------------------------------------------------------
(async () => {
  let session;
  try {
    session = await connectRFB({ doFramebufferProbe: true });
  } catch (e) {
    log('[FATAL] initial connect failed:', e.message);
    process.exit(2);
  }

  if (!session.sock.writable || session.sock.destroyed) {
    log('probe killed the socket -> reconnecting WITHOUT framebuffer probe');
    try { session.sock.destroy(); } catch (_) {}
    session = await connectRFB({ doFramebufferProbe: false });
  }

  const sock = session.sock;

  // Blind sequence -------------------------------------------------------------
  log('STEP 1: send "\\n", wait 2000ms');
  await typeString(sock, '\n'); await sleep(2000);

  if (!process.env.SKIP_LOGIN) {
    log('STEP 2: type "root\\n" (harmless if already at root shell), wait 3000ms');
    await typeString(sock, 'root\n'); await sleep(3000);
    if (process.env.ROOT_PW) {
      log('STEP 3: typing root password from ROOT_PW env, wait 6000ms');
      await typeString(sock, process.env.ROOT_PW + '\n', 60); await sleep(6000);
    } else {
      log('STEP 3: SKIPPED root password typing (ROOT_PW env not set; refusing to guess)');
    }
  } else {
    log('STEPS 2-3: skipped (SKIP_LOGIN=1)');
  }

  log('STEP 5: typing authorized_keys install command (~180 chars @45ms), then wait 8000ms');
  const cmd = `mkdir -p /root/.ssh && echo '${PUBKEY}' >> /root/.ssh/authorized_keys && chmod 700 /root/.ssh && chmod 600 /root/.ssh/authorized_keys && (systemctl restart sshd || systemctl restart ssh)\n`;
  await typeString(sock, cmd, 45);
  await sleep(8000);

  log('STEP 6: final 3000ms grace, closing socket gracefully');
  await sleep(3000);
  try { sock.end(); setTimeout(() => sock.destroy(), 1500); } catch (_) {}
  log('DONE');
})().catch((e) => { console.error('[FATAL]', e.message); process.exit(3); });
