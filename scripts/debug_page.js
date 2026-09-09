const page = await browser.pages.newPage('https://www.ssdnodes.com/manage/clientarea.php?action=productdetails&id=48380');
await browser.wait(page, { value: 8000 });
const info = await browser.pages.getInfo(page);
let text = '';
try { text = await browser.read(page); } catch (e) { text = 'READ_ERR: ' + e.message; }
return JSON.stringify({ title: info && info.title, url: info && info.url, textHead: text.slice(0, 1200) });
