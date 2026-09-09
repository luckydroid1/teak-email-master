const page = 30;
await browser.nav(page).reload();
await browser.wait(page, { value: 8000 });
return await browser.evaluate(page, { code: "const t=document.body.innerText; const posts=t.split('Posted by').length-1; const times=[]; let idx=0; while(true){ const i=t.indexOf('Posted by', idx); if(i<0) break; times.push(t.slice(i, i+70).replace(String.fromCharCode(10),' ')); idx=i+10; } const i=t.indexOf('Posted by'); return JSON.stringify({postCount:posts, posts:times, firstPost: t.slice(i, i+600)});" });
