document.addEventListener('DOMContentLoaded', () => {
  const streamId = Number(LogiApp.getQuery('id') || 0);
  const video = document.getElementById('remoteVideo');
  const playerShell = document.getElementById('playerShell');
  const clipBtn = document.getElementById('clipBtn');
  const clipRecordBtn = document.getElementById('clipRecordBtn');
  const card = document.getElementById('chatUserCard');
  let clipBusy = false;

  document.getElementById('theatreBtn')?.addEventListener('click', e => {
    document.body.classList.toggle('watch-theatre');
    e.currentTarget.classList.toggle('active', document.body.classList.contains('watch-theatre'));
  });
  document.getElementById('fullscreenBtn')?.addEventListener('click', async () => {
    try { if (!document.fullscreenElement) await playerShell.requestFullscreen(); else await document.exitFullscreen(); } catch (_) {}
  });
  document.getElementById('chatFocusWatchBtn')?.addEventListener('click', e => {
    document.body.classList.toggle('watch-chat-focus');
    e.currentTarget.classList.toggle('active', document.body.classList.contains('watch-chat-focus'));
  });
  document.getElementById('chatImportantBtn')?.addEventListener('click', e => {
    document.body.classList.toggle('chat-important-only');
    e.currentTarget.classList.toggle('active', document.body.classList.contains('chat-important-only'));
  });
  document.getElementById('chatVipBtn')?.addEventListener('click', e => {
    document.body.classList.toggle('chat-vip-only');
    e.currentTarget.classList.toggle('active', document.body.classList.contains('chat-vip-only'));
  });

  clipBtn?.addEventListener('click', () => {
    if (!video.srcObject) { LogiApp.toast('Klip için önce canlı video bağlantısının kurulması gerekiyor.', 'error'); return; }
    document.getElementById('clipTitle').value = document.getElementById('watchTitle')?.textContent?.trim() + ' • Klip';
    LogiApp.openModal('clipModal');
  });

  function recorderMime() {
    const candidates = ['video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm'];
    return candidates.find(x => window.MediaRecorder?.isTypeSupported?.(x)) || '';
  }
  async function recordClip(seconds) {
    if (!window.MediaRecorder) throw new Error('Tarayıcın klip kaydını desteklemiyor.');
    const source = video.srcObject;
    if (!(source instanceof MediaStream) || !source.getTracks().length) throw new Error('Canlı video akışı henüz hazır değil.');
    const clone = new MediaStream(source.getTracks());
    const mimeType = recorderMime();
    const recorder = new MediaRecorder(clone, mimeType ? { mimeType, videoBitsPerSecond: 3500000 } : undefined);
    const chunks = [];
    const progress = document.getElementById('clipProgress');
    let remaining = seconds;
    recorder.ondataavailable = e => { if (e.data?.size) chunks.push(e.data); };
    const done = new Promise((resolve,reject) => { recorder.onerror=()=>reject(new Error('Klip kaydı sırasında hata oluştu.')); recorder.onstop=()=>resolve(new Blob(chunks,{type:recorder.mimeType||'video/webm'})); });
    recorder.start(1000);
    progress.className='form-message success'; progress.textContent=`Klip kaydı başladı • ${remaining} sn`;
    const timer=setInterval(()=>{ remaining--; progress.textContent=`Kayıt sürüyor • ${Math.max(0,remaining)} sn`; },1000);
    await new Promise(r=>setTimeout(r,seconds*1000)); clearInterval(timer); recorder.stop();
    return done;
  }
  clipRecordBtn?.addEventListener('click', async () => {
    if (clipBusy) return; clipBusy=true; clipRecordBtn.disabled=true;
    const progress=document.getElementById('clipProgress');
    try {
      const seconds=Number(document.getElementById('clipDuration').value||30);
      const blob=await recordClip(seconds);
      progress.textContent='Klip yükleniyor...';
      const fd=new FormData(); fd.append('streamId',String(streamId)); fd.append('durationSeconds',String(seconds)); fd.append('title',document.getElementById('clipTitle').value.trim()||'BUFF Klip'); fd.append('video',blob,`buff-clip-${streamId}-${Date.now()}.webm`);
      const result=await LogiApp.api('/clips/upload.php',{method:'POST',body:fd});
      progress.textContent='Klip kaydedildi.'; LogiApp.toast(result.message||'Klip oluşturuldu.','success');
      setTimeout(()=>LogiApp.closeModal('clipModal'),700);
    } catch(e) { progress.className='form-message error'; progress.textContent=e.message; }
    finally { clipBusy=false; clipRecordBtn.disabled=false; }
  });

  // Chat mini profile card. Existing chat messages receive data-* attributes from watch.js V12 patch.
  document.getElementById('chatList')?.addEventListener('click', e => {
    const name=e.target.closest('.chat-meta strong[data-user-id]'); if(!name)return;
    e.stopPropagation(); const row=name.closest('.chat-message'); const userId=Number(name.dataset.userId), messageId=Number(row?.dataset.messageId||0), username=name.dataset.username||'', display=name.textContent.trim();
    card.innerHTML=`<div><strong>${LogiApp.escapeHtml(display)}</strong><small>@${LogiApp.escapeHtml(username)}</small></div><div class="chat-user-card-actions"><a href="./channel.php?username=${encodeURIComponent(username)}">Profil</a><button type="button" data-chat-report-user="${userId}">Kullanıcıyı Şikayet Et</button><button type="button" data-chat-report-message="${messageId}" data-user-id="${userId}">Mesajı Şikayet Et</button><button type="button" data-chat-reply="${LogiApp.escapeHtml(username)}">Yanıtla</button></div>`;
    const r=name.getBoundingClientRect(); card.style.left=Math.min(window.innerWidth-260,Math.max(10,r.left))+'px'; card.style.top=Math.min(window.innerHeight-150,r.bottom+7)+'px'; card.classList.remove('hidden');
  });
  card?.addEventListener('click', e => {
    const reply=e.target.closest('[data-chat-reply]'); if(reply){const input=document.getElementById('chatInput');input.value=`@${reply.dataset.chatReply} `;input.focus();card.classList.add('hidden');}
    const report=e.target.closest('[data-chat-report-user]'); if(report){const f=document.getElementById('reportForm');if(!f){LogiApp.toast('Şikayet formu yüklenemedi. Sayfayı yenileyip tekrar dene.','error');return;}f.reset();if(f.elements.scope)f.elements.scope.value='user';if(f.elements.targetUserId)f.elements.targetUserId.value=report.dataset.chatReportUser||'';if(f.elements.chatMessageId)f.elements.chatMessageId.value='';LogiApp.openModal('reportModal');card.classList.add('hidden');}
    const messageReport=e.target.closest('[data-chat-report-message]'); if(messageReport){const f=document.getElementById('reportForm');if(!f){LogiApp.toast('Şikayet formu yüklenemedi. Sayfayı yenileyip tekrar dene.','error');return;}f.reset();if(f.elements.scope)f.elements.scope.value='chat_message';if(f.elements.targetUserId)f.elements.targetUserId.value=messageReport.dataset.userId||'';if(f.elements.chatMessageId)f.elements.chatMessageId.value=messageReport.dataset.chatReportMessage||'';LogiApp.openModal('reportModal');card.classList.add('hidden');}
  });
  document.getElementById('reportBtn')?.addEventListener('click',()=>{const f=document.getElementById('reportForm');if(!f)return;f.reset();f.elements.scope.value='creator';f.elements.targetUserId.value='';f.elements.chatMessageId.value='';});
  document.addEventListener('click',e=>{if(card&&!card.contains(e.target)&&!e.target.closest('.chat-meta strong[data-user-id]'))card.classList.add('hidden');});
});
