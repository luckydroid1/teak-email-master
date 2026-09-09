const pages = await browser.pages.list();
return JSON.stringify(pages.map(p => ({ id: p.pageId, url: (p.url||'').slice(0,80), title: (p.title||'').slice(0,40), own: p.ownership, owner: p.ownerLabel || '' })), null, 1);
