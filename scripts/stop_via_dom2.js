const page = 26;
const t0 = Date.now();
// 1) click Stop via DOM (code body)
const click1 = await browser.evaluate(page, { code: "const btn=[...document.querySelectorAll('button')].find(b=>/stop/i.test((b.textContent||'').trim())); if(btn){btn.click(); return 'clicked';} return 'not-found';" });
await new Promise(r => setTimeout(r, 2500));
// 2) confirm dialog
const conf = await browser.evaluate(page, { code: "const btns=[...document.querySelectorAll('button')]; const yes=btns.find(b=>/yes|continue|confirm/i.test((b.textContent||'').trim())); if(yes){yes.click(); return 'confirmed:'+(yes.textContent||'').trim();} return 'none; text:'+document.body.innerText.slice(0,250);" });
await new Promise(r => setTimeout(r, 7000));
// 3) read status
const status = await browser.evaluate(page, { code: "const t=document.body.innerText; const m=t.match(/Status\\s*\\n?\\s*([A-Za-z ]+)/); return m?m[1].trim():t.slice(0,200);" });
return JSON.stringify({ click1, conf, status, elapsed: Date.now() - t0 });
