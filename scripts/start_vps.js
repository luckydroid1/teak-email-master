const page = 26;
const t0 = Date.now();
// find Start/Boot/Power-on button
const click1 = await browser.evaluate(page, { code: "const btns=[...document.querySelectorAll('button')]; const names=btns.map(b=>(b.textContent||'').trim()).filter(Boolean); const start=btns.find(b=>/^(start|boot|power on|on)$/i.test((b.textContent||'').trim())); if(start){start.click(); return 'clicked-start';} return 'no-start-btn; buttons:'+names.join('|').slice(0,200);" });
await new Promise(r => setTimeout(r, 2500));
const conf = await browser.evaluate(page, { code: "const btns=[...document.querySelectorAll('button')]; const yes=btns.find(b=>/yes|continue|confirm/i.test((b.textContent||'').trim())); if(yes){yes.click(); return 'confirmed';} return 'no-dialog';" });
await new Promise(r => setTimeout(r, 5000));
const status = await browser.evaluate(page, { code: "const t=document.body.innerText; const m=t.match(/Status\\s*\\n?\\s*([A-Za-z ]+)/); return m?m[1].trim():t.slice(0,150);" });
return JSON.stringify({ click1, conf, status, elapsed: Date.now() - t0 });
