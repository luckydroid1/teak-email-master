const page = await browser.pages.newPage('https://www.ssdnodes.com/manage/clientarea.php?action=productdetails&id=48380');
await browser.wait(page, { value: 9000 });
let text = '';
try { text = await browser.read(page); } catch (e) { text = 'READ_ERR ' + e.message; }
const loaded = text.includes('jdp-claw');
return JSON.stringify({ loaded, len: text.length, hasRestart: text.includes('Restart'), head: text.slice(0, 400) });
