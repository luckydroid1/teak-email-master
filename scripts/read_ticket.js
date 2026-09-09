const page = 29;
const snap = await browser.observe(page).snapshot();
const t = snap.text || '';
const lines = t.split('\n');
let ref = null;
for (let i = 0; i < lines.length; i++) {
  if (/838995/.test(lines[i])) {
    for (let j = i; j < Math.min(i + 10, lines.length); j++) {
      const mm = lines[j].match(/ref=(e\d+)/);
      if (mm) { ref = mm[1]; break; }
    }
    break;
  }
}
if (ref) {
  await browser.input(page).click(ref);
  await browser.wait(page, { value: 6000 });
}
const text = await browser.read(page);
const i = text.toLowerCase().indexOf('password');
return JSON.stringify({ ref, opened: !!ref, body: text.slice(Math.max(0, i - 600), i + 1500) });
