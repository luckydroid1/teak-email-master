const page = await browser.pages.newPage('https://www.ssdnodes.com/manage/clientarea.php?action=productdetails&id=48380&modop=custom&a=console');
let text = '';
for (let i = 0; i < 8; i++) {
  await browser.wait(page, { value: 3000 });
  try { text = await browser.read(page); } catch (e) { text = 'ERR'; }
  if (text.length > 3000) break;
}
return await browser.evaluate(page, { code: "const t=document.body.innerText; const i=t.search(/console|vnc|novnc|terminal|password/i); const hasCanvas=!!document.querySelector('canvas'); const hasIframe=!!document.querySelector('iframe'); return JSON.stringify({ hasCanvas, hasIframe, iframes: [...document.querySelectorAll('iframe')].map(f=>f.src.slice(0,80)), ctx: i>=0 ? t.slice(Math.max(0,i-200), i+900) : t.slice(0,900) });" });
