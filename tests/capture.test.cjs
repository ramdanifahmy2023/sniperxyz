const test=require('node:test'), assert=require('node:assert/strict'), vm=require('node:vm'),fs=require('node:fs');
const {IDBFactory}=require('fake-indexeddb');
const {JSDOM}=require('jsdom');
const {webcrypto}=require('node:crypto');
const source=name=>fs.readFileSync(`xyzsniper_extention/assets/js/${name}.js`,'utf8');
const tick=()=>new Promise(resolve=>setTimeout(resolve,30));
function collector(responseFactory=()=>new Response('{"ok":true}',{headers:{'content-type':'application/json'}})){
  const messages=[], calls=[], listeners={};
  class XHR{open(){}send(){}setRequestHeader(){}}
  const window={fetch:(...args)=>{calls.push(args);return Promise.resolve(responseFactory());},postMessage:message=>{if(message.payload)messages.push(message.payload);},addEventListener:(name,fn)=>listeners[name]=fn};
  const ctx={window,XMLHttpRequest:XHR,Headers,Request,Response,ReadableStream,URL,URLSearchParams,FormData,Blob,ArrayBuffer,Uint8Array,TextDecoder,TextEncoder,btoa,performance,crypto:webcrypto,location:{href:'https://example.test/'},console};
  vm.createContext(ctx);vm.runInContext(source('privacy')+'\n'+source('interceptor'),ctx);
  listeners.message({source:window,data:{source:'XYZ_SNIPER_CONTROL',enabled:true}});
  return {window,messages,calls,listeners};
}
test('vendor JSON is readable, original fetch response remains readable',async()=>{
  const c=collector(()=>new Response('{"sales":0}',{headers:{'content-type':'application/vnd.api+json'}}));
  const response=await c.window.fetch('/api');assert.equal((await response.json()).sales,0);await tick();
  assert.equal(c.messages[0].responseBodyFormat,'json');assert.equal(c.messages[0].responseBody.sales,0);
});
test('Request init override and FormData repeated fields are preserved',async()=>{
  const c=collector();await c.window.fetch(new Request('https://example.test/api',{method:'POST',body:'old'}),{method:'PUT',body:'new'});await tick();
  assert.equal(c.messages[0].method,'PUT');assert.equal(c.messages[0].requestBody,'new');
  const form=new FormData();form.append('tag','a');form.append('tag','b');form.append('password','private');
  await c.window.fetch('/form',{method:'POST',body:form});await tick();
  assert.deepEqual(JSON.parse(JSON.stringify(c.messages[1].requestBody)),[{name:'tag',value:'a'},{name:'tag',value:'b'},{name:'password',value:'[REDACTED]'}]);
});
test('stream emits before end and bounded response explicitly marks truncation',async()=>{
  let controller;const c=collector(()=>new Response(new ReadableStream({start(c){controller=c;c.enqueue(new TextEncoder().encode('data: one\n\n'));}}),{headers:{'content-type':'text/event-stream'}}));
  await c.window.fetch('/stream');await tick();assert.equal(c.messages[0].responseBodyFormat,'stream-chunk');controller.close();await tick();
  const large=collector(()=>new Response('a'.repeat(600000),{headers:{'content-type':'text/plain'}}));await large.window.fetch('/large');await tick();assert.equal(large.messages[0].truncated,true);assert.equal(large.messages[0].responseBody.length,524288);
});
test('pause bypasses collector and redacts header secrets',async()=>{
  const c=collector();await c.window.fetch('/api',{headers:{Authorization:'Bearer PRIVATE'}});await tick();assert.equal(c.messages[0].requestHeaders.authorization,'[REDACTED]');
  c.listeners.message({source:c.window,data:{source:'XYZ_SNIPER_CONTROL',enabled:false}});await c.window.fetch('/api');await tick();assert.equal(c.messages.length,1);assert.equal(c.calls.length,2);
});
function background(fetchImpl){
  const store={activeProject:{id:1,domain:'example.test',title:'Fixture'},apiToken:'fixture-token',captureEnabled:true};
  const chrome={storage:{local:{get:async()=>({...store}),set:async data=>Object.assign(store,data),remove:async keys=>{for(const key of Array.isArray(keys)?keys:[keys])delete store[key];}}},alarms:{create:async()=>{},onAlarm:{addListener(){}}},runtime:{onMessage:{addListener(fn){chrome.listener=fn;}},getURL:()=> 'chrome-extension://fixture/'}};
  const ctx={chrome,indexedDB:new IDBFactory(),crypto:webcrypto,TextEncoder,URL,AbortSignal,console,fetch:fetchImpl,CONFIG:{getApiUrl:name=>name},importScripts(){}};
  vm.createContext(ctx);vm.runInContext(source('capture-queue')+'\n'+source('privacy')+'\n'+source('background'),ctx);
  const message=(type,extra={},sender={url:'chrome-extension://fixture/index.html'})=>new Promise(resolve=>chrome.listener({type,...extra},sender,resolve));
  return {ctx,store,message,capture:data=>message('XYZ_SNIPER_CAPTURED_REQUEST',{data},{url:'https://example.test/',tab:{id:1,url:'https://example.test/'},documentId:'doc',frameId:0})};
}
const fixture=id=>({event_id:id,source_type:'fetch',url:'https://example.test/api',method:'GET',responseBody:{sales:0}});
test('server rejected events stay in failed queue; retry can recover',async()=>{
  let reject=true;
  const b=background(async(path,init)=>{const items=JSON.parse(init.body).payloads;return Response.json({status:'success',data:{results:items.map(i=>({event_id:i.event_id,status:reject?'rejected':'stored',reason:'fixture rejection'}))}});});
  assert.equal((await b.capture(fixture('evt_one'))).queued,true);await b.ctx.flush();
  assert.equal((await b.message('XYZ_SNIPER_QUEUE_STATUS')).failed,1);
  reject=false;await b.message('XYZ_SNIPER_RETRY');const state=await b.message('XYZ_SNIPER_QUEUE_STATUS');assert.equal(state.failed,0);assert.equal(state.pending,0);assert.ok(state.lastAck);
});
test('missing ACK is retried, not silently removed',async()=>{
  const b=background(async()=>Response.json({status:'success'}));await b.capture(fixture('evt_no_ack'));await b.ctx.flush();assert.equal((await b.message('XYZ_SNIPER_QUEUE_STATUS')).pending,1);
});
test('end session clears identity, resume starts new session, foreign domain is ignored',async()=>{
  const b=background(async(path,init)=>Response.json({status:'success',data:{results:(JSON.parse(init.body).payloads||[]).map(i=>({event_id:i.event_id,status:'stored'}))}}));
  await b.capture(fixture('evt_first'));const first=b.store.captureSession.session_id;await b.ctx.flush();await b.message('XYZ_SNIPER_END_SESSION');await b.ctx.flush();assert.equal(b.store.captureSession,undefined);
  await b.message('XYZ_SNIPER_SET_CAPTURE_STATE',{enabled:true});await b.capture(fixture('evt_next'));assert.notEqual(b.store.captureSession.session_id,first);await b.ctx.flush();
  const result=await b.message('XYZ_SNIPER_CAPTURED_REQUEST',{data:fixture('evt_foreign')},{url:'https://other.test',tab:{id:2,url:'https://other.test'}});assert.equal(result.queued,false);
});
test('IndexedDB concurrent writes, capacity failure and once-only migration preserve data',async()=>{
  const ctx={indexedDB:new IDBFactory(),crypto:webcrypto,TextEncoder};vm.createContext(ctx);vm.runInContext(source('capture-queue'),ctx);const q=vm.runInContext('new CaptureQueue(10000)',ctx);
  await Promise.all(Array.from({length:20},(_,i)=>q.put({queue_id:String(i),event_id:String(i),payload:{i}})));assert.equal((await q.all()).length,20);
  await assert.rejects(q.put({queue_id:'huge',payload:'a'.repeat(20000)}));assert.equal((await q.all()).length,20);
  await q.migrate([{event_id:'legacy',payload:{x:1}}]);await q.migrate([{event_id:'legacy',payload:{x:1}}]);assert.equal((await q.all()).length,21);
});
test('snapshot keeps zero, decimal display, selected period and hides private text',()=>{
  const dom=new JSDOM('<html lang="id"><body><main><h1>Business Insights fixture</h1><select aria-label="Periode"><option selected>Hari ini</option></select><article><h2>Penjualan</h2><span>Rp1.234,50</span></article><article><h2>Pesanan</h2><span>0</span></article><section hidden>999 rahasia</section><div data-sniper-private>888 rahasia</div><input type="password" value="SECRET"></main></body></html>',{url:'https://example.test/',runScripts:'outside-only'});
  dom.window.HTMLElement.prototype.getClientRects=function(){return this.hidden||this.closest('[hidden]')?[]:[{}];};
  dom.window.eval(source('page-snapshot')+'\nwindow.fixtureSnapshot=SniperSnapshot.collect("test")');
  const s=dom.window.fixtureSnapshot;assert.ok(s.metrics.some(m=>m.raw_text==='0'));assert.ok(s.metrics.some(m=>m.raw_text==='Rp1.234,50'));assert.equal(s.filters[0].value,'Hari ini');assert.ok(!s.text.includes('rahasia'));assert.ok(!s.text.includes('SECRET'));dom.window.close();
});
