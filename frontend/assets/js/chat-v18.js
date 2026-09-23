/* BUFF chat reader: presentation only; existing chat and moderation APIs remain authoritative. */
(() => {
  'use strict';
  const labels = {
    tr: ['Mesajlarda ara','Akışı duraklat','Canlı akışa dön','yeni mesaj','Saat','Bana yazılanlar','Yanıtla','Oku','Okuma alanı','Kapat','Filtreye uygun mesaj yok.','Yazı boyutu','Normal','Büyük','Çok büyük','Sohbet akışı'],
    en: ['Search messages','Pause scrolling','Back to live','new messages','Time','Mentions','Reply','Read','Reading area','Close','No messages match the filter.','Text size','Normal','Large','Extra large','Chat stream'],
    de: ['Nachrichten suchen','Scrollen pausieren','Zurück zu Live','neue Nachrichten','Uhrzeit','Erwähnungen','Antworten','Lesen','Lesebereich','Schließen','Keine passenden Nachrichten.','Textgröße','Normal','Groß','Sehr groß','Chatverlauf'],
    es: ['Buscar mensajes','Pausar desplazamiento','Volver al directo','mensajes nuevos','Hora','Menciones','Responder','Leer','Área de lectura','Cerrar','No hay mensajes que coincidan.','Tamaño del texto','Normal','Grande','Muy grande','Chat en directo'],
    ar: ['البحث في الرسائل','إيقاف التمرير','العودة إلى المباشر','رسائل جديدة','الوقت','الإشارات إليّ','رد','قراءة','منطقة القراءة','إغلاق','لا توجد رسائل تطابق البحث.','حجم النص','عادي','كبير','كبير جداً','المحادثة المباشرة']
  };
  const lang = () => window.LogiI18n?.currentLanguage?.() || 'tr';
  const t = n => (labels[lang()] || labels.tr)[n];
  const save=(k,v)=>{try{localStorage.setItem(k,v);}catch{}};
  const read=(k,fallback)=>{try{return localStorage.getItem(k)||fallback;}catch{return fallback;}};
  function mount(list, {user, input, studio=false}={}) {
    if (!list) return null;
    if (list.buffChatReader) return list.buffChatReader;
    const toolbar=document.createElement('div');toolbar.className='v18-chat-toolbar';toolbar.dataset.noI18n='';
    const search=document.createElement('input');search.type='search';search.className='v18-chat-search';
    const controls=document.createElement('div');controls.className='v18-chat-controls';
    const pause=document.createElement('button');pause.type='button';pause.setAttribute('aria-pressed','false');
    const mentions=document.createElement('button');mentions.type='button';mentions.setAttribute('aria-pressed','false');
    const timeLabel=document.createElement('label'),time=document.createElement('input'),timeText=document.createElement('span');
    time.type='checkbox';time.checked=read('buff_chat_time','1')==='1';timeLabel.append(time,timeText);
    const size=document.createElement('select');size.className='v18-chat-size';
    [1,1.125,1.25].forEach(value=>{const option=document.createElement('option');option.value=String(value);size.append(option);});
    size.value=read('buff_chat_size','1');if(!size.value)size.value='1';
    controls.append(pause,mentions,timeLabel,size);toolbar.append(search,controls);list.before(toolbar);
    const reading=document.createElement('div');reading.className='v18-chat-reading';reading.hidden=true;reading.dataset.noI18n='';
    const readingTitle=document.createElement('strong'),readingBody=document.createElement('p'),close=document.createElement('button');close.type='button';close.textContent='×';
    readingBody.className='user-content';readingBody.dataset.noI18n='';reading.append(readingTitle,readingBody,close);toolbar.after(reading);
    const empty=document.createElement('p');empty.className='v18-chat-filter-empty';empty.hidden=true;empty.dataset.noI18n='';list.after(empty);
    const jump=document.createElement('button');jump.type='button';jump.className='v18-chat-jump';jump.hidden=true;jump.dataset.noI18n='';empty.after(jump);
    list.classList.add('v18-chat-list');list.setAttribute('role','log');list.setAttribute('aria-live','off');
    let paused=false,manualPause=false,unread=0,mentionOnly=false,frame=0;const seen=new Set();
    const isNearBottom=()=>list.scrollHeight-list.scrollTop-list.clientHeight<36;
    const renderControls=()=>{
      search.placeholder=t(0);search.setAttribute('aria-label',t(0));pause.textContent=paused?t(2):t(1);pause.setAttribute('aria-pressed',String(paused));
      mentions.textContent=t(5);mentions.setAttribute('aria-pressed',String(mentionOnly));timeText.textContent=t(4);
      size.setAttribute('aria-label',t(11));[...size.options].forEach((o,i)=>o.textContent=t(12+i));close.setAttribute('aria-label',t(9));list.setAttribute('aria-label',t(15));
      jump.hidden=!paused;jump.textContent=`${unread ? `${unread} ${t(3)} · ` : ''}${t(2)}`;empty.textContent=t(10);
      list.classList.toggle('v18-hide-time',!time.checked);list.style.setProperty('--reader-scale',size.value);
    };
    const trim=()=>{
      const max=paused?600:300;
      while(list.children.length>max){const first=list.firstElementChild;const before=list.scrollHeight;first.remove();if(paused)list.scrollTop-=Math.max(0,before-list.scrollHeight);}
    };
    const toLive=()=>{paused=false;manualPause=false;unread=0;trim();list.scrollTop=list.scrollHeight;renderControls();};
    const filter=()=>{
      const q=search.value.trim().toLocaleLowerCase(lang());let visible=0;
      list.querySelectorAll('.chat-message').forEach(row=>{const show=(!q||row.dataset.searchText.includes(q))&&(!mentionOnly||row.classList.contains('v18-mention'));row.classList.toggle('v18-filtered',!show);if(show)visible++;});
      empty.hidden=!((q||mentionOnly)&&!visible);renderControls();
    };
    const scheduleBottom=()=>{if(frame)return;frame=requestAnimationFrame(()=>{frame=0;if(!paused)list.scrollTop=list.scrollHeight;});};
    list.addEventListener('scroll',()=>{if(!list.clientHeight)return;if(!isNearBottom()){paused=true;}else if(paused&&!manualPause){paused=false;unread=0;}renderControls();},{passive:true});
    pause.onclick=()=>{if(paused)toLive();else{paused=true;manualPause=true;renderControls();}};jump.onclick=toLive;
    search.addEventListener('input',filter);mentions.onclick=()=>{mentionOnly=!mentionOnly;filter();};
    time.onchange=()=>{save('buff_chat_time',time.checked?'1':'0');renderControls();};size.onchange=()=>{save('buff_chat_size',size.value);renderControls();};
    close.onclick=()=>{reading.hidden=true;pause.focus();};
    const reply=name=>{if(!input||!name)return;input.value=`@${name} ${input.value.replace(/^@\S+\s*/,'')}`;input.focus();};
    function append(row,msg,{initial=false}={}){
      const id=Number(msg.id);if(!Number.isSafeInteger(id)||id<1||seen.has(id))return false;seen.add(id);
      const username=String(user?.username||'').toLocaleLowerCase('en-US');
      const names=[...String(msg.message||'').matchAll(/@([\p{L}\p{N}_-]+)/gu)].map(m=>m[1].toLocaleLowerCase('en-US'));
      row.classList.toggle('v18-mention',!!username&&names.includes(username));row.dataset.messageId=String(id);
      row.dataset.searchText=`${msg.displayName||''} ${msg.username||''} ${msg.message||''}`.toLocaleLowerCase(lang());
      row.dataset.originalColor=msg.isVip?String(msg.chatColor||''):'';
      row.style.setProperty('--reader-color',msg.isVip?LogiApp.readableChatColor(msg.chatColor):'var(--ui-text)');
      row.dataset.role=msg.isOwner?'owner':msg.isModerator?'mod':msg.isVip?'vip':'user';
      const meta=row.querySelector('.chat-meta'),stamp=meta?.querySelector('small');
      if(stamp){const date=new Date(String(msg.createdAt||'').replace(' ','T'));stamp.classList.add('v18-chat-time');if(!Number.isNaN(date.getTime())){stamp.textContent=new Intl.DateTimeFormat(lang(),{hour:'2-digit',minute:'2-digit'}).format(date);stamp.title=date.toLocaleString(lang());}stamp.dataset.noI18n='';}
      const actions=document.createElement('span');actions.className='v18-chat-row-actions';actions.dataset.noI18n='';
      if(msg.username){const b=document.createElement('button');b.type='button';b.textContent=t(6);b.dataset.chatLabel='6';b.onclick=()=>reply(msg.username);actions.append(b);}
      if(studio){const b=document.createElement('button');b.type='button';b.textContent=t(7);b.dataset.chatLabel='7';b.onclick=()=>{readingTitle.textContent=`${t(8)} · ${msg.displayName}`;readingBody.textContent=msg.message||msg.stickerCode||'';reading.hidden=false;};actions.append(b);}
      row.querySelector(':scope > div:last-child')?.append(actions);
      list.querySelector('.chat-empty,.rail-empty')?.remove();list.append(row);trim();
      const q=search.value.trim().toLocaleLowerCase(lang());row.classList.toggle('v18-filtered',!!((q&&!row.dataset.searchText.includes(q))||(mentionOnly&&!row.classList.contains('v18-mention'))));
      if(paused&&!initial&&!row.classList.contains('v18-filtered'))unread++;else if(!paused)scheduleBottom();
      if(q||mentionOnly)filter();else renderControls();return true;
    }
    function clear(){list.replaceChildren();seen.clear();paused=false;manualPause=false;unread=0;reading.hidden=true;empty.hidden=true;renderControls();}
    document.addEventListener('logi:languagechange',()=>{renderControls();list.querySelectorAll('[data-chat-label]').forEach(b=>b.textContent=t(Number(b.dataset.chatLabel)));filter();});
    new MutationObserver(()=>{list.querySelectorAll('.chat-message[data-original-color]').forEach(row=>{row.style.setProperty('--reader-color',row.dataset.originalColor?LogiApp.readableChatColor(row.dataset.originalColor):'var(--ui-text)');});}).observe(document.documentElement,{attributes:true,attributeFilter:['data-theme']});
    renderControls();list.buffChatReader={append,clear,toLive};return list.buffChatReader;
  }
  window.BUFFChat={mount};
})();
