const page = await browser.pages.newPage('https://www.ssdnodes.com/manage/clientarea.php?action=productdetails&id=48380');
await browser.wait(page, { value: 5000 });
const snap = await browser.observe(page).snapshot();
const text = snap.text || '';
const lines = text.split('\n').filter(l => /restart|stop|reset|running|status/i.test(l)).slice(0, 15);
let restartRef = null;
const m = text.match(/Restart[^\n]*?ref=(e\d+)/i) || text.match(/ref=(e\d+)[^\n]*?Restart/i);
if (m) restartRef = m[1];

let confirmed = false;
if (restartRef) {
  await browser.input(page).click(restartRef);
  await browser.wait(page, { value: 2000 });
  const snap2 = await browser.observe(page).snapshot();
  const t2 = snap2.text || '';
  const m2 = t2.match(/Yes,?[ ]?continue[^\n]*?ref=(e\d+)/i) || t2.match(/ref=(e\d+)[^\n]*?Yes,?[ ]?continue/i);
  if (m2) {
    await browser.input(page).click(m2[1]);
    confirmed = true;
  } else {
    // maybe a generic confirm button
    const m3 = t2.match(/(?:Yes|Confirm|Continue)[^\n]*?ref=(e\d+)/i);
    if (m3) { await browser.input(page).click(m3[1]); confirmed = true; }
  }
  await browser.wait(page, { value: 5000 });
}
const after = await browser.grep(page, { pattern: '(?i)(running|stopped|offline|reboot)' });
return JSON.stringify({ restartRef, confirmed, after: after.slice(0, 400) });
