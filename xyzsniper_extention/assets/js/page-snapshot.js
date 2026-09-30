const SniperSnapshot = {
  number(text, locale) {
    const rounded = /\d[\d.,]*\s*(?:rb|ribu|jt|juta|k|m)\b/i.test(text);
    const unit = /^(Rp|IDR)\s*/i.test(text) ? 'IDR' : /%$/.test(text) ? 'percent' : null;
    let numeric = text.replace(/^(Rp|IDR)\s*/i, '').replace(/%$/, '').trim();
    if (rounded) return {value_decimal:null,unit,interpretation:'rounded_display'};
    if (/^id\b/i.test(locale) && /^-?(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d+)?$/.test(numeric)) numeric=numeric.replace(/\./g,'').replace(',','.');
    else if (!/^-?\d+$/.test(numeric)) return {value_decimal:null,unit,interpretation:'unverified'};
    return {value_decimal:numeric,unit,interpretation:'display_value'};
  },
  visible(element, allowDate = false) {
    if (element.closest('textarea,[contenteditable],[data-sniper-private],script,style,noscript') || (!allowDate && element.closest('input'))) return false;
    const style=getComputedStyle(element);
    return style.display!=='none' && style.visibility!=='hidden' && element.getClientRects().length>0;
  },
  text(element, limit=600) {
    if (!element) return '';
    const walker=document.createTreeWalker(element,NodeFilter.SHOW_TEXT);
    let node, result='', visits=0;
    while ((node=walker.nextNode()) && visits++<2000 && result.length<limit) {
      if (node.parentElement && this.visible(node.parentElement)) result+=' '+node.textContent.trim();
    }
    return result.replace(/\s+/g,' ').trim().slice(0,limit);
  },
  collect(reason) {
    const root=document.querySelector('main,[role="main"]')||document.body;
    if(!root)return null;
    const metrics=[],lines=[];let textSize=0,visited=0,truncated=false;
    const walker=document.createTreeWalker(root,NodeFilter.SHOW_TEXT);
    let node;
    while((node=walker.nextNode())){
      if(++visited>20000){truncated=true;break;}
      const element=node.parentElement,text=node.textContent.replace(/\s+/g,' ').trim();
      if(!text || !element || !this.visible(element))continue;
      if(textSize+text.length>60000){truncated=true;break;}
      lines.push(text);textSize+=text.length;
      if(/\d/.test(text) && metrics.length<200 && text.length<=160){
        const container=element.closest('tr,[role="row"],dl,section,article')||element.parentElement;
        const context=this.text(container);
        const label=this.text(element.previousElementSibling,160)||this.text(element.parentElement?.previousElementSibling,160)||lines.at(-2)||'';
        const nearby = lines.slice(-5).join(' · ');
        metrics.push({raw_text:text,label:label||null,context:context||nearby,nearby_text:nearby,source:'dom',...this.number(text,document.documentElement.lang||navigator.language),label_status:'candidate',evidence_index:lines.length-1});
      }
    }
    const filters=Array.from(document.querySelectorAll('select,input[type="date"],input[type="month"],[aria-pressed="true"],[role="tab"][aria-selected="true"]')).filter(el=>this.visible(el,el.matches('input[type="date"],input[type="month"]'))).slice(0,40).map(el=>({label:el.getAttribute('aria-label')||this.text(el.labels?.[0],160)||el.name||null,value:el.tagName==='SELECT'?el.selectedOptions[0]?.textContent:el.value||this.text(el,160)}));
    return {title:document.title,url:location.href,observed_at:new Date().toISOString(),locale:document.documentElement.lang||navigator.language,timezone:Intl.DateTimeFormat().resolvedOptions().timeZone,headings:Array.from(root.querySelectorAll('h1,h2,h3')).filter(el=>this.visible(el)).slice(0,30).map(el=>this.text(el,160)),filters,text:lines.join('\n'),metrics,metrics_truncated:metrics.length>=200,loading:!!document.querySelector('[aria-busy="true"],[role="progressbar"]'),truncated,reason,coverage:'visible_dom_only',adapter:'generic-v1'};
  }
};
