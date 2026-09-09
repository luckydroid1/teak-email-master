const page = 26;
const t0 = Date.now();
const snap = await browser.observe(page).snapshot();
const stext = snap.text || '';
if (!stext.includes('jdp-claw') && !stext.toLowerCase().includes('restart')) {
  return JSON.stringify({ err: 'page-not-ready', head: stext.slice(0, 200) });
}
let restartRef = null;
const m = stext.match(/Restart[^\n]*?ref=(e\d+)/i) || stext.match(/ref=(e\d+)[^\n]*?Restart/i);
if (m) restartRef = m[1];
if (!restartRef) return JSON.stringify({ err: 'no-restart-ref', sample: stext.slice(-500) });
await browser.input(page).click(restartRef);
await browser.wait(page, { value: 1800 });
const snap2 = await browser.observe(page).snapshot();
const t2 = snap2.text || '';
let yesRef = null;
const m2 = t2.match(/Yes,?[ ]?continue[^\n]*?ref=(e\d+)/i) || t2.match(/ref=(e\d+)[^\n]*?Yes,?[ ]?continue/i);
if (m2) yesRef = m2[1];
let confirmed = false;
if (yesRef) { await browser.input(page).click(yesRef); confirmed = true; }
await browser.wait(page, { value: 3500 });
const after = await browser.grep(page, { pattern: '(?i)(running|stopped|offline)' });
return JSON.stringify({ restartRef, yesRef, confirmed, after: after.slice(0, 300), elapsed: Date.now() - t0 });
