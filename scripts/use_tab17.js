const page = 17;
await browser.nav(page).goto('https://www.ssdnodes.com/manage/clientarea.php?action=productdetails&id=48380');
await browser.wait(page, { value: 9000 });
let text = '';
try { text = await browser.read(page); } catch (e) { text = 'READ_ERR ' + e.message; }
const snap = await browser.observe(page).snapshot();
const stext = snap.text || '';
let restartRef = null;
const m = stext.match(/Restart[^\n]*?ref=(e\d+)/i) || stext.match(/ref=(e\d+)[^\n]*?Restart/i);
if (m) restartRef = m[1];
return JSON.stringify({ loaded: text.includes('jdp-claw'), len: text.length, restartRef, sample: stext.slice(0, 300) });
