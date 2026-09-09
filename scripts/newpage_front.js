const page = await browser.pages.newPage('https://www.ssdnodes.com/manage/clientarea.php?action=productdetails&id=48380', { background: false });
await browser.wait(page, { value: 9000 });
let text = '';
try { text = await browser.read(page); } catch (e) { text = 'READ_ERR ' + e.message; }
const snap = await browser.observe(page).snapshot();
const stext = snap.text || '';
let restartRef = null;
const m = stext.match(/Restart[^\n]*?ref=(e\d+)/i) || stext.match(/ref=(e\d+)[^\n]*?Restart/i);
if (m) restartRef = m[1];
return JSON.stringify({ page, loaded: text.includes('jdp-claw'), len: text.length, restartRef, head: text.slice(0, 250) });
