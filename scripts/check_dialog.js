const page = 26;
const snap = await browser.observe(page).snapshot();
const t = snap.text || '';
const interesting = t.split('\n').filter(l => /dialog|sure|yes|cancel|continue|confirm/i.test(l)).slice(0, 15);
return JSON.stringify({ interesting, tail: t.slice(-800) });
