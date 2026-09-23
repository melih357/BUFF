document.addEventListener('DOMContentLoaded', async () => {
  const me = await LogiApp.getCurrentUser();
  if (!LogiApp.requireLoggedIn(me)) return;

  const roomList = document.getElementById('roomList');
  const messageList = document.getElementById('messageList');
  const peopleSearch = document.getElementById('peopleSearch');
  const peopleResults = document.getElementById('peopleResults');
  const chatEmpty = document.getElementById('chatEmpty');
  const chatActive = document.getElementById('chatActive');
  const info = document.getElementById('roomSidebar');
  const esc = LogiApp.escapeHtml;

  let rooms = [];
  let contacts = [];
  let activeRoom = null;
  let lastMessageId = 0;
  let roomFilter = 'all';
  let messageTimer = 0;
  let roomTimer = 0;
  let signalTimer = 0;
  let voiceRecorder = null;
  let voiceChunks = [];
  let voiceStartedAt = 0;
  let voiceTick = 0;
  let voiceBlob = null;

  let callPeer = null;
  let callStream = null;
  let callTargetId = 0;
  let incomingCallerId = 0;
  let pendingOffer = null;
  let callState = 'idle';

  const avatar = u => LogiApp.avatarMarkup({displayName:u?.displayName||'BUFF', avatarUrl:u?.avatarUrl}, 'avatar-circle');

  function roomPreview(room) {
    const lm = room.lastMessage;
    if (!lm) return 'Henüz mesaj yok';
    if (lm.type === 'image') return '📷 Fotoğraf';
    if (lm.type === 'voice') return '🎙 Sesli mesaj';
    if (lm.type === 'coin') return '◈ Coin hediyesi';
    return lm.body || 'Yeni mesaj';
  }

  function renderRooms() {
    const list = rooms.filter(r => roomFilter === 'all' || r.type === roomFilter);
    roomList.innerHTML = list.length ? list.map(r => `
      <button class="room-row ${activeRoom?.id===r.id?'active':''}" type="button" data-room-id="${r.id}">
        <span class="room-avatar">${r.avatarUrl?`<img src="${esc(r.avatarUrl)}" alt="">`:esc((r.title||'B').charAt(0).toUpperCase())}</span>
        <span class="room-copy"><strong>${esc(r.title||'Sohbet')}</strong><small>${esc(roomPreview(r))}</small></span>
        <span class="room-tail">${r.unread?`<b>${r.unread>99?'99+':r.unread}</b>`:''}<em>${r.lastMessage?esc(LogiApp.timeAgo(r.lastMessage.createdAt)):''}</em></span>
      </button>`).join('') : '<div class="menu-empty">Bu bölümde henüz sohbet yok.</div>';
  }

  async function loadRooms(keepActive=true) {
    try {
      const d = await LogiApp.api('/messages/rooms.php');
      rooms = d.rooms || [];
      if (keepActive && activeRoom) {
        const fresh = rooms.find(r => r.id === activeRoom.id);
        if (fresh) activeRoom = fresh;
      }
      renderRooms();
    } catch (e) {
      roomList.innerHTML = `<div class="menu-empty">${esc(e.message)}</div>`;
    }
  }

  function renderRoomInfo() {
    if (!activeRoom) return;
    const members = activeRoom.members || [];
    const peer = activeRoom.type==='direct' ? members.find(x=>x.id!==me.id) : null;
    info.innerHTML = `
      <div class="room-info-head"><span class="room-info-avatar">${activeRoom.avatarUrl?`<img src="${esc(activeRoom.avatarUrl)}" alt="">`:esc((activeRoom.title||'B').charAt(0).toUpperCase())}</span><h3>${esc(activeRoom.title)}</h3><small>${activeRoom.type==='group'?`${members.length} üye`:`${esc(peer?.publicId||'')}`}</small></div>
      <div class="room-info-section"><span>Üyeler</span>${members.map(u=>`<a class="room-member" href="./channel.php?username=${encodeURIComponent(u.username)}">${avatar(u)}<div><strong>${esc(u.displayName)}</strong><small>@${esc(u.username)} • ${esc(u.publicId||'')}</small></div>${u.role==='owner'?'<em>Yönetici</em>':''}</a>`).join('')}</div>
      ${activeRoom.type==='direct'?'<div class="room-info-note">Birebir mesaj, coin hediyesi ve sesli arama yalnızca karşılıklı takip devam ettiği sürece kullanılabilir.</div>':'<div class="room-info-note">Gruplar fotoğraf, sesli mesaj ve metin mesajını destekler. Grup sesli araması sonraki ölçekleme katmanına ayrılmıştır.</div>'}`;
    document.getElementById('voiceCallBtn')?.classList.toggle('hidden', activeRoom.type!=='direct');
    document.getElementById('coinGiftBtn')?.classList.toggle('hidden', activeRoom.type!=='direct');
  }

  function openRoomUI(room) {
    activeRoom = room;
    lastMessageId = 0;
    messageList.innerHTML = '';
    chatEmpty.classList.add('hidden');
    chatActive.classList.remove('hidden');
    const title=document.getElementById('roomTitle'), meta=document.getElementById('roomMeta'), av=document.getElementById('roomAvatar');
    title.textContent=room.title||'Sohbet';
    meta.textContent = room.type==='group' ? `${room.members?.length||0} üye` : `${room.members?.find(x=>x.id!==me.id)?.publicId||''} • karşılıklı takip`;
    if(room.avatarUrl){ av.innerHTML=`<img src="${esc(room.avatarUrl)}" alt="">`; av.classList.add('avatar-photo'); } else { av.textContent=(room.title||'B').charAt(0).toUpperCase();av.classList.remove('avatar-photo');}
    renderRoomInfo();
    renderRooms();
    history.replaceState(null,'',`./messages.php?room=${room.id}`);
    loadMessages(true);
    if(window.BUFFV14?.subscribe) window.BUFFV14.subscribe(`room.${room.id}`);
  }

  async function openRoom(id) {
    let room = rooms.find(r=>r.id===Number(id));
    if(!room){
      const d=await LogiApp.api(`/messages/room.php?id=${Number(id)}`);
      room=d.room;
    }
    if(room) openRoomUI(room);
  }

  function messageMarkup(m) {
    const isMine = !!m.mine;
    const who = m.user || {};
    let body = '';
    if(m.type==='image') body=`<button class="dm-image" type="button" data-image="${esc(m.mediaUrl||'')}"><img src="${esc(m.mediaUrl||'')}" alt="Gönderilen fotoğraf"></button>${m.body?`<p>${esc(m.body)}</p>`:''}`;
    else if(m.type==='voice') body=`<div class="voice-message"><button type="button" data-audio="${esc(m.mediaUrl||'')}">▶</button><div><i></i><span>Sesli mesaj</span></div><em>${Math.max(1,Number(m.durationSeconds||0))} sn</em><audio preload="metadata" src="${esc(m.mediaUrl||'')}"></audio></div>`;
    else if(m.type==='coin') body=`<div class="coin-message"><span>◈</span><div><strong>Coin hediyesi</strong><small>${esc(m.body)}</small></div></div>`;
    else if(m.type==='system') body=`<div class="system-message">${esc(m.body)}</div>`;
    else body=`<p>${esc(m.body).replace(/\n/g,'<br>')}</p>`;
    return `<article class="dm-message ${isMine?'mine':''} ${m.type==='system'?'system':''}" data-message-id="${m.id}">
      ${!isMine&&m.type!=='system'?avatar(who):''}
      <div class="dm-bubble">${!isMine&&activeRoom?.type==='group'?`<strong class="dm-sender">${esc(who.displayName||'')}</strong>`:''}${body}<small>${esc(LogiApp.timeAgo(m.createdAt))}</small></div>
    </article>`;
  }

  async function loadMessages(reset=false) {
    if(!activeRoom) return;
    try{
      const d=await LogiApp.api(`/messages/list.php?room_id=${activeRoom.id}&after_id=${reset?0:lastMessageId}`);
      if(reset){messageList.innerHTML='';lastMessageId=0;}
      for(const m of (d.messages||[])){
        if(m.id<=lastMessageId) continue;
        messageList.insertAdjacentHTML('beforeend',messageMarkup(m));
        lastMessageId=Math.max(lastMessageId,Number(m.id));
      }
      if((d.messages||[]).length) messageList.scrollTop=messageList.scrollHeight;
    }catch(e){}
  }

  roomList.addEventListener('click', e => {
    const row=e.target.closest('[data-room-id]'); if(row) openRoom(Number(row.dataset.roomId)).catch(err=>LogiApp.toast(err.message,'error'));
  });

  document.querySelectorAll('[data-room-tab]').forEach(btn=>btn.addEventListener('click',()=>{
    document.querySelectorAll('[data-room-tab]').forEach(x=>x.classList.remove('active'));btn.classList.add('active');roomFilter=btn.dataset.roomTab;renderRooms();
  }));

  let searchTimer=0;
  async function runPeopleSearch(){
    const q=peopleSearch.value.trim();
    if(!q){peopleResults.classList.add('hidden');peopleResults.innerHTML='';return;}
    try{
      const d=await LogiApp.api(`/social/search.php?q=${encodeURIComponent(q)}`);
      peopleResults.innerHTML=(d.users||[]).length?(d.users||[]).map(u=>`
        <div class="people-result" data-user-id="${u.id}">
          ${avatar(u)}<div><strong>${esc(u.displayName)}</strong><small>@${esc(u.username)} • ${esc(u.publicId)}</small></div>
          <div class="people-actions"><button type="button" data-follow-user="${u.id}">${u.pending?'İsteği İptal Et':u.following?'Takibi Bırak':'Takip Et'}</button>${u.mutual?`<button class="primary" type="button" data-message-user="${u.id}">Mesaj</button>`:''}</div>
        </div>`).join(''):'<div class="menu-empty">Kullanıcı bulunamadı.</div>';
      peopleResults.classList.remove('hidden');
    }catch(e){peopleResults.innerHTML=`<div class="menu-empty">${esc(e.message)}</div>`;peopleResults.classList.remove('hidden');}
  }
  peopleSearch.addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(runPeopleSearch,180)});
  peopleResults.addEventListener('click',async e=>{
    const follow=e.target.closest('[data-follow-user]');if(follow){try{await LogiApp.api('/follow/toggle.php',{method:'POST',body:JSON.stringify({userId:Number(follow.dataset.followUser)})});await runPeopleSearch();await loadContacts();}catch(err){LogiApp.toast(err.message,'error')}return;}
    const msg=e.target.closest('[data-message-user]');if(msg){try{const d=await LogiApp.api('/messages/create-direct.php',{method:'POST',body:JSON.stringify({userId:Number(msg.dataset.messageUser)})});peopleResults.classList.add('hidden');await loadRooms(false);openRoomUI(d.room);}catch(err){LogiApp.toast(err.message,'error')}}
  });

  document.addEventListener('click',e=>{if(!e.target.closest('.id-search-box'))peopleResults.classList.add('hidden')});

  async function loadContacts(){
    try{const d=await LogiApp.api('/social/contacts.php');contacts=d.contacts||[];}catch{contacts=[]}
  }

  document.getElementById('newGroupBtn')?.addEventListener('click',async()=>{
    await loadContacts();
    const box=document.getElementById('groupContactList');
    box.innerHTML=contacts.length?contacts.map(u=>`<label class="group-contact">${avatar(u)}<div><strong>${esc(u.displayName)}</strong><small>${esc(u.publicId)} • @${esc(u.username)}</small></div><input type="checkbox" value="${u.id}"></label>`).join(''):'<div class="menu-empty">Grup kurmak için önce en az iki kullanıcıyla karşılıklı takipte olmalısın.</div>';
    LogiApp.openModal('groupModal');
  });

  document.getElementById('groupForm')?.addEventListener('submit',async e=>{
    e.preventDefault();const form=e.currentTarget,msg=document.getElementById('groupMessage'),ids=[...form.querySelectorAll('input[type=checkbox]:checked')].map(x=>Number(x.value));
    try{const d=await LogiApp.api('/messages/create-group.php',{method:'POST',body:JSON.stringify({title:new FormData(form).get('title'),userIds:ids})});msg.className='form-message success';msg.textContent=d.message;await loadRooms(false);openRoomUI(d.room);setTimeout(()=>LogiApp.closeModal('groupModal'),500);}catch(err){msg.className='form-message error';msg.textContent=err.message;}finally{btn.disabled=false;}
  });

  async function sendFormData(fd){
    if(!activeRoom)throw new Error('Önce bir sohbet seç.');
    fd.append('roomId',String(activeRoom.id));
    const d=await LogiApp.api('/messages/send.php',{method:'POST',body:fd});
    await loadMessages(); await loadRooms(); return d;
  }

  document.getElementById('messageComposer')?.addEventListener('submit',async e=>{
    e.preventDefault();const input=document.getElementById('messageText'),text=input.value.trim();if(!text)return;
    const fd=new FormData();fd.append('body',text);
    try{input.value='';await sendFormData(fd);}catch(err){input.value=text;LogiApp.toast(err.message,'error')}
  });

  document.getElementById('imageMessageBtn')?.addEventListener('click',()=>document.getElementById('messageImageInput')?.click());
  document.getElementById('messageImageInput')?.addEventListener('change',async e=>{
    const file=e.target.files?.[0];if(!file)return;const fd=new FormData();fd.append('media',file);fd.append('body','');
    try{await sendFormData(fd);}catch(err){LogiApp.toast(err.message,'error')}finally{e.target.value='';}
  });

  function voiceMime(){return ['audio/webm;codecs=opus','audio/webm','audio/ogg'].find(x=>window.MediaRecorder?.isTypeSupported?.(x))||'';}
  async function startVoiceRecord(){
    if(voiceRecorder?.state==='recording')return;
    try{
      const stream=await navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true}});
      const mime=voiceMime();voiceRecorder=new MediaRecorder(stream,mime?{mimeType:mime}:undefined);voiceChunks=[];voiceBlob=null;voiceStartedAt=Date.now();
      voiceRecorder.ondataavailable=e=>{if(e.data?.size)voiceChunks.push(e.data)};
      voiceRecorder.onstop=()=>{voiceBlob=new Blob(voiceChunks,{type:voiceRecorder.mimeType||'audio/webm'});stream.getTracks().forEach(t=>t.stop());};
      voiceRecorder.start(500);document.getElementById('voiceRecordState').classList.remove('hidden');
      voiceTick=setInterval(()=>{const sec=Math.floor((Date.now()-voiceStartedAt)/1000);document.getElementById('voiceRecordTimer').textContent=`${Math.floor(sec/60)}:${String(sec%60).padStart(2,'0')}`},250);
    }catch(err){LogiApp.toast('Mikrofon açılamadı: '+err.message,'error')}
  }
  function stopVoice(discard=false){return new Promise(resolve=>{if(!voiceRecorder||voiceRecorder.state!=='recording'){resolve(null);return;}const r=voiceRecorder;r.addEventListener('stop',()=>{clearInterval(voiceTick);document.getElementById('voiceRecordState').classList.add('hidden');const b=discard?null:voiceBlob;voiceRecorder=null;setTimeout(()=>resolve(discard?null:voiceBlob),0)},{once:true});r.stop();});}
  document.getElementById('voiceMessageBtn')?.addEventListener('click',startVoiceRecord);
  document.getElementById('cancelVoiceBtn')?.addEventListener('click',()=>stopVoice(true));
  document.getElementById('sendVoiceBtn')?.addEventListener('click',async()=>{
    if(!voiceRecorder)return;const duration=Math.max(1,Math.round((Date.now()-voiceStartedAt)/1000));
    const stopped=new Promise(resolve=>{const r=voiceRecorder;r.addEventListener('stop',()=>{clearInterval(voiceTick);document.getElementById('voiceRecordState').classList.add('hidden');const b=new Blob(voiceChunks,{type:r.mimeType||'audio/webm'});r.stream?.getTracks?.().forEach(t=>t.stop());voiceRecorder=null;resolve(b)},{once:true});r.stop();});
    const blob=await stopped;if(!blob)return;const fd=new FormData();fd.append('media',blob,`buff-voice-${Date.now()}.webm`);fd.append('durationSeconds',String(duration));fd.append('body','');
    try{await sendFormData(fd);}catch(err){LogiApp.toast(err.message,'error')}
  });

  messageList.addEventListener('click',e=>{
    const a=e.target.closest('[data-audio]');if(a){const audio=a.parentElement.querySelector('audio');if(!audio)return;if(audio.paused){document.querySelectorAll('.voice-message audio').forEach(x=>{if(x!==audio)x.pause()});audio.play().catch(()=>{});a.textContent='Ⅱ';audio.onended=()=>a.textContent='▶';}else{audio.pause();a.textContent='▶';}}
    const img=e.target.closest('[data-image]');if(img){window.open(img.dataset.image,'_blank','noopener');}
  });

  document.getElementById('coinGiftBtn')?.addEventListener('click',()=>{if(activeRoom?.type!=='direct')return;document.getElementById('coinMessage').textContent='';LogiApp.openModal('coinModal')});
  document.getElementById('coinForm')?.addEventListener('submit',async e=>{
    e.preventDefault();const form=e.currentTarget,btn=form.querySelector('button[type=submit]');if(btn.disabled)return;const msg=document.getElementById('coinMessage'),peer=activeRoom?.members?.find(x=>x.id!==me.id);if(!peer)return;
    const fd=new FormData(form);
    btn.disabled=true;try{const d=await BUFFV17.mutation('/messages/coin.php',{roomId:activeRoom.id,recipientUserId:peer.id,amount:Number(fd.get('amount')),note:fd.get('note')},'friend-coin');msg.className='form-message success';msg.textContent=d.message;await loadMessages();setTimeout(()=>LogiApp.closeModal('coinModal'),550);}catch(err){msg.className='form-message error';msg.textContent=err.message;}finally{btn.disabled=false;}
  });

  // ---- 1:1 WebRTC audio calls ----
  const callOverlay=document.getElementById('callOverlay'), callStateEl=document.getElementById('callState'), callName=document.getElementById('callName'), remoteAudio=document.getElementById('remoteCallAudio');
  function showCall(name,state,incoming=false){callName.textContent=name||'BUFF kullanıcısı';callStateEl.textContent=state;callOverlay.classList.remove('hidden');document.getElementById('acceptCallBtn').classList.toggle('hidden',!incoming);}
  function hideCall(){callOverlay.classList.add('hidden');document.getElementById('acceptCallBtn').classList.add('hidden');}
  async function sendSignal(type,payload={}){if(!activeRoom||!callTargetId)return;return LogiApp.api('/messages/signal-send.php',{method:'POST',body:JSON.stringify({roomId:activeRoom.id,recipientUserId:callTargetId,type,payload})});}
  async function ensureCallMedia(){if(callStream)return callStream;callStream=await navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true},video:false});return callStream;}
  function ensurePeer(){
    if(callPeer)return callPeer;callPeer=new RTCPeerConnection({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});
    if(callStream)callStream.getTracks().forEach(t=>callPeer.addTrack(t,callStream));
    callPeer.ontrack=e=>{remoteAudio.srcObject=e.streams[0]||new MediaStream([e.track]);};
    callPeer.onicecandidate=e=>{if(e.candidate)sendSignal('candidate',e.candidate.toJSON()).catch(()=>{})};
    callPeer.onconnectionstatechange=()=>{if(callPeer?.connectionState==='connected'){callState='connected';callStateEl.textContent='Bağlandı';}if(['failed','closed','disconnected'].includes(callPeer?.connectionState))endCall(false);};
    return callPeer;
  }
  async function startCall(){
    if(activeRoom?.type!=='direct')return;const peer=activeRoom.members.find(x=>x.id!==me.id);if(!peer)return;callTargetId=peer.id;callState='ringing';showCall(peer.displayName,'Aranıyor…',false);
    try{await ensureCallMedia();await sendSignal('ring',{name:me.displayName});}catch(err){LogiApp.toast('Arama başlatılamadı: '+err.message,'error');endCall(false);}
  }
  async function acceptIncoming(){
    if(!incomingCallerId||!activeRoom)return;callTargetId=incomingCallerId;callState='connecting';document.getElementById('acceptCallBtn').classList.add('hidden');callStateEl.textContent='Bağlanıyor…';
    try{await ensureCallMedia();ensurePeer();await sendSignal('accept',{});}catch(err){LogiApp.toast(err.message,'error');endCall(true);}
  }
  async function endCall(signal=true){
    if(signal&&callTargetId)sendSignal('hangup',{}).catch(()=>{});try{callPeer?.close()}catch{}callPeer=null;callStream?.getTracks?.().forEach(t=>t.stop());callStream=null;remoteAudio.srcObject=null;callTargetId=0;incomingCallerId=0;pendingOffer=null;callState='idle';hideCall();
  }
  async function pollSignals(){
    try{
      const d=await LogiApp.api('/messages/signal-poll.php');
      for(const s of (d.signals||[])){
        if(!activeRoom||Number(s.roomId)!==Number(activeRoom.id))continue;
        if(s.type==='ring'){incomingCallerId=s.senderUserId;callTargetId=s.senderUserId;const caller=activeRoom.members.find(x=>x.id===s.senderUserId);showCall(caller?.displayName||'BUFF kullanıcısı','Seni arıyor…',true);}
        else if(s.type==='accept'&&callState==='ringing'){callTargetId=s.senderUserId;await ensureCallMedia();const pc=ensurePeer();const offer=await pc.createOffer();await pc.setLocalDescription(offer);await sendSignal('offer',offer);}
        else if(s.type==='offer'){callTargetId=s.senderUserId;if(callState==='idle'){pendingOffer=s.payload;continue;}const pc=ensurePeer();await pc.setRemoteDescription(new RTCSessionDescription(s.payload));const ans=await pc.createAnswer();await pc.setLocalDescription(ans);await sendSignal('answer',ans);}
        else if(s.type==='answer'){const pc=ensurePeer();await pc.setRemoteDescription(new RTCSessionDescription(s.payload));}
        else if(s.type==='candidate'){const pc=ensurePeer();try{await pc.addIceCandidate(new RTCIceCandidate(s.payload))}catch{}}
        else if(s.type==='reject'||s.type==='hangup'){endCall(false);}
      }
    }catch{}
  }
  document.getElementById('voiceCallBtn')?.addEventListener('click',startCall);
  document.getElementById('acceptCallBtn')?.addEventListener('click',acceptIncoming);
  document.getElementById('hangupCallBtn')?.addEventListener('click',()=>endCall(true));

  window.addEventListener('buff:realtime',ev=>{
    const msg=ev.detail||{},p=msg.payload||{};
    if(msg.event==='message.created'&&activeRoom&&Number(p.roomId)===Number(activeRoom.id))loadMessages();
    if(msg.event==='message.coin'&&activeRoom&&Number(p.roomId)===Number(activeRoom.id))loadMessages();
  });

  await Promise.all([loadRooms(false),loadContacts()]);
  const initial=Number(new URLSearchParams(location.search).get('room')||0);if(initial)openRoom(initial).catch(()=>{});
  roomTimer=setInterval(()=>loadRooms(),4000);messageTimer=setInterval(()=>loadMessages(),950);signalTimer=setInterval(pollSignals,850);
  window.addEventListener('beforeunload',()=>{clearInterval(roomTimer);clearInterval(messageTimer);clearInterval(signalTimer);endCall(false);});
});
