/* BUFF V18: small, finite feedback. Payment/event IDs, never animation frames, identify gifts. */
(() => {
  'use strict';
  if(window.BUFFMotion?.version===18)return;
  const reduced=()=>document.documentElement.dataset.reduceMotion==='1'||window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const t=value=>window.LogiI18n?.translatePhrase?.(value)||value;
  const layers=new WeakMap();
  function playGift(layer,event){
    const id=Number(event?.eventId||event?.id);
    if(!layer||!Number.isSafeInteger(id)||id<1)return false;
    let state=layers.get(layer);if(!state){state={queue:[],seen:new Set(),playing:false};layers.set(layer,state);}
    const key=`${event.kind||'stream'}:${id}`;if(state.seen.has(key))return false;state.seen.add(key);
    // Keep deduplication for this page's lifetime; never re-admit old IDs when the list grows.
    // A busy stream must not accumulate minutes of stale visual notifications.
    if(document.hidden||state.queue.length>=5)return false;
    state.queue.push({...event,queuedAt:Date.now()});next(layer,state);return true;
  }
  function next(layer,state){
    if(state.playing)return;
    while(state.queue.length&&(document.hidden||Date.now()-state.queue[0].queuedAt>12000))state.queue.shift();
    const event=state.queue.shift();if(!event)return;
    state.playing=true;const scene=document.createElement('div');scene.className='buff-gift-scene v18-gift-toast';
    scene.dataset.giftEvent=String(event.eventId||event.id);scene.setAttribute('role','status');scene.setAttribute('aria-live','polite');
    scene.innerHTML=`<div class="v18-gift-icon" aria-hidden="true">${esc(event.giftIcon||'🎁')}</div><div class="v18-gift-copy"><small class="user-content">${esc(event.senderName||t('İzleyici'))}</small><strong>${esc(event.giftName||t('Hediye'))} <span>×${Math.max(1,Number(event.quantity)||1)}</span></strong><em>${Number(event.totalPrice||0).toLocaleString(document.documentElement.lang||'tr')} coin</em></div>`;
    let audio;let finished=false;const finish=()=>{if(finished)return;finished=true;try{audio?.pause();}catch{}scene.remove();state.playing=false;next(layer,state);};
    // The watchdog is registered before media work, so playback errors cannot strand the queue.
    setTimeout(finish,reduced()?1500:2500);
    try{layer.append(scene);requestAnimationFrame(()=>scene.classList.add('is-playing'));
      if(event.audioUrl){audio=new Audio(event.audioUrl);audio.volume=.7;audio.play()?.catch(()=>{});}
    }catch{finish();}
  }
  const observer='IntersectionObserver'in window?new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add('buff-visible');observer.unobserve(entry.target);}}),{threshold:.01}):null;
  function refresh(root=document){
    if(!observer||reduced())return;
    root.querySelectorAll?.('main > .content-section, .v17-post').forEach(el=>{if(el.dataset.buffObserved||el.getBoundingClientRect().top<innerHeight)return;el.dataset.buffObserved='1';el.classList.add('buff-reveal');observer.observe(el);});
  }
  window.BUFFMotion={version:18,playGift,refresh};
  document.addEventListener('DOMContentLoaded',()=>{document.body.classList.add('buff-motion-ready');refresh();},{once:true});
})();
