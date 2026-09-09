const page = 28;
const t0 = Date.now();
// click Technical Issue department
await browser.input(page).click('e36');
await browser.wait(page, { value: 5000 });
const snap = await browser.observe(page).snapshot();
const t = snap.text || '';
// locate subject input and message textarea refs
let subjRef = null, msgRef = null;
const ms = t.match(/subject[^\n]*\[ref=(e\d+)\]/i) || t.match(/\[ref=(e\d+)\][^\n]*\n[^\n]*subject/i) || t.match(/textbox[^\n]*\n[^\n]*Subject/i);
const lines = t.split('\n');
for (let i = 0; i < lines.length; i++) {
  if (/subject/i.test(lines[i]) && !subjRef) {
    const m = (lines[i] + ' ' + (lines[i+1] || '')).match(/ref=(e\d+)/);
    if (m) subjRef = m[1];
    for (let j = i; j < Math.min(i + 3, lines.length); j++) {
      const mm = lines[j].match(/ref=(e\d+)/);
      if (mm) { subjRef = mm[1]; break; }
    }
  }
  if (/message|multiline/i.test(lines[i]) && !msgRef) {
    for (let j = Math.max(0, i - 1); j < Math.min(i + 3, lines.length); j++) {
      const mm = lines[j].match(/ref=(e\d+)/);
      if (mm) { msgRef = mm[1]; break; }
    }
  }
}
return JSON.stringify({ subjRef, msgRef, formSample: t.slice(0, 2000), elapsed: Date.now() - t0 });
