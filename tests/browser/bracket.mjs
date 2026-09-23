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
  await call('Page.navigate',{url:'about:blank'});
  await new Promise(r=>setTimeout(r,100));
  await call('Page.navigate',{url:origin+path});
  await new Promise(r=>setTimeout(r,300));
  for(let i=0;i<60;i++) {
    await new Promise(r=>setTimeout(r,100));
    if(await evaluate('document.readyState === "complete"')) break;
  }
  await evaluate('document.fonts.ready.then(()=>true)');
};

await call('Page.enable'); await call('Runtime.enable');
const assert = (condition, message) => { if (!condition) throw new Error(message); };
await navigate('/events');
const eventUrl = await evaluate('document.querySelector(".tournament-card").getAttribute("href")');
await navigate(new URL(eventUrl).pathname);
const bracketUrl = await evaluate('document.querySelector(".tournament-card").getAttribute("href")');
await navigate(new URL(bracketUrl).pathname);
await evaluate('document.querySelector("[data-bracket-zoom-reset]").click()');
const audit = async () => evaluate(`(() => {
 const errors=[];
 const close=(a,b)=>Math.abs(a-b)<1;
 const nodes=[...document.querySelectorAll('.bracket-match-node')];
 const size=nodes[0];
 for(const node of nodes) {
  if(!close(node.offsetWidth,size.offsetWidth)||!close(node.offsetHeight,size.offsetHeight)) errors.push('Unequal card size');
  if(node.scrollHeight>node.clientHeight+1||node.scrollWidth>node.clientWidth+1) errors.push('Card overflow');
  for(const name of node.querySelectorAll('.bracket-team-name span')) {
   if(name.scrollWidth>name.clientWidth+1||getComputedStyle(name).textOverflow==='ellipsis') errors.push('Clipped name');
  }
 }
 let edgeCount=0;
 for(const canvas of document.querySelectorAll('[data-bracket-canvas]')) {
  const groups=[...canvas.querySelectorAll('.bracket-round-group')];
  let lastLeft=null,center=null;
  for(const group of groups) {
   const cards=[...group.querySelectorAll('.bracket-match-node')];
   const left=cards[0].offsetLeft;
   if(lastLeft!==null&&!close(left-lastLeft,size.offsetWidth+112)) errors.push('Unequal round gap');
   lastLeft=left;
   const middle=(cards[0].offsetTop+cards.at(-1).offsetTop+size.offsetHeight)/2;
   if(center!==null&&!close(middle,center)) errors.push('Round not centered');
   center=middle;
   cards.forEach((node,index)=>{
    if(!close(node.offsetLeft,left)) errors.push('Jagged round');
    if(index&&!close(node.offsetTop-cards[index-1].offsetTop,size.offsetHeight+40)) errors.push('Unequal match gap');
   });
  }
  const paths=[...canvas.querySelectorAll('.bracket-connector')];
  const localNodes=[...canvas.querySelectorAll('.bracket-match-node')];
  const ids=new Set(localNodes.map(node=>node.dataset.matchId));
  const expected=localNodes.reduce((sum,node)=>sum+[node.dataset.winnerNext,node.dataset.loserNext].filter(id=>ids.has(id)).length,0);
  if(paths.length!==expected) errors.push('Missing progression edges');
  for(const path of paths) {
   edgeCount++;
   if(getComputedStyle(path.parentElement).display==='none'||getComputedStyle(path).strokeWidth!=='1px') errors.push('Invisible or heavy connector');
   const source=localNodes.find(node=>node.dataset.matchId===path.dataset.source);
   const target=localNodes.find(node=>node.dataset.matchId===path.dataset.target);
   const start=path.getPointAtLength(0),end=path.getPointAtLength(path.getTotalLength());
   if(!close(start.x,source.offsetLeft+source.offsetWidth)||!close(start.y,source.offsetTop+source.offsetHeight/2)) errors.push('Wrong source endpoint');
   if(!close(end.x,target.offsetLeft)||!close(end.y,target.offsetTop+target.offsetHeight/2)) errors.push('Wrong target endpoint');
  }
 }
 if(document.body.scrollWidth>innerWidth+1) errors.push('Page overflow');
 return {errors,edgeCount,width:size.offsetWidth,height:size.offsetHeight};
})()`);
for(const width of [320,390,768,1366]) {
 await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
 await new Promise(resolve=>setTimeout(resolve,300));
 const result=await audit();
 assert(result.edgeCount>0, 'Fixture must have connector lines');
 assert(!result.errors.length, width+': '+JSON.stringify(result));
}
await evaluate(`(() => {
 document.querySelector('.bracket-team-name span').textContent='ชื่อทีมผู้เข้าแข่งขันที่ยาวมาก Long participant name '.repeat(8);
 const node=document.querySelector('.bracket-match-node');
 const actions=document.createElement('div'); actions.className='bracket-card-actions';
 const button=document.createElement('button'); button.className='btn';button.textContent='Record result';actions.append(button);node.append(actions);
 document.dispatchEvent(new CustomEvent('easykids:live-content-updated',{detail:{target:document.querySelector('[data-live-bracket]')}}));
})()`);
const longNames=await audit();
assert(!longNames.errors.length, 'Long names / actions: '+JSON.stringify(longNames));
await evaluate('document.querySelector("[data-bracket-zoom-out]").click()');
assert(await evaluate('document.querySelector("[data-bracket-zoom-level]").textContent==="80%"'), 'Zoom must change one step after repeated layout');
assert(!(await audit()).errors.length, 'Zoom must preserve layout');
await evaluate('document.querySelector("[data-bracket-zoom-reset]").click()');
assert(exceptions.length===0, 'Browser errors: '+exceptions.join(', '));
console.log('Uniform dimensions, symmetric spacing, 1px center connectors, long names, mobile, live refresh and zoom passed.');
ws.close();
