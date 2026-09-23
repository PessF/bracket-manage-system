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
assert(await evaluate('!!document.querySelector("input[name=participant]")'), 'Event filter missing');
const bracketUrl = await evaluate('document.querySelector(".tournament-card").getAttribute("href")');
await navigate(new URL(bracketUrl).pathname);
await new Promise(resolve => setTimeout(resolve, 350));
const name = await evaluate('JSON.parse(document.querySelector("[data-participant-search]").dataset.participantSearch)[0]');
const searchFor = async text => evaluate(`(() => {
 const input=document.querySelector('[data-bracket-search-input]');
 input.value=${JSON.stringify(text)}; input.dispatchEvent(new Event('input', {bubbles:true}));
 return document.querySelectorAll('.is-search-match').length;
})()`);
assert(await searchFor(name) > 0, 'Team search must highlight slots');
await evaluate(`document.querySelector('[data-bracket-search-next]').click()`);
assert(await evaluate('document.querySelectorAll(".is-search-current").length === 1'), 'Next must locate a slot');
await evaluate(`(() => {
 const region=document.querySelector('[data-live-bracket]');
 region.querySelectorAll('.is-search-match').forEach(node => node.classList.remove('is-search-match'));
 document.dispatchEvent(new CustomEvent('easykids:live-content-updated',{detail:{target:region}}));
})()`);
assert(await evaluate('document.querySelectorAll(".is-search-match").length > 0'), 'Refresh must restore highlights');
assert(await searchFor('no-such-participant-987654') === 0, 'Unknown name must clear matches');
assert(await evaluate('document.querySelector("[data-bracket-search-next]").disabled'), 'No matches must disable next');
await searchFor(name);
await evaluate('document.querySelector("[data-bracket-search-clear]").click()');
assert(await evaluate('document.querySelectorAll(".is-search-match").length === 0 && document.activeElement.matches("[data-bracket-search-input]")'), 'Clear must reset and focus input');
// Individual member names are indexed independently, including Thai and literal punctuation.
await evaluate(`document.querySelector('[data-participant-search]').dataset.participantSearch=JSON.stringify(['Team','สมใจ <Alice> 100%_'])`);
assert(await searchFor('สมใจ <alice> 100%_') === 1, 'Member search must match literal Unicode names');
for (const width of [320, 390, 768, 1366]) {
 await call('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
 await new Promise(resolve => setTimeout(resolve, 100));
 assert(await evaluate('document.body.scrollWidth <= innerWidth + 1'), `Overflow at ${width}px`);
}
await call('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});
assert(await evaluate('parseFloat(getComputedStyle(document.querySelector("[data-bracket-search-next]")).transitionDuration) < 0.001'), 'Reduced motion must disable transitions');
await navigate(new URL(eventUrl).pathname+'?participant='+encodeURIComponent(name));
assert(await evaluate('document.querySelectorAll(".tournament-card").length > 0'), 'Event team filter must return competition');
await navigate(new URL(eventUrl).pathname+'?participant=no-such-participant-987654');
assert(await evaluate('document.querySelectorAll(".tournament-card").length === 0'), 'Event filter must hide unrelated competitions');
assert(exceptions.length === 0, 'Browser errors: '+exceptions.join(', '));
console.log('Search, live refresh, navigation, Unicode, mobile layout, and reduced motion checks passed.');
ws.close();
