const page = await browser.pages.newPage('https://www.ssdnodes.com/manage/submitticket.php');
const t0 = Date.now();
let text = '', loaded = false;
for (let i = 0; i < 9; i++) {
  await browser.wait(page, { value: 3000 });
  try { text = await browser.read(page); } catch (e) { text = 'ERR ' + e.message; }
  if (/department/i.test(text) && text.length > 500) { loaded = true; break; }
}
const snap = await browser.observe(page).snapshot();
const stext = snap.text || '';
const deptLines = stext.split('\n').filter(l => /department|technical|billing|sales/i.test(l)).slice(0, 10);
return JSON.stringify({ page, loaded, len: text.length, deptLines, tail: stext.slice(0, 800), elapsed: Date.now() - t0 });
