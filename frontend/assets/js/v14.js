(() => {
  'use strict';
  const apiBase = '../backend/api';
  const api = async (path, options={}) => {
    const res = await fetch(apiBase + path, { credentials:'include', ...options });
    const text = await res.text(); let data={}; try{data=text?JSON.parse(text):{};}catch{throw new Error(text||`HTTP ${res.status}`)}
    if(!res.ok) throw new Error(data.message||`HTTP ${res.status}`); return data;
  };
  const bus = new EventTarget(); let socket=null, config=null, pollTimer=null, lastEventId=0, pollPrimed=false;
  const seenRealtimeIds = new Set();
  async function getConfig(){ if(config)return config; config=await api('/config.php'); return config; }
  function emitRealtime(msg){
    const id=Number(msg?.id||0);
    if(id>0){
      if(seenRealtimeIds.has(id)) return;
      seenRealtimeIds.add(id); lastEventId=Math.max(lastEventId,id);
      if(seenRealtimeIds.size>800){const first=seenRealtimeIds.values().next().value;seenRealtimeIds.delete(first);}
    }
    bus.dispatchEvent(new CustomEvent(msg.event||'message',{detail:msg}));
    bus.dispatchEvent(new CustomEvent('message',{detail:msg}));
    window.dispatchEvent(new CustomEvent('buff:realtime',{detail:msg}));
  }
  let realtimePolling=false;
  async function pollRealtimeOnce(){
    if(realtimePolling)return;realtimePolling=true;
    try{
      const sid=streamId();
      const prime=pollPrimed?'':'&prime=1';
      const q=`?after_id=${lastEventId}${sid?`&stream_id=${sid}`:''}${prime}`;
      const d=await api('/realtime/poll.php'+q);
      lastEventId=Math.max(lastEventId,Number(d.lastId||0)); pollPrimed=true;
      for(const e of (d.events||[])) emitRealtime(e);
    }catch{}finally{realtimePolling=false;}
  }
  function startPolling(){
    if(pollTimer)return;
    pollRealtimeOnce();
    pollTimer=setInterval(pollRealtimeOnce,1200);
  }
  async function connectRealtime(){
    try{
      const cfg=await getConfig(); if(!cfg.realtimeUrl||socket?.readyState===1)return socket;
      socket=new WebSocket(cfg.realtimeUrl);
      let opened=false;const fallback=setTimeout(()=>{if(!opened)startPolling();},2200);
      socket.onopen=()=>{opened=true;clearTimeout(fallback);startPolling();};
      socket.onmessage=e=>{try{emitRealtime(JSON.parse(e.data));}catch{}};
      socket.onclose=()=>{socket=null;startPolling();setTimeout(connectRealtime,2500)}; socket.onerror=()=>{}; return socket;
    }catch{startPolling();return null;}
  }
  function subscribe(topic){ if(socket?.readyState===1)socket.send(JSON.stringify({type:'subscribe',topic}));else setTimeout(()=>subscribe(topic),600); }
  window.BUFFV14={api,bus,getConfig,connectRealtime,subscribe};

  const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const streamId=()=>Number(new URLSearchParams(location.search).get('id')||0);

  async function mountWatch(){
    const sid=streamId(); if(!sid)return;
    const center=document.querySelector('.watch-center'); if(!center)return;
    const card=document.createElement('section');card.className='v14-engagement-grid';card.innerHTML=`
      <article class="v14-engagement-card"><div class="v14-card-head"><div><span class="section-kicker">TOPLULUK HEDEFİ</span><h3>Birlikte tamamlayın</h3></div><span id="v14GoalState" class="v14-live-dot"></span></div><div id="v14GoalBox" class="v14-panel-empty">Aktif hedef yükleniyor…</div></article>
      <article class="v14-engagement-card"><div class="v14-card-head"><div><span class="section-kicker">CANLI TAHMİN</span><h3>Sadakat puanınla katıl</h3></div><strong id="v14LoyaltyBadge">0 puan</strong></div><div id="v14PredictionBox" class="v14-panel-empty">Tahmin yükleniyor…</div></article>`;
    const about=document.querySelector('.stream-about-card');(about?.parentNode||center).insertBefore(card,about||null);
    async function loadGoal(){try{const d=await api(`/goals/list.php?streamId=${sid}`);const g=d.goals?.find(x=>x.status==='active')||d.goals?.[0];const box=document.getElementById('v14GoalBox');if(!g){box.innerHTML='<span class="v14-muted">Yayıncı henüz hedef açmadı.</span>';return;}box.innerHTML=`<div class="v14-goal-title"><strong>${esc(g.title)}</strong><span>${g.currentAmount.toLocaleString('tr-TR')} / ${g.targetAmount.toLocaleString('tr-TR')} coin</span></div><div class="v14-progress"><i style="width:${Math.min(100,g.progress)}%"></i></div><small>%${g.progress} tamamlandı</small>`;}catch(e){document.getElementById('v14GoalBox').textContent=e.message;}}
    async function loadPred(){try{const d=await api(`/predictions/list.php?streamId=${sid}`);const p=d.predictions?.find(x=>x.status==='open')||d.predictions?.[0];document.getElementById('v14LoyaltyBadge').textContent=`${(d.loyalty?.points||0).toLocaleString('tr-TR')} puan`;const box=document.getElementById('v14PredictionBox');if(!p){box.innerHTML='<span class="v14-muted">Aktif tahmin yok.</span>';return;}const opts=(p.options||[]).map(o=>`<button class="v14-pred-option" data-pred="${p.id}" data-opt="${o.id}"><strong>${esc(o.label)}</strong><span>${o.pointPool.toLocaleString('tr-TR')} puan • ${o.entryCount} kişi</span></button>`).join('');box.innerHTML=`<strong class="v14-pred-question">${esc(p.question)}</strong><div class="v14-pred-options">${opts}</div>${p.mine?`<small>Katılımın: ${p.mine.points} puan</small>`:'<small>Bir seçenek seçtiğinde kullanacağın puanı soracağız.</small>'}`;box.querySelectorAll('[data-pred]').forEach(btn=>btn.onclick=async()=>{const points=Number(prompt('Kaç sadakat puanı kullanmak istiyorsun?','25')||0);if(points<1)return;try{await api('/predictions/enter.php',{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify({predictionId:Number(btn.dataset.pred),optionId:Number(btn.dataset.opt),points})});await loadPred();}catch(err){alert(err.message)}});}catch(e){document.getElementById('v14PredictionBox').textContent=e.message;}}
    await Promise.all([loadGoal(),loadPred()]);await connectRealtime();subscribe(`stream.${sid}`);bus.addEventListener('community_goal.updated',loadGoal);bus.addEventListener('community_goal.created',loadGoal);bus.addEventListener('prediction.created',loadPred);bus.addEventListener('prediction.entry',loadPred);bus.addEventListener('prediction.resolved',loadPred);
  }

  async function mountStudio(){
    const settings=document.querySelector('.studio-settings-card');if(!settings)return;
    const panel=document.createElement('section');panel.className='v14-studio-interaction';panel.innerHTML=`<span class="section-kicker">ETKİLEŞİM MERKEZİ</span><h3>Tahmin & topluluk hedefi</h3><div class="v14-studio-stack">
      <form id="v14GoalForm"><label><span>Topluluk hedefi</span><input name="title" maxlength="160" placeholder="Örn. Korku oyunu fonu"></label><label><span>Hedef coin</span><input name="targetAmount" type="number" min="1" value="5000"></label><button class="btn btn-ghost btn-small">Hedefi Aç</button></form>
      <form id="v14PredForm"><label><span>Tahmin sorusu</span><input name="question" maxlength="180" placeholder="Bu turu kazanır mıyım?"></label><div class="form-row"><input name="option1" placeholder="Evet"><input name="option2" placeholder="Hayır"></div><label><span>Süre</span><select name="durationSeconds"><option value="60">1 dk</option><option value="180" selected>3 dk</option><option value="300">5 dk</option></select></label><button class="btn btn-ghost btn-small">Tahmini Aç</button></form></div><div id="v14StudioInteractionMsg" class="form-message"></div>`;
    settings.appendChild(panel);
    async function myStream(){const d=await api('/stream/my.php');if(!d.stream?.id)throw new Error('Önce yayını başlatmalısın.');return Number(d.stream.id);}
    panel.querySelector('#v14GoalForm').onsubmit=async e=>{e.preventDefault();const f=new FormData(e.currentTarget),msg=panel.querySelector('#v14StudioInteractionMsg');try{const sid=await myStream();const d=await api('/goals/create.php',{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify({streamId:sid,title:f.get('title'),targetAmount:Number(f.get('targetAmount'))})});msg.textContent=d.message;msg.className='form-message success';}catch(err){msg.textContent=err.message;msg.className='form-message error';}};
    panel.querySelector('#v14PredForm').onsubmit=async e=>{e.preventDefault();const f=new FormData(e.currentTarget),msg=panel.querySelector('#v14StudioInteractionMsg');try{const sid=await myStream();const d=await api('/predictions/create.php',{method:'POST',headers:{'content-type':'application/json'},body:JSON.stringify({streamId:sid,question:f.get('question'),options:[f.get('option1'),f.get('option2')],durationSeconds:Number(f.get('durationSeconds'))})});msg.textContent=d.message;msg.className='form-message success';}catch(err){msg.textContent=err.message;msg.className='form-message error';}};
  }

  async function mountDiscover(){
    const main=document.querySelector('.kick-main-v9');if(!main)return;
    try{const d=await api('/discover.php');if(!d.recommendedClips?.length)return;const sec=document.createElement('section');sec.className='content-section v14-discover-clips';sec.innerHTML=`<div class="section-heading"><div><span class="section-kicker">VİRAL KEŞFET</span><h2>Topluluğun aldığı klipler</h2><p>İzleme ve etkileşim geçmişine göre kişiselleştirilir.</p></div></div><div class="v14-clip-grid">${d.recommendedClips.map(c=>`<a class="v14-clip-card" href="./clips.php?clip=${c.id}"><div class="v14-clip-thumb">${c.thumbnailUrl?`<img src="${esc(c.thumbnailUrl)}" alt="">`:'<span>▶</span>'}<small>${c.durationSeconds}s</small></div><strong>${esc(c.title)}</strong><span>${esc(c.channel.displayName)} • ${c.viewCount.toLocaleString('tr-TR')} izlenme</span></a>`).join('')}</div>`;main.appendChild(sec);}catch{}
  }

  async function mountHome(){
    const form=document.getElementById('postForm');if(!form||form.querySelector('[name="visibility"]'))return;
    const actions=form.querySelector('.composer-actions');if(!actions)return;const select=document.createElement('label');select.className='v14-visibility-select';select.innerHTML='<span>Görünürlük</span><select name="visibility"><option value="public">Herkes</option><option value="members">Üyelere özel</option></select>';actions.insertBefore(select,actions.lastElementChild);
  }

  document.addEventListener('DOMContentLoaded',()=>{startPolling();connectRealtime();const page=document.body.dataset.page||'';if(page==='watch')mountWatch();if(page==='studio')mountStudio();if(location.pathname.endsWith('/discover.php'))mountDiscover();if(location.pathname.endsWith('/index.php')||location.pathname.endsWith('/frontend/'))mountHome();});
})();
