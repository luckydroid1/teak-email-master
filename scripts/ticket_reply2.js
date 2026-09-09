const page = 30;
const t0 = Date.now();
await browser.nav(page).goto('https://www.ssdnodes.com/manage/viewticket.php?tid=838995&c=9EES9tQy');
await browser.wait(page, { value: 8000 });
const REPLY = [
  'Hello Neenu,',
  '',
  'Thank you for checking. We verified the ECDSA fingerprint matches our server, so you did reach the right machine. However:',
  '',
  '1. Your pasted output ends at the password prompt - it does not show a completed login. Could you paste the result AFTER entering the password?',
  '',
  '2. The password currently shown in our panel (Server Information > Show password) is REJECTED by sshd from our side: "Permission denied (publickey,password)". We tested both password and keyboard-interactive methods, and again after waiting (in case of temporary lockout).',
  '',
  '3. FASTEST FIX: please run this one command on the VPS console from your hypervisor side:',
  '',
  "mkdir -p /root/.ssh && echo 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIGE69dkjXnCC2sucHDHW30uP3EtFrBvOOrAUWYvjId27 pesat-deploy' >> /root/.ssh/authorized_keys && chmod 700 /root/.ssh && chmod 600 /root/.ssh/authorized_keys",
  '',
  'That immediately restores our access without changing anything else on the server.',
  '',
  'Thank you!',
  'Nell VH'
].join('\n');
// find the reply message textarea via DOM (AX refs proved unreliable)
const fillRes = await browser.evaluate(page, { code: "const tas=[...document.querySelectorAll('textarea')]; if(!tas.length) return 'no-textarea'; const ta=tas[0]; ta.focus(); return 'found:'+tas.length+' textareas, name='+(ta.name||'')+' id='+(ta.id||'');" });
// set value via DOM + dispatch input event (fill via AX failed to persist last time)
const setRes = await browser.evaluate(page, { code: "const ta=document.querySelector('textarea'); if(!ta) return 'no-textarea'; const msg=['Hello Neenu,','Thank you for checking. We verified the ECDSA fingerprint matches our server, so you reached the right machine. However:','1. Your pasted output ends at the password prompt - it does not show a completed login. Please paste the result AFTER entering the password.','2. The password currently shown in our panel (Server Information > Show password) is REJECTED by sshd from our side. We tested password and keyboard-interactive, and again after waiting in case of temporary lockout.','3. FASTEST FIX: please run this one command on the VPS console from your hypervisor side:','mkdir -p /root/.ssh && echo ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIGE69dkjXnCC2sucHDHW30uP3EtFrBvOOrAUWYvjId27 pesat-deploy >> /root/.ssh/authorized_keys','(wrap the echo argument in single quotes when running)','That immediately restores our access without changing anything else.','Thank you!','Nell VH'].join(String.fromCharCode(10)); ta.value=msg; ta.dispatchEvent(new Event('input',{bubbles:true})); return 'set-'+msg.length+'-chars';" });
await browser.wait(page, { value: 1500 });
// click the Send/Submit button via DOM
const clickRes = await browser.evaluate(page, { code: "const btns=[...document.querySelectorAll('button,input[type=submit]')]; const send=btns.find(b=>/^(send|submit|reply)$/i.test((b.value||b.textContent||'').trim())); if(send){send.click(); return 'clicked:'+(send.value||send.textContent).trim();} return 'no-send-btn; buttons:'+btns.map(b=>(b.value||b.textContent||'').trim()).filter(Boolean).slice(0,10).join('|');" });
await browser.wait(page, { value: 6000 });
// verify: count posts
const verify = await browser.evaluate(page, { code: "const t=document.body.innerText; return 'posts='+(t.split('Posted by').length-1)+'; url='+location.href.slice(0,90);" });
return JSON.stringify({ fillRes, setRes, clickRes, verify, elapsed: Date.now() - t0 });
