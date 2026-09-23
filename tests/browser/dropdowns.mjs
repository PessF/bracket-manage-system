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


// Run against an app with built assets; no database writes are performed.
import { readFileSync } from 'node:fs';
await call('Page.enable'); await call('Runtime.enable');
const fixture = `<form id="dropdown-form"><label for="event">Event</label>
<select id="event" name="event" required><option value="">Choose event</option><option value="a">Alpha</option><option value="b">Beta</option><option value="x" disabled>Unavailable</option></select>
<button id="after" type="button">Next control</button>
<label for="status">Status</label><select id="status" name="status" disabled><option>Draft</option></select>
<div id="live"><select id="round" name="round"><option>Round 1</option></select></div></form>`;
const component = readFileSync(new URL('../../public/assets/js/smart-select-fallback.js', import.meta.url), 'utf8');
const css = ['ui.css','responsive.css','motion.css'].map(name => readFileSync(new URL('../../resources/css/'+name, import.meta.url), 'utf8')).join('\n');
for (const mode of ['bundled', 'fallback']) {
  await navigate('/login');
  if (mode === 'fallback') {
    await call('Page.navigate', {url:'about:blank'});
    await new Promise(r=>setTimeout(r,150));
    await evaluate(`document.head.innerHTML='<style>'+${JSON.stringify(css)}+'</style>'; document.body.dataset.theme='easykids'; document.body.innerHTML=${JSON.stringify(fixture)}; ${component}`);
  } else {
    await evaluate(`document.querySelector('main').innerHTML=${JSON.stringify(fixture)}; document.dispatchEvent(new CustomEvent('easykids:live-content-updated'));`);
  }
  const result = await evaluate(`(async () => {
    const assert = (value, message) => {if(!value) throw new Error(message)};
    const delay = () => new Promise(r=>setTimeout(r,30));
    const select = document.querySelector('#event');
    const trigger = select.parentElement.querySelector('.smart-select-trigger');
    const menu = document.getElementById(trigger.getAttribute('aria-controls'));
    const key = (element, value) => element.dispatchEvent(new KeyboardEvent('keydown',{key:value,bubbles:true,cancelable:true}));
    assert(document.querySelectorAll('select:not(.native-select-enhanced)').length===0,'Unenhanced select');
    assert(trigger.getAttribute('aria-label')==='Event','Missing label');
    document.querySelector('label[for=event]').click();
    assert(document.activeElement===trigger,'Label does not focus custom trigger');
    assert(select.tabIndex===-1,'Duplicate native tab stop');
    assert(!document.querySelector('#dropdown-form').reportValidity(),'Required field accepted empty value');
    assert(document.activeElement===trigger && trigger.getAttribute('aria-invalid')==='true','Validation focus lost');
    let changes=0; select.addEventListener('change',()=>changes++);
    key(trigger,'ArrowDown'); await delay();
    assert(menu.classList.contains('open'),'Menu did not open');
    key(document.activeElement,'ArrowDown'); key(document.activeElement,'Enter');
    assert(select.value==='a' && changes===1 && trigger.textContent==='Alpha','Keyboard selection failed');
    assert(new FormData(document.querySelector('#dropdown-form')).get('event')==='a','Submission value lost');
    trigger.click(); await delay(); key(document.activeElement,'Escape');
    assert(!menu.classList.contains('open') && document.activeElement===trigger,'Escape focus failed');
    key(trigger,'b'); assert(select.value==='b','Typeahead failed');
    select.options[2].disabled=true; await delay();
    assert(menu.children[2].hidden,'Disabled option not synchronized');
    select.add(new Option('Gamma','g')); await delay();
    assert(menu.children.length===5,'New options missing');
    const disabled=document.querySelector('#status'); disabled.disabled=false; await delay();
    assert(!disabled.parentElement.querySelector('button').disabled,'Disabled state stale');
    document.querySelector('#dropdown-form').reset(); await delay();
    assert(select.value==='' && trigger.textContent==='Choose event','Form reset stale');
    const oldId=document.querySelector('#round').parentElement.querySelector('button').getAttribute('aria-controls');
    document.querySelector('#live').innerHTML='<select id="round"><option>Round 2</option></select>';
    document.dispatchEvent(new CustomEvent('easykids:live-content-updated')); await delay();
    assert(!document.getElementById(oldId),'Orphaned live menu');
    assert(document.querySelector('#round').parentElement.querySelector('button').textContent==='Round 2','Live select not enhanced');
    document.dispatchEvent(new CustomEvent('easykids:live-content-updated'));
    assert(document.querySelectorAll('.smart-select').length===3 && document.querySelectorAll('.smart-select-popover').length===3,'Duplicate enhancement');
    trigger.click(); await delay();
    const styles=getComputedStyle(menu);
    assert(styles.animationName==='surface-enter','Shared animation missing');
    assert(getComputedStyle(trigger).color==='rgb(231, 237, 244)' && getComputedStyle(trigger).backgroundColor==='rgb(12, 18, 25)','Reference trigger theme differs');
    assert(styles.backgroundColor==='rgb(17, 24, 32)' && styles.boxShadow==='none','Reference popover theme differs');
    return 'labels, required validation, keyboard, form values, reset, option mutations, disabled states and live replacement passed';
  })()`);
  console.log(mode+': '+result);
  await call('Emulation.setDeviceMetricsOverride',{width:375,height:812,deviceScaleFactor:1,mobile:true});
  await new Promise(r=>setTimeout(r,200));
  await evaluate(`const mobileTrigger=document.querySelector('#event').parentElement.querySelector('button'); if(mobileTrigger.getAttribute('aria-expanded')!=='true') mobileTrigger.click();`);
  const fits=await evaluate(`(()=>{const r=document.querySelector('.smart-select-popover.open').getBoundingClientRect();return r.left>=0 && r.right<=innerWidth;})()`);
  if(!fits) throw new Error('Mobile menu overflows viewport');
  await call('Emulation.clearDeviceMetricsOverride');
}
if(exceptions.length) throw new Error(exceptions.join('\n'));
console.log('Desktop and mobile dropdown checks passed.');
ws.close();
