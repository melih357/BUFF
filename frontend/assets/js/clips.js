document.addEventListener('DOMContentLoaded', async () => {
  const user = await LogiApp.getCurrentUser();
  const grid = document.getElementById('clipsGrid');
  const count = document.getElementById('clipCount');
  const player = document.getElementById('clipPlayer');
  const commentList = document.getElementById('clipCommentList');
  let clips = [], filter = 'all', activeClip = null, shareTargetClip = null;
  const esc = LogiApp.escapeHtml;
  const fmt = n => LogiApp.formatNumber(Number(n || 0));
  const durationText = sec => { const s=Math.max(1,Number(sec||0)); return s>=60?`${Math.floor(s/60)}:${String(s%60).padStart(2,'0')}`:`${s} sn`; };

  async function loadClips(){
    try{ const d=await LogiApp.api('/clips/list.php?limit=60'); clips=d.clips||[]; render(); const q=new URLSearchParams(location.search).get('clip'); if(q){ let c=clips.find(x=>Number(x.id)===Number(q));if(!c){const one=await LogiApp.api('/clips/list.php?id='+Number(q));c=one.clips?.[0];if(c)clips.push(c);}if(c)openClip(c); }}
    catch(e){grid.innerHTML=`<div class="compact-empty">${esc(e.message)}</div>`;}
  }
  function actionButtons(c){ return `<button data-clip-action="like" data-id="${c.id}" class="${c.liked?'active':''}">♥ <span>${fmt(c.likeCount)}</span></button><button data-clip-action="likes" data-id="${c.id}">Beğenenler</button><button data-clip-action="gift" data-id="${c.id}">🎁 Hediye</button><button data-clip-action="comment" data-id="${c.id}">💬 <span>${fmt(c.commentCount)}</span></button><button data-clip-action="share" data-id="${c.id}">↗ <span>${fmt(c.shareCount)}</span></button><button data-clip-action="repost" data-id="${c.id}" class="${c.reposted?'active':''}">⟳ <span>${fmt(c.repostCount)}</span></button>`; }
  function render(){
    const q=(document.getElementById('clipSearch')?.value||'').trim().toLowerCase();
    const list=clips.filter(c=>(filter==='all'||String(c.sourceType||'live')===filter)&&(!q||`${c.title} ${c.streamerName} ${c.username} ${c.category||''}`.toLowerCase().includes(q)));
    count.textContent=String(list.length);
    if(!list.length){grid.innerHTML='<div class="compact-empty">Bu filtrede henüz klip yok.</div>';return;}
    grid.innerHTML=list.map(c=>`<article class="media-card clip-card-v16" data-clip-id="${c.id}"><button class="media-thumb clip-play" type="button" data-clip-id="${c.id}" aria-label="Klibi oynat">${c.thumbnailUrl?`<img src="${esc(c.thumbnailUrl)}" alt="">`:'<div class="media-fallback">▶</div>'}<span class="duration-badge">${durationText(c.durationSeconds)}</span><span class="clip-source-badge">${c.sourceType==='upload'?'YÜKLEME':'CANLI KLİP'}</span></button><div class="media-card-body"><div class="media-card-top"><strong>${esc(c.title)}</strong><span>◉ ${fmt(c.viewCount)}</span></div><a href="./channel.php?username=${encodeURIComponent(c.username)}">${esc(c.streamerName)}</a><small>${esc(c.category||'Topluluk')} • ${esc(LogiApp.timeAgo(c.createdAt))}${c.clipperName?` • ${esc(c.clipperName)}`:''}</small><div class="clip-engagement">${actionButtons(c)}</div></div></article>`).join('');
    window.BUFFMotion?.refresh?.(grid);
  }
  async function clipAction(action,id){
    if(!user){LogiApp.toast('Bu işlem için giriş yapmalısın.','error');return;}
    const c=clips.find(x=>Number(x.id)===Number(id)); if(!c)return;
    if(action==='likes')return BUFFV17.people('clip',id);
    if(action==='gift')return BUFFV17.gift('clip',id,document.getElementById('clipGiftLayer'));
    if(action==='comment'){openClip(c);setTimeout(()=>document.getElementById('clipCommentInput')?.focus(),120);return;}
    try{
      if(action==='like'){const d=await LogiApp.api('/clips/like.php',{method:'POST',body:JSON.stringify({clipId:c.id})});c.liked=d.liked;c.likeCount=d.likeCount;}
      if(action==='repost'){const d=await LogiApp.api('/clips/repost.php',{method:'POST',body:JSON.stringify({clipId:c.id})});c.reposted=d.reposted;c.repostCount=d.repostCount;LogiApp.toast(d.reposted?'Klip akışında repost edildi.':'Repost kaldırıldı.','success');}
      if(action==='share'){shareTargetClip=c;await openShareModal(c);return;}
      render(); if(activeClip?.id===c.id)renderModalActions(c);
    }catch(e){LogiApp.toast(e.message,'error');}
  }
  async function openShareModal(c){
    if(!user){LogiApp.toast('Paylaşmak için giriş yapmalısın.','error');return;}
    const root=document.getElementById('clipShareContacts');root.innerHTML='<div class="compact-empty">Kişiler yükleniyor…</div>';LogiApp.openModal('clipShareModal');
    try{const d=await LogiApp.api('/social/contacts.php');const contacts=d.contacts||[];root.innerHTML=contacts.length?contacts.map(u=>`<button class="clip-share-person" type="button" data-share-user="${u.id}">${LogiApp.avatarMarkup({displayName:u.displayName,avatarUrl:u.avatarUrl},'avatar-circle')}<span><strong>${esc(u.displayName)}</strong><small>${esc(u.publicId)} • @${esc(u.username)}</small></span><b>Gönder</b></button>`).join(''):'<div class="compact-empty">Henüz karşılıklı takip ettiğin kullanıcı yok.</div>';}catch(e){root.innerHTML=`<div class="compact-empty">${esc(e.message)}</div>`;}
  }
  document.getElementById('clipShareContacts')?.addEventListener('click',async e=>{const btn=e.target.closest('[data-share-user]');if(!btn||!shareTargetClip)return;try{btn.disabled=true;btn.querySelector('b').textContent='Gönderiliyor…';const r=await LogiApp.api('/messages/create-direct.php',{method:'POST',body:JSON.stringify({userId:Number(btn.dataset.shareUser)})});const url=`${location.origin}${location.pathname}?clip=${shareTargetClip.id}`;const fd=new FormData();fd.append('roomId',String(r.room.id));fd.append('body',`🎬 ${shareTargetClip.title}\n${url}`);await LogiApp.api('/messages/send.php',{method:'POST',body:fd});const d=await LogiApp.api('/clips/share.php',{method:'POST',body:JSON.stringify({clipId:shareTargetClip.id})});shareTargetClip.shareCount=d.shareCount;render();LogiApp.toast('Klip arkadaşına gönderildi.','success');LogiApp.closeModal('clipShareModal');}catch(err){LogiApp.toast(err.message,'error');btn.disabled=false;btn.querySelector('b').textContent='Gönder';}});
  document.getElementById('clipCopyLinkBtn')?.addEventListener('click',async()=>{if(!shareTargetClip)return;const url=`${location.origin}${location.pathname}?clip=${shareTargetClip.id}`;await LogiApp.copyText(url);try{const d=await LogiApp.api('/clips/share.php',{method:'POST',body:JSON.stringify({clipId:shareTargetClip.id})});shareTargetClip.shareCount=d.shareCount;render();}catch{}LogiApp.toast('Klip bağlantısı kopyalandı.','success');});

  async function loadComments(c){document.getElementById('clipCommentForm').classList.add('hidden');document.getElementById('clipCommentCount').textContent=String(c.commentCount||0);await BUFFV17.comments('clip',c.id,commentList);}
  function renderModalActions(c){document.getElementById('clipModalActions').innerHTML=actionButtons(c);}
  async function openClip(c){
    activeClip=c;player.src=c.fileUrl;document.getElementById('clipPlayerTitle').textContent=c.title;document.getElementById('clipPlayerMeta').textContent=`${c.streamerName} • ${c.category||'Topluluk'} • ${durationText(c.durationSeconds)}`;renderModalActions(c);LogiApp.openModal('clipPlayerModal');player.play().catch(()=>{});await loadComments(c);
  }

  document.querySelectorAll('[data-clip-filter]').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('[data-clip-filter]').forEach(x=>x.classList.remove('active'));btn.classList.add('active');filter=btn.dataset.clipFilter;render();}));
  document.getElementById('clipSearch')?.addEventListener('input',render);
  grid.addEventListener('click',e=>{const play=e.target.closest('.clip-play');if(play){const c=clips.find(x=>x.id===Number(play.dataset.clipId));if(c)openClip(c);return;}const a=e.target.closest('[data-clip-action]');if(a)clipAction(a.dataset.clipAction,Number(a.dataset.id));});
  document.getElementById('clipModalActions')?.addEventListener('click',e=>{const a=e.target.closest('[data-clip-action]');if(a)clipAction(a.dataset.clipAction,Number(a.dataset.id));});
  document.querySelector('[data-close-modal="clipPlayerModal"]')?.addEventListener('click',()=>{player.pause();player.removeAttribute('src');player.load();activeClip=null;});

  const openUpload=()=>{if(!LogiApp.requireLoggedIn(user))return;LogiApp.openModal('clipUploadModal');};
  document.getElementById('openClipUploadBtn')?.addEventListener('click',openUpload);document.getElementById('openClipUploadBtn2')?.addEventListener('click',openUpload);
  const uploadForm=document.getElementById('clipUploadForm'),uploadPreview=document.getElementById('clipUploadPreview');
  uploadForm?.elements.video?.addEventListener('change',()=>{const f=uploadForm.elements.video.files?.[0];if(!f)return;const u=URL.createObjectURL(f);uploadPreview.src=u;uploadPreview.classList.remove('hidden');uploadPreview.onloadedmetadata=()=>{document.getElementById('clipUploadDuration').value=String(Math.max(1,Math.round(uploadPreview.duration||30)));};});
  uploadForm?.addEventListener('submit',async e=>{e.preventDefault();const form=e.currentTarget,btn=form.querySelector('button[type="submit"]'),msg=document.getElementById('clipUploadMessage');if(btn.disabled)return;btn.disabled=true;try{const d=await BUFFV17.uploadVideo('clip',new FormData(form),n=>{msg.textContent='Yükleniyor… %'+n;});msg.textContent=d.message;msg.className='form-message success';form.reset();uploadPreview.pause();uploadPreview.classList.add('hidden');uploadPreview.removeAttribute('src');await loadClips();LogiApp.closeModal('clipUploadModal');}catch(err){msg.textContent=err.message;msg.className='form-message error';}finally{btn.disabled=false;btn.textContent='Klibi Yayınla';}});
  document.getElementById('clipCommentForm')?.addEventListener('submit',async e=>{e.preventDefault();if(!activeClip||!LogiApp.requireLoggedIn(user))return;const input=document.getElementById('clipCommentInput'),body=input.value.trim();if(!body)return;try{await LogiApp.api('/clips/comment-create.php',{method:'POST',body:JSON.stringify({clipId:activeClip.id,body})});input.value='';await loadComments(activeClip);render();}catch(err){LogiApp.toast(err.message,'error');}});

  await loadClips();
});
