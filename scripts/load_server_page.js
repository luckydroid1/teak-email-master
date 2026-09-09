const page = await browser.pages.newPage('about:blank');
let loaded = false, text = '', title = '';
for (let attempt = 0; attempt < 4 && !loaded; attempt++) {
  await browser.nav(page).goto('https://www.ssdnodes.com/manage/clientarea.php?action=productdetails&id=48380');
  await browser.wait(page, { value: 8000 });
  try { text = await browser.read(page); } catch (e) { text = 'READ_ERR ' + e.message; }
  title = (await browser.pages.getInfo(page) || {}).title || '';
  if (text.includes('Restart') || text.includes('jdp-claw')) { loaded = true; break; }
  await browser.nav(page).reload();
  await browser.wait(page, { value: 6000 });
  try { text = await browser.read(page); } catch (e) { text = 'READ_ERR ' + e.message; }
  if (text.includes('Restart') || text.includes('jdp-claw')) { loaded = true; break; }
}
return JSON.stringify({ loaded, title, len: text.length, head: text.slice(0, 600) });
