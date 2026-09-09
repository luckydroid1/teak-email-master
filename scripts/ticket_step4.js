const page = 28;
const t0 = Date.now();
await browser.input(page).press('Escape');
await browser.wait(page, { value: 1000 });
const snap = await browser.observe(page).snapshot();
const t = snap.text || '';
// find ALL buttons/links with send/submit text
const lines = t.split('\n');
const candidates = [];
for (let i = 0; i < lines.length; i++) {
  if (/\b(send|submit)\b/i.test(lines[i]) && /ref=e\d+/.test(lines[i]) && !/home/i.test(lines[i])) {
    candidates.push(lines[i].trim().slice(0, 120));
  }
}
// find the exact ref for a button named Send or Submit
let sendRef = null;
for (const ln of lines) {
  const m = ln.match(/button\s+"(?:Send|Submit)[^"]*"\s+\[ref=(e\d+)\]/i);
  if (m) { sendRef = m[1]; break; }
}
return JSON.stringify({ candidates, sendRef, tail: lines.slice(-25).join('\n').slice(0, 1200), elapsed: Date.now() - t0 });
