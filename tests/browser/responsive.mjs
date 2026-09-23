import fs from 'node:fs/promises';
// Node 22+, connected to a dedicated headless browser and disposable seeded app.
const origin = process.env.UI_BASE_URL || 'http://127.0.0.1:8097';
const targets = await (await fetch((process.env.CDP_URL || 'http://127.0.0.1:9333') + '/json/list')).json();
const ws = new WebSocket(targets.find(t => t.type === 'page').webSocketDebuggerUrl);
await new Promise(resolve => ws.addEventListener('open', resolve, {once:true}));
let sequence = 0;
const pending = new Map();
const exceptions = [];
ws.addEventListener('message', ({data}) => {
  const message = JSON.parse(data);
  if (message.method === 'Runtime.exceptionThrown') exceptions.push(message.params.exceptionDetails.text);
  if (pending.has(message.id)) {
    const {resolve,reject} = pending.get(message.id); pending.delete(message.id);
    message.error ? reject(new Error(JSON.stringify(message.error))) : resolve(message.result);
  }
});
const call = (method, params = {}) => new Promise((resolve,reject) => {const id=++sequence;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}));});
const evaluate = async expression => {
  const result=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
  if(result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
  return result.result.value;
};
const navigate = async path => {
  await call('Page.navigate',{url:origin+path});
  for(let i=0;i<60;i++) {
    await new Promise(r=>setTimeout(r,100));
    if(await evaluate('document.readyState === "complete"')) break;
  }
  await evaluate('document.fonts.ready.then(()=>true)');
};

await call('Page.enable'); await call('Runtime.enable');
await call('Network.clearBrowserCookies');
const reports=[];
const audit=async (path,width,role) => {
 await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
 await navigate(path);
 await new Promise(r=>setTimeout(r,180));
 const status = await evaluate(`fetch(${JSON.stringify(path)}, {headers:{Accept:'text/html'}}).then(response=>response.status)`);
 if(status !== (path === '/not-found' ? 404 : 200)) throw new Error(`${role} ${path}: HTTP ${status}`);
 const result=await evaluate(`(() => {
  const visible=n=>!!(n.offsetWidth||n.offsetHeight);
  const bad=[...document.querySelectorAll('body *')].filter(n=>visible(n)&&n.getBoundingClientRect().right>innerWidth+2&&!n.closest('.bracket-canvas,.bracket-connectors')).slice(0,8).map(n=>({tag:n.tagName,cls:n.className?.baseVal||n.className,right:n.getBoundingClientRect().right}));
  const nodes=[...document.querySelectorAll('.bracket-match-node')].filter(visible).map(n=>n.getBoundingClientRect());
  let overlaps=0;for(let i=0;i<nodes.length;i++)for(let j=i+1;j<nodes.length;j++){const a=nodes[i],b=nodes[j];if(a.left<b.right&&a.right>b.left&&a.top<b.bottom&&a.bottom>b.top)overlaps++;}
  return {title:document.title,width:innerWidth,body:document.body.scrollWidth,bad,overlaps,nodes:nodes.length};
 })()`);
 reports.push({path,width,role,...result});
 if(result.body>width+1||result.overlaps) console.log('ISSUE',JSON.stringify(reports.at(-1)));
};
await navigate('/events');
const event=await evaluate('document.querySelector(".tournament-card").getAttribute("href")');
await navigate(new URL(event).pathname);
const competition=await evaluate('document.querySelector(".tournament-card").getAttribute("href")');
const bracket=new URL(competition).pathname;
const base=bracket.replace(/\/bracket$/,'');
const paths=['/events',new URL(event).pathname,bracket,base+'/overview',base+'/results',base+'/matches','/login','/api/docs','/not-found'];
for(const width of [320,390,768,820,1024,1366])for(const path of paths)await audit(path,width,'viewer');
await audit(bracket,390,'viewer');


if(process.env.UI_ADMIN_EMAIL && process.env.UI_ADMIN_PASSWORD) {
 await navigate('/login');
 await evaluate(`(() => {
 document.querySelector('[name=email]').value=${JSON.stringify(process.env.UI_ADMIN_EMAIL)};
 document.querySelector('[name=password]').value=${JSON.stringify(process.env.UI_ADMIN_PASSWORD)};
 document.querySelector('form[action$="/login"]').requestSubmit();
 })()`);
 await new Promise(r=>setTimeout(r,700));
 if(await evaluate('location.pathname === "/login"')) throw new Error('Test admin login failed');
 const adminPaths=['/events/create',new URL(event).pathname+'/edit','/tournaments/create','/admin/users','/admin/api-token',base,base+'/edit',bracket,...JSON.parse(process.env.UI_EXTRA_ADMIN_PATHS || '[]')];
 for(const width of [320,390,768,820,1024,1366])for(const path of adminPaths)await audit(path,width,'admin');
}
const issues=reports.filter(r=>r.body>r.width+1||r.overlaps);
if(process.env.UI_REPORT_PATH) await fs.writeFile(process.env.UI_REPORT_PATH,JSON.stringify({reports,exceptions},null,2));
console.log(JSON.stringify({pages:reports.length,issues,exceptions}));
ws.close();
if(issues.length || exceptions.length) process.exitCode=1;
