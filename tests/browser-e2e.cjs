const {chromium}=require('playwright');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict');
const {spawn,execFileSync}=require('node:child_process');
const root=path.resolve(__dirname,'..'),temp=fs.mkdtempSync(path.join(os.tmpdir(),'sniper-e2e-'));
const appUrl='http://127.0.0.1:8199';
let context,php,seed,fixture;
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
async function until(work,timeout=15000){const start=Date.now();while(Date.now()-start<timeout){const result=await work();if(result)return result;await sleep(250);}throw new Error('Timed out waiting for fixture data');}
(async()=>{
 try{
  fixture=http.createServer((req,res)=>{
   if(req.url==='/api'){res.setHeader('content-type','application/vnd.api+json');res.end(JSON.stringify({sales:1234,orders:0}));return;}
   if(req.url==='/app.js'){res.setHeader('content-type','text/javascript');res.end(`document.querySelector('button').onclick=()=>fetch('/api').then(r=>r.json()).then(()=>{document.querySelector('#sales').textContent='Rp2.345,60';});`);return;}
   res.setHeader('content-type','text/html');res.setHeader('content-security-policy',"script-src 'self'");
   res.end('<!doctype html><html lang="id"><title>Business Insights fixture</title><body><main><h1>Ringkasan Bisnis fixture</h1><label>Periode<select><option>Hari ini</option><option>Kemarin</option></select></label><article><h2>Penjualan</h2><span id="sales">Rp1.234,50</span></article><article><h2>Pesanan</h2><span>0</span></article><button>Perbarui angka</button><div data-sniper-private>PRIVATE_FIXTURE_TEXT</div></main><script src="/app.js"></script></body></html>');
  });
  await new Promise(r=>fixture.listen(0,'127.0.0.1',r));const fixtureOrigin=`http://127.0.0.1:${fixture.address().port}`;
  seed=JSON.parse(execFileSync('php',['tests/e2e-db.php',path.join(temp,'.env'),fixtureOrigin,appUrl],{cwd:root,encoding:'utf8'}));
  const log=fs.openSync(path.join(temp,'php.log'),'w');
  php=spawn('php',['-S','127.0.0.1:8199','-t','xyzsniper/public','xyzsniper/router.php'],{cwd:root,env:{...process.env,SNIPER_ENV_FILE:path.join(temp,'.env')},stdio:['ignore',log,log]});
  await until(async()=>{try{return (await fetch(appUrl+'/api')).ok;}catch(_){return false;}});
  const ext=path.join(temp,'extension');fs.cpSync(path.join(root,'xyzsniper_extention'),ext,{recursive:true,filter:p=>!path.basename(p).startsWith('._')});
  fs.writeFileSync(path.join(ext,'assets/js/config.js'),`const CONFIG={getBaseUrl:()=>${JSON.stringify(appUrl)},getApiUrl:name=>${JSON.stringify(appUrl)}+'/api/'+name};`);
  context=await chromium.launchPersistentContext(path.join(temp,'profile'),{channel:'chromium',headless:true,args:[`--disable-extensions-except=${ext}`,`--load-extension=${ext}`]});
  let worker=context.serviceWorkers()[0]||await context.waitForEvent('serviceworker');
  await worker.evaluate(({token,domain})=>chrome.storage.local.set({apiToken:token,activeProject:{id:1,title:'Fixture',domain},captureEnabled:true}),{token:seed.token,domain:fixtureOrigin});
  const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto(fixtureOrigin);
  async function tool(name,args){const response=await fetch(appUrl+'/mcp',{method:'POST',headers:{'Content-Type':'application/json',Authorization:`Bearer ${seed.token}`},body:JSON.stringify({jsonrpc:'2.0',id:1,method:'tools/call',params:{name,arguments:args}})});const result=await response.json();if(result.error)throw new Error(JSON.stringify(result.error));return result.result.structuredContent;}
  const session=await until(async()=>{const data=await tool('get_sessions',{project_id:1});return data.items[0];});
  const snapshots=await until(async()=>{const data=await tool('get_page_snapshots',{session_id:session.session_id});return data.items.length?data.items:false;});
  const metrics=await tool('get_dashboard_metrics',{event_id:snapshots[0].event_id});assert.ok(metrics.items.some(m=>m.raw_text==='0'));assert.ok(metrics.items.some(m=>m.raw_text==='Rp1.234,50'&&m.value_decimal==='1234.50'));assert.equal(metrics.filters[0].value,'Hari ini');
  console.log('PASS actual MV3 extension -> API -> database -> MCP snapshot under restrictive CSP');
  await page.getByRole('button',{name:'Perbarui angka'}).click();
  await until(async()=>{const events=await tool('get_session_events',{session_id:session.session_id});return events.items.some(e=>e.source_type==='fetch');},45000).catch(async error=>{console.error({pageErrors:errors,collector:await page.evaluate(()=>({installed:!!window.__sniperCollectorV2,wrapped:fetch.toString().includes('nativeFetch')})),events:await tool('get_session_events',{session_id:session.session_id}),queue:await worker.evaluate(()=>queue.all())});throw error;});
  const data=await tool('search_traffic',{keyword:'1234',session_id:session.session_id});assert.ok(data.items.some(e=>e.response_body?.sales===1234));console.log('PASS live fetch vendor JSON is searchable through MCP');
  const extensionId=worker.url().split('/')[2];const popup=await context.newPage();popup.on('pageerror',e=>errors.push(e.message));await popup.goto(`chrome-extension://${extensionId}/index.html`);await popup.getByRole('button',{name:'Jeda',exact:true}).waitFor();await page.bringToFront();await popup.evaluate(()=>document.getElementById('snapshot-now-btn').click());await until(async()=> (await popup.locator('#capture-feedback').textContent()).includes('Snapshot diminta'));await popup.getByRole('button',{name:'Jeda',exact:true}).click();await popup.getByRole('button',{name:'Lanjutkan',exact:true}).waitFor();
  await popup.getByRole('button',{name:'Lanjutkan',exact:true}).click();await popup.getByRole('button',{name:'Akhiri sesi',exact:true}).click();
  await until(async()=>{const state=await worker.evaluate(()=>chrome.storage.local.get('captureSession'));return !state.captureSession;});console.log('PASS popup pause/resume/end controls');
  const web=await context.newPage();web.on('pageerror',e=>errors.push(e.message));await web.goto(appUrl+'/auth');await web.locator('input[name="email"]').fill('fixture@example.test');await web.locator('input[name="password"]').fill('Sniper-fixture-only-2026');await web.locator('button[type="submit"]').click();await web.waitForURL('**/panel');
  await web.goto(appUrl+'/panel/session/'+session.session_id);await web.getByRole('heading',{name:'Timeline Audit'}).waitFor();await web.goto(appUrl+'/panel/event/'+snapshots[0].event_id);await web.getByRole('heading',{name:'Detail rekaman'}).waitFor();assert.ok((await web.textContent('main')).includes('Rp1.234,50'));
  for(const width of [375,1280]){await web.setViewportSize({width,height:900});await web.reload();await web.getByRole('heading',{name:'Detail rekaman'}).waitFor();await web.screenshot({path:path.join(temp,`event-${width}.png`),fullPage:true});assert.equal(await web.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,JSON.stringify(await web.evaluate(()=>Array.from(document.querySelectorAll('main,main *')).filter(el=>el.getBoundingClientRect().right>innerWidth).slice(0,12).map(el=>({tag:el.tagName,cls:el.className,width:el.getBoundingClientRect().width})))));}
  await web.screenshot({path:path.join(temp,'event-light.png'),fullPage:true});
  await web.locator('summary').first().focus();await web.keyboard.press('Enter');assert.equal(await web.locator('details').first().getAttribute('open'),'');
  await web.locator('#panel-theme-select').selectOption('dark');assert.ok(await web.locator('html').evaluate(el=>el.classList.contains('dark')));
  await web.screenshot({path:path.join(temp,'event-dark.png'),fullPage:true});
  assert.equal(await web.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
  console.log('PASS keyboard detail disclosure and dark theme');
  const exportResult=await context.request.get(appUrl+'/panel/export_session/'+session.session_id);const exported=await exportResult.json();assert.equal(exported.manifest.complete,true);assert.equal(exported.events.length,exported.manifest.event_count);assert.ok(!JSON.stringify(exported).includes('PRIVATE_FIXTURE_TEXT'));
  console.log('PASS web event detail, narrow/desktop layout and complete export');
  await worker.evaluate(async()=>{globalThis.fetch=async()=>{throw new TypeError('fixture offline');};await chrome.storage.local.set({captureEnabled:true});});
  await page.bringToFront();await page.getByRole('button',{name:'Perbarui angka'}).click();
  const queued=await until(async()=>{const rows=await worker.evaluate(()=>queue.all());return rows.some(row=>!row.operation&&row.lastError==='fixture offline')?rows.filter(row=>!row.operation):false;});
  await worker.evaluate(()=>chrome.storage.local.set({captureEnabled:false}));
  await context.close();context=await chromium.launchPersistentContext(path.join(temp,'profile'),{channel:'chromium',headless:true,args:[`--disable-extensions-except=${ext}`,`--load-extension=${ext}`]});
  worker=context.serviceWorkers()[0]||await context.waitForEvent('serviceworker');
  const restored=await worker.evaluate(async()=>{await ready;return queue.all();});assert.ok(queued.every(row=>restored.some(saved=>saved.event_id===row.event_id)));
  const recoveredPopup=await context.newPage();await recoveredPopup.goto(`chrome-extension://${extensionId}/index.html`);
  const downloadPromise=recoveredPopup.waitForEvent('download');await recoveredPopup.getByRole('button',{name:'Ekspor antrean',exact:true}).click();
  const download=await downloadPromise;const exportedQueue=JSON.parse(fs.readFileSync(await download.path(),'utf8'));assert.ok(exportedQueue.items.some(row=>row.event_id===queued[0].event_id));
  await recoveredPopup.getByRole('button',{name:'Coba kirim ulang',exact:true}).click();
  await until(async()=>!(await worker.evaluate(()=>queue.all())).some(row=>!row.operation));
  const recovered=await tool('get_event',{event_id:queued[0].event_id});assert.equal(recovered.event_id,queued[0].event_id);
  console.log('PASS offline queue survives full browser restart and retries into MCP');
  assert.deepEqual(errors,[]);console.log('PASS no page JavaScript exceptions');console.log('Artifacts: '+temp);
 }finally{
  if(context)await context.close();if(php)php.kill();if(fixture)fixture.close();if(seed)execFileSync('php',['tests/e2e-db.php','cleanup',seed.database],{cwd:root});
  // Artifacts and logs stay outside the repository for inspection; remove secrets.
  if(fs.existsSync(path.join(temp,'.env')))fs.unlinkSync(path.join(temp,'.env'));
 }
})().catch(error=>{console.error(error);process.exitCode=1;});
