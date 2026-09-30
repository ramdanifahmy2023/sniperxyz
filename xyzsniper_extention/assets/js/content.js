(() => {
  const navigationId=`nav_${crypto.randomUUID()}`;
  let enabled=false, sequence=0, lastSignature='', timer, lastSnapshot=0, lastUrl=location.href;
  async function send(data){
    try{
      const result=await chrome.runtime.sendMessage({type:'XYZ_SNIPER_CAPTURED_REQUEST',data:SniperPrivacy.redact(data),navigation_id:navigationId,pageUrl:location.href});
      if(result?.queued===false && !['capture_paused','domain_or_token_missing'].includes(result.reason)) console.warn('Sniper: event tidak tersimpan:',result.reason);
      return result;
    }catch(error){console.debug('Sniper: refresh halaman setelah pembaruan ekstensi.',error.message);}
  }
  function event(source,data){return {event_id:`evt_${crypto.randomUUID()}`,sequence_no:++sequence,source_type:source,request_url:location.href,method:'GET',occurred_at:Date.now(),responseBody:data,responseBodyFormat:'json',content_type:'application/json',page_title:document.title};}
  async function snapshot(reason='page_change',force=false){
    if(!enabled || document.visibilityState==='hidden')return;
    const data=SniperSnapshot.collect(reason);if(!data)return;
    const signature=JSON.stringify([data.url,data.text,data.filters,data.loading]);
    if(!force && signature===lastSignature)return;
    lastSnapshot=Date.now();
    const result=await send({...event('snapshot',data),truncated:data.truncated,capture_reason:reason});
    if(result?.queued)lastSignature=signature;
    return result;
  }
  function schedule(reason){clearTimeout(timer);timer=setTimeout(()=>void snapshot(reason),Math.max(800,3000-(Date.now()-lastSnapshot)));}
  async function policy(){
    try{
      const result=await chrome.runtime.sendMessage({type:'XYZ_SNIPER_POLICY'});
      const previous=enabled;enabled=result.enabled===true;
      window.postMessage({source:'XYZ_SNIPER_CONTROL',enabled},'*');
      if(enabled&&!previous){lastSignature='';schedule('recording_started');}
    }catch(_){enabled=false;window.postMessage({source:'XYZ_SNIPER_CONTROL',enabled:false},'*');}
  }
  window.addEventListener('message',message=>{
    if(message.source===window && message.data?.source==='XYZ_SNIPER_READY')window.postMessage({source:'XYZ_SNIPER_CONTROL',enabled},'*');
    if(message.source===window && message.data?.source==='XYZ_SNIPER_INTERCEPT' && enabled)void send(message.data.payload);
  });
  chrome.storage.onChanged.addListener(changes=>{if(changes.captureEnabled||changes.activeProject||changes.apiToken)void policy();});
  chrome.runtime.onMessage.addListener((message,sender,respond)=>{
    if(message.type==='XYZ_SNIPER_SNAPSHOT_NOW'){void snapshot('manual',true).then(result=>respond({ok:result?.queued===true}));return true;}
  });
  function start(){
    new MutationObserver(()=>{if(enabled)schedule('page_change');}).observe(document.body,{subtree:true,childList:true,characterData:true,attributes:true,attributeFilter:['aria-busy','aria-selected','aria-pressed']});
    document.addEventListener('click',e=>{
      if(!enabled)return;
      const target=e.target.closest('button,a,[role="tab"]');
      if(!target || target.closest('[data-sniper-private]'))return;
      const label=(target.getAttribute('aria-label')||target.innerText||'').trim().slice(0,160);
      if(label)void send(event('interaction',{action:'click',label,url:location.href}));
      schedule('interaction');
    },true);
    document.addEventListener('change',()=>{if(enabled)schedule('filter_change');},true);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)schedule('visible');});
    schedule('page_loaded');
  }
  if(document.body)start();else document.addEventListener('DOMContentLoaded',start,{once:true});
  void policy();
  setInterval(()=>{if(lastUrl!==location.href){lastUrl=location.href;void policy();schedule('navigation');}else if(enabled)schedule('periodic');},5000);
})();
