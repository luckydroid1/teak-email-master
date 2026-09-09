const page = 28;
const t0 = Date.now();
const SUBJECT = 'SSH access locked out - need root access restoration (VPS 94.100.26.189 / jdp-claw)';
const MESSAGE = [
  'Hello SSDNodes team,',
  '',
  'I am completely locked out of my VPS and need your help restoring access.',
  '',
  'Server details:',
  '- Hostname: jdp-claw',
  '- IP: 94.100.26.189',
  '- OS: Ubuntu 24.04',
  '',
  'Problem:',
  '1. SSH rejects all passwords. Please note sshd DOES offer the password method (we see "Permission denied (publickey,password)"), but no password we have works.',
  '2. The panel "Reset password" feature updates the password shown in the client area, but after a full Stop + Start power cycle the new password still does not work on the actual VPS. It seems the reset is not injected into the guest.',
  '3. The VNC console proxy (107.155.75.218:9579) is effectively VIEW-ONLY: it delivers the framebuffer correctly, but KeyEvent input from clients is filtered/dropped (verified programmatically with a custom RFB 3.8 client). So we cannot type anything at the console.',
  '',
  'Request - please do ONE of the following:',
  'a) Run a password reset for root from your side (e.g. via hypervisor/VNC by your staff), set it to a temporary password you share with me, or',
  'b) Inject this SSH public key into /root/.ssh/authorized_keys for root:',
  '',
  'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIGE69dkjXnCC2sucHDHW30uP3EtFrBvOOrAUWYvjId27 pesat-deploy',
  '',
  'or c) provide a working interactive console session.',
  '',
  'Please do NOT reinstall the server - it hosts a live production web app with data we must keep.',
  '',
  'Thank you!',
  'Nell VH'
].join('\n');

await browser.input(page).fill('e31', SUBJECT);
await browser.input(page).fill('e59', MESSAGE);
await browser.wait(page, { value: 1500 });
// find submit/send button
const snap = await browser.observe(page).snapshot();
const t = snap.text || '';
let sendRef = null;
const m = t.match(/(?:send|submit)[^\n]*ref=(e\d+)/i) || t.match(/ref=(e\d+)[^\n]*\n[^\n]*(?:Send|Submit)/i);
if (m) sendRef = m[1];
// also check for captcha marker
const hasCaptcha = /recaptcha|captcha/i.test(t);
let clicked = false;
if (sendRef && !hasCaptcha) { await browser.input(page).click(sendRef); clicked = true; await browser.wait(page, { value: 4000 }); }
const after = await browser.read(page);
return JSON.stringify({ sendRef, hasCaptcha, clicked, result: after.slice(0, 600), elapsed: Date.now() - t0 });
