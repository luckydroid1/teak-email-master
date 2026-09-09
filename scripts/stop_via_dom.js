const page = 26;
const t0 = Date.now();
// 1) click Stop via DOM
await browser.evaluate(page, { func: () => {
  const btn = [...document.querySelectorAll('button')].find(b => /stop/i.test((b.textContent||'').trim()) || /stop/i.test(b.className||''));
  if (btn) btn.click();
  return btn ? 'clicked:' + (btn.textContent||'').trim() : 'not-found';
}});
await new Promise(r => setTimeout(r, 2500));
// 2) find and click confirm in DOM
const conf = await browser.evaluate(page, { func: () => {
  const btns = [...document.querySelectorAll('button')];
  const yes = btns.find(b => /yes|continue|confirm/i.test((b.textContent||'').trim()));
  if (yes) { yes.click(); return 'confirmed:' + (yes.textContent||'').trim(); }
  return 'no-dialog-buttons; visible-text: ' + document.body.innerText.slice(0, 300);
}});
await new Promise(r => setTimeout(r, 7000));
// 3) read status
const status = await browser.evaluate(page, { func: () => {
  const t = document.body.innerText;
  const m = t.match(/Status\s*\n?\s*([A-Za-z ]+)/);
  return m ? m[1].trim() : t.slice(0, 200);
}});
return JSON.stringify({ conf: typeof conf === 'string' ? conf : JSON.stringify(conf), status: typeof status === 'string' ? status : JSON.stringify(status), elapsed: Date.now() - t0 });
