const page = 29;
const t0 = Date.now();
await browser.nav(page).goto('https://www.ssdnodes.com/manage/viewticket.php?tid=838995&c=9EES9tQy');
await browser.wait(page, { value: 8000 });
const REPLY = [
  'Hello Neenu,',
  '',
  'Thank you for the quick response. However, we still cannot log in:',
  '',
  '1. The password currently displayed in the panel (Server Information > Password > Show password) is rejected by sshd: "Permission denied (publickey,password)". We tried both password and keyboard-interactive methods.',
  '',
  '2. Your test output shows the password PROMPT but not a completed login. Could you please paste the actual successful login (after typing the password), and tell us the EXACT current root password?',
  '',
  '3. Alternatively, the fastest fix for us: please run this single command on the VPS console from your side:',
  '',
  "mkdir -p /root/.ssh && echo 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIGE69dkjXnCC2sucHDHW30uP3EtFrBvOOrAUWYvjId27 pesat-deploy' >> /root/.ssh/authorized_keys && chmod 700 /root/.ssh && chmod 600 /root/.ssh/authorized_keys",
  '',
  'This adds our public SSH key and immediately restores our access without changing anything else.',
  '',
  'Also please check whether fail2ban or any IP-based restriction might be blocking our IP (we made several failed password attempts while diagnosing).',
  '',
  'Thank you!',
  'Nell VH'
].join('\n');
// find reply textarea
const snap = await browser.observe(page).snapshot();
const t = snap.text || '';
let msgRef = null;
const lines = t.split('\n');
for (let i = 0; i < lines.length; i++) {
  if (/reply|message|multiline/i.test(lines[i])) {
    for (let j = Math.max(0, i - 2); j < Math.min(i + 4, lines.length); j++) {
      const mm = lines[j].match(/ref=(e\d+)/);
      if (mm) { msgRef = mm[1]; break; }
    }
    if (msgRef) break;
  }
}
if (!msgRef) return JSON.stringify({ err: 'no-reply-textarea', sample: t.slice(-1200) });
await browser.input(page).fill(msgRef, REPLY);
await browser.wait(page, { value: 1000 });
// find submit button
const snap2 = await browser.observe(page).snapshot();
const t2 = snap2.text || '';
let sendRef = null;
const m2 = t2.match(/button\s+"(?:Send|Submit|Reply)[^"]*"\s+\[ref=(e\d+)\]/i);
if (m2) sendRef = m2[1];
let clicked = false;
if (sendRef) { await browser.input(page).click(sendRef); clicked = true; await browser.wait(page, { value: 5000 }); }
const after = await browser.read(page);
return JSON.stringify({ msgRef, sendRef, clicked, url: (await browser.pages.getInfo(page)).url, result: after.slice(0, 400), elapsed: Date.now() - t0 });
