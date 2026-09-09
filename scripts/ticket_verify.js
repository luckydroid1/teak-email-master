const page = 28;
await browser.wait(page, { value: 6000 });
let text = '';
try { text = await browser.read(page); } catch (e) { text = 'ERR ' + e.message; }
if (!/ticket|submitted|thank/i.test(text)) {
  await browser.nav(page).goto('https://www.ssdnodes.com/manage/supporttickets.php');
  await browser.wait(page, { value: 6000 });
  try { text = await browser.read(page); } catch (e) { text = 'ERR ' + e.message; }
}
return JSON.stringify({ len: text.length, head: text.slice(0, 900) });
