const pages = await browser.pages.list();
const mine = pages.filter(p => /ssdnodes/.test(p.url || '') && p.ownership === 'mine');
let page;
if (mine.length) {
  page = mine[0].pageId;
  await browser.nav(page).goto('https://www.ssdnodes.com/manage/clientarea.php?action=productdetails&id=48380');
} else {
  page = await browser.pages.newPage('https://www.ssdnodes.com/manage/clientarea.php?action=productdetails&id=48380');
}
await browser.wait(page, { value: 8000 });
let text = '';
try { text = await browser.read(page); } catch (e) { text = 'ERR ' + e.message; }
// extract the useful bits
const grab = (re) => { const m = text.match(re); return m ? m[1].trim() : null; };
return JSON.stringify({
  page,
  loaded: text.includes('jdp-claw'),
  hostname: grab(/Hostname\s*\n+\s*(\S+)/),
  status: grab(/Status\s*\n+\s*([A-Za-z ]+)/),
  os: grab(/OS\s*\n+\s*(\S+)/),
  location: grab(/Location\s*\n+\s*([A-Za-z ]+)/),
  credit: grab(/Available Credit[\s\S]{0,80}?Amount\s*\n+\s*(\$[\d.,]+)/),
  len: text.length
});
