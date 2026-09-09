// Persist recovered secrets to .ops/secrets.env (0600). Never prints plaintext.
const fs = require('fs');
const f = 'C:/Users/User/.zcode/cli/agents/sess_6847e2fa-2188-4191-8307-35cba5203562/agent_6ea005b5-2058-4b4b-9c06-48f2dfc946e8/transcript.jsonl';
const lines = fs.readFileSync(f, 'utf8').split('\n').filter(Boolean);
const firstUse = {};
for (let i = 0; i < lines.length; i++) {
  const s = lines[i];
  for (const m of s.matchAll(/sshpass\s+-p\s+['"]([^'"\s\\]{4,40})['"]/g)) {
    const pw = m[1]; const tag = pw.slice(0, 2) + '***' + pw.slice(-2);
    if (!firstUse[tag]) firstUse[tag] = { line: i, pw };
  }
}
const vncPw = firstUse['9L***2^']?.pw;
const oldRoot = firstUse['C6***mU']?.pw;
const curRoot = firstUse['6q***94']?.pw;
if (!vncPw || !curRoot) { console.error('MISSING candidates'); process.exit(1); }
// sanity: distinct
if (new Set([vncPw, oldRoot, curRoot]).size !== 3) { console.error('unexpected collision'); process.exit(1); }
const body = [
  '# Recovered from prior agent session transcript (agent_6ea005b5, 2026-08-25). DO NOT COMMIT.',
  'export VNC_PASSWORD=' + JSON.stringify(vncPw),
  'export ROOT_PW_OLD=' + JSON.stringify(oldRoot || ''),
  'export ROOT_PW_CURRENT=' + JSON.stringify(curRoot),
  '',
].join('\n');
fs.writeFileSync('D:/Claude Cowork/Mail-admin-pesat/.ops/secrets.env', body, { mode: 0o600 });
console.log('secrets.env written:', { vncLen: vncPw.length, oldLen: oldRoot ? oldRoot.length : 0, curLen: curRoot.length });
