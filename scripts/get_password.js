const page = 29;
await browser.nav(page).goto('https://www.ssdnodes.com/manage/clientarea.php?action=productdetails&id=48380');
await browser.wait(page, { value: 8000 });
const r = await browser.evaluate(page, { code: "const els=[...document.querySelectorAll('a,button,span,div,i')].filter(e=>/show password/i.test(e.textContent||'')&&(e.textContent||'').trim().length<30); if(els.length){els[0].click(); return 'clicked';} return 'not-found';" });
await browser.wait(page, { value: 1500 });
const pw = await browser.evaluate(page, { code: "const t=document.body.innerText; const i=t.indexOf('Password'); const seg=t.substring(i, i+150); const lines=seg.split(String.fromCharCode(10)); return lines.length>1 ? lines[1].trim() : 'parse-fail: '+seg.slice(0,60);" });
return JSON.stringify({ r, pw });
