const pages = await browser.pages.list();
const mine = pages.filter(p => p.ownership !== 'user');
// pick any ssdnodes tab, prefer productdetails
const target = mine.find(p => /productdetails/.test(p.url)) || mine.find(p => /ssdnodes/.test(p.url));
if (!target) return JSON.stringify({ pages: pages.map(p => ({ id: p.pageId, url: p.url, own: p.ownership })) });
const page = target.pageId;
await browser.nav(page).goto('https://www.ssdnodes.com/manage/clientarea.php?action=productdetails&id=48380');
await browser.wait(page, { value: 9000 });
let text = '';
try { text = await browser.read(page); } catch (e) { text = 'READ_ERR ' + e.message; }
const snap = await browser.observe(page).snapshot();
const stext = snap.text || '';
let restartRef = null;
const m = stext.match(/Restart[^\n]*?ref=(e\d+)/i) || stext.match(/ref=(e\d+)[^\n]*?Restart/i);
if (m) restartRef = m[1];
return JSON.stringify({ usedPage: page, ownership: target.ownership, loaded: text.includes('jdp-claw'), len: text.length, restartRef });
