(() => {
  const esc = s => window.LogiApp?.escapeHtml?.(s) ?? String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  const t = (k, fallback='') => window.LogiI18n?.t?.(k, fallback) || fallback || k;
  const page = () => document.body?.dataset?.page || '';

  function updateBranding() {
    document.querySelectorAll('.logo-lockup img, .auth-brand-panel img').forEach(img => {
      if (!img.dataset.v12Brand) { img.src = './assets/img/buff-logo.png'; img.dataset.v12Brand = '1'; }
    });
    document.querySelectorAll('link[rel="icon"]').forEach(link => link.href = './assets/img/buff-logo.png');
  }

  function ensureHeaderLinks() {
    const nav = document.querySelector('.app-header .header-nav');
    if (!nav) return;
    const existing = [...nav.querySelectorAll('a')].map(a => a.getAttribute('href') || '');
    const wanted = [
      ['./index.php', 'home', 'Ana Sayfa'], ['./discover.php', 'browse', 'Gözat'], ['./replay.php', 'replays', 'Tekrarlar'], ['./clips.php', 'clips', 'Klipler'], ['./moments.php', 'moments', 'Anlar'], ['./messages.php', 'messages', 'Mesajlar']
    ];
    const currentPage = page();
    nav.innerHTML = wanted.map(([href,key,label]) => {
      const active = (key==='home' && currentPage==='feed') || (key==='browse' && ['home','discover'].includes(currentPage)) || (key==='replays' && currentPage==='replay') || (key==='clips' && currentPage==='clips') || (key==='moments' && currentPage==='moments') || (key==='messages' && currentPage==='messages');
      return `<a href="${href}" class="${active?'active':''}" data-i18n="${key}">${t(key,label)}</a>`;
    }).join('');
  }

  function ensureMobileNav(me) {
    if (document.querySelector('.v12-mobile-nav') || ['login','register','payment','admin'].includes(page())) return;
    const nav = document.createElement('nav'); nav.className = 'v12-mobile-nav';
    const p = page();
    nav.innerHTML = `
      <a class="${p==='feed'?'active':''}" href="./index.php"><span>⌂</span><small data-i18n="home">${t('home','Ana Sayfa')}</small></a>
      <a class="${['home','discover'].includes(p)?'active':''}" href="./discover.php"><span>⌕</span><small data-i18n="browse">${t('browse','Gözat')}</small></a>
      <a class="${p==='replay'?'active':''}" href="./replay.php"><span>▶</span><small data-i18n="replays">${t('replays','Tekrarlar')}</small></a>
      <a class="${p==='clips'?'active':''}" href="./clips.php"><span>✂</span><small data-i18n="clips">${t('clips','Klipler')}</small></a>
      <a class="${p==='messages'?'active':''}" href="./messages.php"><span>💬</span><small>${t('messages','Mesajlar')}</small></a>
      <a class="${['profile','channel','settings','studio'].includes(p)?'active':''}" href="${me?.accountMode==='creator'?'./studio.php':'./profile.php'}"><span>${me?.accountMode==='creator'?'●':'◎'}</span><small>${me?.accountMode==='creator'?t('studio','Studio'):t('profile','Profil')}</small></a>`;
    document.body.appendChild(nav);
  }

  function ensureSidebarLinks(me) {
    const side = document.querySelector('.app-sidebar'); if (!side) return;
    const block = side.querySelector('.sidebar-main-links');
    if (block) {
      const p = page();
      block.innerHTML = `
        <a class="sidebar-nav ${p==='feed'?'active':''}" href="./index.php"><span>⌂</span><div><strong data-i18n="home">${t('home','Ana Sayfa')}</strong></div></a>
        <a class="sidebar-nav ${['home','discover'].includes(p)?'active':''}" href="./discover.php"><span>⌕</span><div><strong data-i18n="browse">${t('browse','Gözat')}</strong></div></a>
        <a class="sidebar-nav ${p==='replay'?'active':''}" href="./replay.php"><span>▶</span><div><strong data-i18n="replays">${t('replays','Tekrarlar')}</strong></div></a>
        <a class="sidebar-nav ${p==='clips'?'active':''}" href="./clips.php"><span>✂</span><div><strong data-i18n="clips">${t('clips','Klipler')}</strong></div></a>
        <a class="sidebar-nav ${p==='moments'?'active':''}" href="./moments.php"><span>◈</span><div><strong data-i18n="moments">${t('moments','Anlar')}</strong></div></a>
        <a class="sidebar-nav ${p==='messages'?'active':''}" href="./messages.php"><span>💬</span><div><strong>${t('messages','Mesajlar')}</strong></div></a>
        ${me?.accountMode==='creator'?`<a class="sidebar-nav ${p==='studio'?'active':''}" href="./studio.php"><span>●</span><div><strong data-i18n="studio">${t('studio','Creator Studio')}</strong></div></a>`:''}
        <a class="sidebar-nav ${p==='profile'?'active':''}" href="./profile.php"><span>◎</span><div><strong data-i18n="profile">${t('profile','Profilim')}</strong></div></a>
        ${me?.accountMode==='creator'?`<a class="sidebar-nav ${p==='channel'?'active':''}" href="./channel.php"><span>▤</span><div><strong data-i18n="channel">${t('channel','Kanalım')}</strong></div></a>`:''}
        ${me?.isAdmin?`<a class="sidebar-nav ${p==='admin'?'active':''}" href="./admin.php"><span>🛡</span><div><strong>Admin Paneli</strong></div></a>`:''}
        <a class="sidebar-nav" href="./settings.php#membership"><span>✦</span><div><strong data-i18n="membership">${t('membership','Üyelik')}</strong></div></a>
        <a class="sidebar-nav ${p==='settings'?'active':''}" href="./settings.php"><span>⚙</span><div><strong data-i18n="settings">${t('settings','Ayarlar')}</strong></div></a>`;
    }
  }

  function suggestionRow(href, icon, title, meta='') {
    return `<a class="search-suggestion" href="${href}"><span>${icon}</span><div><strong>${esc(title)}</strong>${meta?`<small>${esc(meta)}</small>`:''}</div></a>`;
  }
  function bindSearch(input) {
    if (!input || input.dataset.v12Search) return; input.dataset.v12Search='1';
    const wrap = input.closest('.header-search,.v9-search,.compact-search') || input.parentElement; if(!wrap) return;
    wrap.style.position='relative';
    const box=document.createElement('div'); box.className='v12-search-suggestions'; wrap.appendChild(box);
    let timer=0, controller=null;
    const hide=()=>box.classList.remove('open');
    const render=async()=>{
      const q=input.value.trim(); if(q.length<1){hide();return;}
      clearTimeout(timer); timer=setTimeout(async()=>{
        try{ controller?.abort(); controller=new AbortController(); const data=await LogiApp.api(`/search.php?q=${encodeURIComponent(q)}`,{signal:controller.signal});
          const chunks=[];
          if(data.streams?.length) chunks.push(`<div class="search-group-title">${t('live_streams','Canlı Yayınlar')}</div>`+data.streams.slice(0,4).map(s=>suggestionRow(`./watch.php?id=${s.id}`,'●',s.title,`${s.streamerName} • ${s.category}`)).join(''));
          if(data.channels?.length) chunks.push(`<div class="search-group-title">${t('channel','Kanallar')}</div>`+data.channels.slice(0,4).map(c=>suggestionRow(`./channel.php?username=${encodeURIComponent(c.username)}`,'◎',c.displayName,`${c.publicId?c.publicId+' • ':''}@${c.username}`)).join(''));
          if(data.categories?.length) chunks.push(`<div class="search-group-title">${t('categories','Kategoriler')}</div>`+data.categories.slice(0,4).map(c=>suggestionRow(`./discover.php?q=${encodeURIComponent(c.name)}`,c.icon||'▣',c.name,'')).join(''));
          if(data.recordings?.length) chunks.push(`<div class="search-group-title">${t('replays','Tekrarlar')}</div>`+data.recordings.slice(0,4).map(r=>suggestionRow(`./replay.php?id=${r.id}`,'▶',r.title,r.display_name||'')).join(''));
          if(data.clips?.length) chunks.push(`<div class="search-group-title">${t('clips','Klipler')}</div>`+data.clips.slice(0,4).map(c=>suggestionRow(`./clips.php?id=${c.id}`,'✂',c.title,c.display_name||'')).join(''));
          box.innerHTML=chunks.join('')||`<div class="menu-empty">${t('no_results','Sonuç bulunamadı')}</div>`; box.classList.add('open');
        }catch(e){ if(e.name!=='AbortError') hide(); }
      },160);
    };
    input.addEventListener('input',render); input.addEventListener('focus',()=>{if(input.value.trim())render();});
    document.addEventListener('click',e=>{if(!wrap.contains(e.target))hide()});
    input.addEventListener('keydown',e=>{ if(e.key==='Escape')hide(); if(e.key==='Enter'&&input.value.trim()&&!box.querySelector(':focus')) window.location.href=`./discover.php?q=${encodeURIComponent(input.value.trim())}`; });
  }

  function bindGlobalSearches(){ ['globalSearch','searchInput','replaySearch','clipSearch'].forEach(id=>bindSearch(document.getElementById(id))); const generic=document.querySelector('.app-header input[type="search"]');bindSearch(generic); }

  function addWatchDrawerButton(){
    if(page()!=='watch'||document.querySelector('.v12-chat-drawer-btn'))return;
    const actions=document.querySelector('.stream-detail-actions'); if(!actions)return;
    const b=document.createElement('button');b.className='btn btn-ghost v12-chat-drawer-btn';b.type='button';b.style.display='none';b.innerHTML='💬';b.addEventListener('click',()=>document.body.classList.toggle('chat-drawer-open'));actions.appendChild(b);
  }

  function bindShortcut(){ document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){const input=document.querySelector('.app-header input[type="search"]');if(input){e.preventDefault();input.focus();input.select();}}}); }

  async function init(){
    document.body.classList.add('logi-v12'); updateBranding(); ensureHeaderLinks();
    let me=null; try{me=await LogiApp.getCurrentUser();}catch(_){ }
    ensureSidebarLinks(me);ensureMobileNav(me);bindGlobalSearches();addWatchDrawerButton();bindShortcut();window.LogiI18n?.apply(document);
    document.addEventListener('logi:languagechange',()=>{ensureHeaderLinks();window.LogiI18n?.apply(document)});
  }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',init); else init();
})();
