const page = 29;
const t0 = Date.now();
await browser.nav(page).goto('https://www.ssdnodes.com/manage/viewticket.php?tid=838995&c=9EES9tQy');
await browser.wait(page, { value: 8000 });
return await browser.evaluate(page, { code: "const t=document.body.innerText; const i=t.indexOf('Reply'); const j=t.indexOf('Posted by'); const k=(j>=0)?j:i; return k>=0 ? t.slice(k, k+2500) : t.slice(800, 3200);" });
