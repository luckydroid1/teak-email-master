const page = 26;
const t0 = Date.now();
await browser.nav(page).goto('https://www.ssdnodes.com/manage/submitticket.php');
await browser.wait(page, { value: 6000 });
let text = '';
try { text = await browser.read(page); } catch (e) { text = 'ERR ' + e.message; }
// click a department link (technical/support)
const snap = await browser.observe(page).snapshot();
const stext = snap.text || '';
let deptRef = null;
const m = stext.match(/ref=(e\d+)[^\n]*?[Tt]echnical/i) || stext.match(/[Tt]echnical[^\n]*?ref=(e\d+)/i) || stext.match(/ref=(e\d+)[^\n]*?[Ss]upport/i);
if (m) deptRef = m[1];
let clicked = false;
if (deptRef) { await browser.input(page).click(deptRef); clicked = true; await browser.wait(page, { value: 5000 }); }
const snap2 = await browser.observe(page).snapshot();
const t2 = snap2.text || '';
// find subject + message fields
let subjRef = null, msgRef = null;
const ms = t2.match(/\[ref=(e\d+)\][^\n]*\n?[^\n]*subject/i) || t2.match(/subject[^\n]*\[ref=(e\d+)\]/i);
const mm = t2.match(/ref=(e\d+)[^\n]*\n?[^\n]*(message|body)/i) || t2.match(/(message|body)[^\n]*ref=(e\d+)/i);
if (ms) subjRef = ms[1];
if (mm) msgRef = mm[2] || mm[1];
return JSON.stringify({ clicked, deptRef, subjRef, msgRef, formText: t2.slice(0, 1500), elapsed: Date.now() - t0 });
