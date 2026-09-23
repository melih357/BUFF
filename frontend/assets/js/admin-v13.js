document.addEventListener('DOMContentLoaded', async () => {
  const api = LogiApp.api;
  const esc = LogiApp.escapeHtml;
  const fmt = LogiApp.formatNumber;
  let currentView = 'dashboard';
  let currentRows = [];
  let searchTimer = null;
  let currentAdminId = 0;
  let adminSession = null;

  const meta = {
    dashboard:['Dashboard','Platformun canlı durumu, moderasyon kuyruğu ve operasyon metrikleri.'],
    content_titles:['Katalog & Meta Veri','Film, dizi ve canlı içeriklerin başlık, açıklama, tür, kadro, yönetmen ve yaş sınırı bilgileri.'],
    assets:['Medya Deposu','Video, fragman, altyazı, çoklu dil ses ve poster dosyalarını yönetin.'],
    seasons:['Sezon Mimarisi','Dizilerin sezon hiyerarşisini yönetin.'],
    episodes:['Bölüm Mimarisi','Sezonlara bağlı bölümleri, video yollarını ve durumlarını yönetin.'],
    collections:['Kategori & Koleksiyonlar','Öne Çıkanlar, Trendler ve manuel vitrin koleksiyonlarını yönetin.'],
    categories:['Canlı Yayın Kategorileri','Canlı yayın keşfet kategorilerini yönetin.'],
    vod_clips:['VOD / Klipler','Yayın tekrarları ve kullanıcı kliplerini görünürlük bazında yönetin.'],
    users:['Kullanıcı Profilleri','Üye listesi, hesap durumları, şikayet sayıları ve bağlı cihazları yönetin.'],
    sessions:['Oturumlar / Bağlı Cihazlar','Aktif ve yakın dönem kullanıcı oturumlarını IP ve cihaz bilgileriyle izleyin.'],
    memberships:['Paket & Abonelik Ayarları','Ücretsiz, Plus, Pro ve Premium paket özelliklerini yönetin.'],
    user_memberships:['Aktif Üyelikler','Kullanıcılara tanımlı VIP/üyelik seviyelerini ve bitiş tarihlerini izleyin.'],
    payments:['Ödeme & Fatura Takibi','Başarılı/başarısız ödemeler, üyelik ve coin işlemlerini izleyin.'],
    streams:['Canlı Yayın Yönetimi','Canlı kanalları, gecikmeyi, izleyiciyi ve güvenlik durumunu yönetin.'],
    stream_keys:['Stream Key Yönetimi','Yayıncılar için yayın anahtarı üretin ve iptal edin.'],
    epg:['EPG Takvimi','Canlı kanal program akışını başlangıç ve bitiş saatleriyle planlayın.'],
    transcoding:['Transcoding / Encoding','Video dönüşüm kuyruklarını, HLS/DASH profillerini ve ilerleme durumunu izleyin.'],
    drm:['DRM & Geo-blocking','Widevine/FairPlay bayrakları ve coğrafi erişim politikalarını yönetin.'],
    security:['Güvenlik / IP','IP engelleri ve temel erişim güvenliği politikalarını yönetin.'],
    reports:['Kanal / İçerik Şikayetleri','Yayıncı, kanal, yayın, chat ve topluluk şikayetlerini yanıtlayın.'],
    site_complaints:['Site Şikayetleri','Platformun kendisi hakkında gönderilen şikayetleri ayrı kuyrukta yönetin.'],
    support:['Destek Kutusu','Teknik, ödeme ve genel destek taleplerine yanıt verin.'],
    analytics:['İzleyici Analizleri','Kullanıcı, yayın, izlenme ve içerik metriklerini tek noktadan izleyin.'],
    revenue:['Gelir & Finans','Aylık gelir ve başarılı ödeme trendlerini izleyin.'],
    roles:['RBAC Roller','Süper admin, içerik yöneticisi, editör ve müşteri temsilcisi rollerini görün.'],
    role_users:['Rol Atamaları','Yönetim rollerini kullanıcılara atayın veya kaldırın.'],
    announcements:['Bildirim Yönetimi','Tüm kullanıcılara, yayıncılara veya VIP üyelere uygulama içi duyuru gönderin.'],
    settings:['Site Ayarları','Platform davranışlarını anahtar/değer bazında yönetin.'],
    logs:['Sistem Sağlığı & Audit Logları','Yönetici işlemleri ve güvenlik denetim geçmişini görüntüleyin.']
  };

  const schemas = {
    users:[['BUFF ID','public_id'],['DB','id'],['Kullanıcı','user'],['Hesap','account'],['Durum','status'],['Cihaz','device_count'],['Şikayet','report_count'],['Coin / Kazanç','money'],['Kayıt','created_at'],['İşlem','actions']],
    sessions:[['ID','id'],['Kullanıcı','user'],['IP','ip_address'],['Cihaz / Tarayıcı','user_agent'],['Son Aktivite','last_seen_at']],
    streams:[['ID','id'],['Yayın','stream'],['Durum','status'],['İzleyici','viewers'],['Gecikme','delay'],['+18','adult'],['Destek','gift_total'],['İşlem','actions']],
    stream_keys:[['ID','id'],['Yayıncı','user'],['Stream Key','stream_key'],['Durum','status'],['Son kullanım','last_used_at'],['İşlem','actions']],
    epg:[['ID','id'],['Kanal','channel_name'],['Program','title'],['Stream','stream_title'],['Başlangıç','start_at'],['Bitiş','end_at'],['Durum','status'],['İşlem','actions']],
    transcoding:[['ID','id'],['Asset','asset_label'],['Girdi','input_url'],['Profil','output_profile'],['Durum','status'],['İlerleme','progress'],['Hata','error_message'],['İşlem','actions']],
    drm:[['ID','id'],['Politika','name'],['Widevine','widevine_enabled'],['FairPlay','fairplay_enabled'],['Geo mod','geo_mode'],['İzinli ülkeler','allowed_countries'],['Engelli ülkeler','blocked_countries'],['Durum','status'],['İşlem','actions']],
    reports:[['ID','id'],['Tür','report_scope'],['Şikayet eden','reporter'],['Hedef','target'],['Kategori','category'],['Açıklama','description'],['Yanıt','admin_reply'],['Durum','status'],['Tarih','created_at'],['İşlem','actions']],
    site_complaints:[['ID','id'],['Kullanıcı','user'],['Konu','subject'],['Mesaj','message'],['Yanıt','admin_reply'],['Durum','status'],['Tarih','created_at'],['İşlem','actions']],
    support:[['ID','id'],['Kullanıcı','user'],['Tür','ticket_type'],['Konu','subject'],['Mesaj','message'],['Yanıt','admin_reply'],['Durum','status'],['Tarih','created_at'],['İşlem','actions']],
    payments:[['ID','id'],['Kullanıcı','user'],['Tür','order_type'],['Tutar','amount'],['Coin','coin_amount'],['Durum','status'],['Tarih','created_at']],
    memberships:[['ID','id'],['Kod','code'],['Paket','name'],['Fiyat','plan_price'],['Kalite','quality'],['Reklam','ads_enabled'],['Aile','family_slots'],['Durum','active_flag'],['İşlem','actions']],
    user_memberships:[['Kullanıcı','user'],['Seviye','vip_tier'],['Bitiş','vip_until'],['E-posta','email']],
    content_titles:[['ID','id'],['Tür','content_type'],['Başlık','title'],['Tür / Genre','genre'],['Yönetmen','director'],['Yaş','age_rating'],['Durum','status'],['Güncelleme','updated_at'],['İşlem','actions']],
    assets:[['ID','id'],['İçerik','title'],['Asset','asset_type'],['Etiket','label'],['Dil','language_code'],['Dosya','file_url'],['İşlem','actions']],
    seasons:[['ID','id'],['Dizi','title'],['Sezon','season_number'],['Ad','name'],['İşlem','actions']],
    episodes:[['ID','id'],['Dizi','series_title'],['Sezon','season_number'],['Bölüm','episode_number'],['Başlık','title'],['Video','video_url'],['Durum','status'],['İşlem','actions']],
    collections:[['ID','id'],['Koleksiyon','name'],['Slug','slug'],['Tür','collection_type'],['İçerik','item_count'],['Sıra','sort_order'],['Durum','active_flag'],['İşlem','actions']],
    categories:[['ID','id'],['Kategori','name'],['Slug','slug'],['İkon','icon'],['Sıra','sort_order'],['Durum','active_flag'],['İşlem','actions']],
    vod_clips:[['Tür','kind'],['ID','id'],['İçerik','title'],['Yayıncı','username'],['Süre','duration_seconds'],['Görünürlük','visibility'],['Tarih','created_at'],['İşlem','actions']],
    security:[['ID','id'],['IP','ip_address'],['Sebep','reason'],['Durum','active_flag'],['Oluşturma','created_at'],['İşlem','actions']],
    analytics:[['Metrik','metric'],['Değer','value'],['Açıklama','detail']],
    revenue:[['Dönem','period'],['Ödeme','payment_count'],['Gelir','revenue_kurus']],
    roles:[['ID','id'],['Kod','code'],['Rol','name'],['Yetkiler','permissions_json'],['Üye','member_count']],
    role_users:[['BUFF ID','public_id'],['DB','id'],['Kullanıcı','user'],['Rol','role_name'],['Kod','role_code'],['Bitiş','expires_at'],['Yetki veren','granted_by_username'],['İşlem','actions']],
    announcements:[['ID','id'],['Başlık','title'],['Mesaj','body'],['Hedef','target_scope'],['Kanal','channel'],['Durum','active_flag'],['Tarih','created_at'],['İşlem','actions']],
    settings:[['Ayar','setting_key'],['Değer','setting_value'],['Tür','setting_type'],['Public','is_public'],['Güncelleme','updated_at'],['İşlem','actions']],
    logs:[['ID','id'],['Admin','admin_username'],['Aksiyon','action_name'],['Hedef','audit_target'],['Tarih','created_at']]
  };

  const formConfigs = {
    content_titles:{title:'Yeni İçerik',action:'cms_title_create',fields:[['contentType','İçerik türü','select',[['film','Film'],['series','Dizi'],['live','Canlı yayın']]],['title','Başlık','text'],['genre','Tür / Genre','text'],['director','Yönetmen','text'],['ageRating','Yaş sınırı','text'],['description','Açıklama','textarea'],['cast','Oyuncu kadrosu','textarea'],['posterUrl','Afiş URL / yolu','text'],['trailerUrl','Fragman URL / yolu','text'],['status','Durum','select',[['draft','Taslak'],['published','Yayında'],['archived','Arşiv']]]]},
    assets:{title:'Medya Asset Ekle',action:'asset_create',fields:[['titleId','İçerik ID','number'],['assetType','Asset türü','select',[['video','Video'],['trailer','Fragman'],['subtitle','Altyazı'],['audio','Ses'],['poster','Poster']]],['label','Etiket','text'],['languageCode','Dil kodu','text'],['file','Bilgisayardan dosya yükle','file'],['fileUrl','Dosya URL / yolu (dosya seçmezsen)','text'],['mimeType','MIME türü','text']]},
    seasons:{title:'Sezon Ekle',action:'season_create',fields:[['titleId','Dizi ID','number'],['seasonNumber','Sezon no','number'],['name','Sezon adı','text']]},
    episodes:{title:'Bölüm Ekle',action:'episode_create',fields:[['seasonId','Sezon ID','number'],['episodeNumber','Bölüm no','number'],['title','Bölüm başlığı','text'],['description','Açıklama','textarea'],['videoUrl','Video URL / yolu','text'],['durationSeconds','Süre (sn)','number'],['status','Durum','select',[['draft','Taslak'],['published','Yayında']]]]},
    collections:{title:'Koleksiyon Ekle',action:'collection_create',fields:[['name','Koleksiyon adı','text'],['collectionType','Tür','select',[['manual','Manuel'],['featured','Öne Çıkanlar'],['trend','Trendler'],['genre','Türe göre']]],['sortOrder','Sıra','number']]},
    categories:{title:'Kategori Ekle',action:'category_create',fields:[['name','Kategori adı','text'],['icon','İkon','text'],['sortOrder','Sıra','number']]},
    memberships:{title:'Paket Kaydet',action:'membership_save',fields:[['code','Paket kodu','text'],['name','Paket adı','text'],['priceKurus','Fiyat (kuruş)','number'],['billingPeriod','Dönem','select',[['monthly','Aylık'],['yearly','Yıllık']]],['quality','Kalite','select',[['HD','HD'],['FHD','Full HD'],['4K','4K']]],['adsEnabled','Reklam','select',[['0','Reklamsız'],['1','Reklamlı']]],['familySlots','Profil / aile slotu','number']]},
    stream_keys:{title:'Stream Key Üret',action:'stream_key_generate',fields:[['userId','Yayıncı BUFF ID / kullanıcı no','text']]},
    epg:{title:'EPG Programı Ekle',action:'epg_create',fields:[['streamId','Stream ID (opsiyonel)','number'],['channelName','Kanal adı','text'],['title','Program başlığı','text'],['startAt','Başlangıç','datetime-local'],['endAt','Bitiş','datetime-local'],['status','Durum','select',[['scheduled','Planlandı'],['live','Canlı'],['ended','Bitti']]]]},
    transcoding:{title:'Transcode İşine Ekle',action:'transcode_create',fields:[['assetId','Asset ID (opsiyonel)','number'],['inputUrl','Giriş video yolu','text'],['outputProfile','Çıkış profili','select',[['HLS_AUTO','HLS Auto'],['HLS_1080P','HLS 1080p'],['DASH_AUTO','DASH Auto'],['MP4_720P','MP4 720p']]]]},
    drm:{title:'DRM / Geo Politikası',action:'drm_save',fields:[['name','Politika adı','text'],['widevineEnabled','Widevine','select',[['0','Kapalı'],['1','Açık']]],['fairplayEnabled','FairPlay','select',[['0','Kapalı'],['1','Açık']]],['geoMode','Geo modu','select',[['off','Kapalı'],['allowlist','Sadece izinli ülkeler'],['blocklist','Engelli ülkeler']]],['allowedCountries','İzinli ülke kodları','text'],['blockedCountries','Engelli ülke kodları','text'],['status','Durum','select',[['draft','Taslak'],['active','Aktif']]]]},
    security:{title:'IP Engelle',action:'ip_block_create',fields:[['ipAddress','IP adresi','text'],['reason','Sebep','textarea']]},
    role_users:{title:'Rol Ata',action:'role_assign',fields:[['userId','Kullanıcı BUFF ID / kullanıcı no','text'],['roleId','Rol ID','number'],['duration','Yetki süresi','select',[['1h','1 saat'],['3h','3 saat'],['1d','1 gün'],['7d','1 hafta'],['permanent','Süresiz']]]]},
    announcements:{title:'Duyuru Gönder',action:'announcement_send',fields:[['title','Başlık','text'],['body','Mesaj','textarea'],['targetScope','Hedef','select',[['all','Tüm kullanıcılar'],['creators','Yayıncılar'],['vip','VIP üyeler']]]]},
    settings:{title:'Site Ayarı',action:'setting_update',fields:[['key','Ayar anahtarı','text'],['value','Değer','textarea'],['settingType','Tür','select',[['string','Metin'],['integer','Sayı'],['boolean','Boolean'],['json','JSON']]],['isPublic','Public','select',[['0','Hayır'],['1','Evet']]]]}
  };

  function toast(message,type='success'){
    const root=document.getElementById('adminToastRoot'); const el=document.createElement('div'); el.className=`admin-toast ${type}`; el.textContent=message; root.appendChild(el); setTimeout(()=>el.remove(),3600);
  }
  function status(v){ const x=String(v??'—').toLowerCase(); return `<span class="admin-status-chip ${esc(x)}">${esc(String(v??'—'))}</span>`; }
  function actions(...items){ return `<div class="admin-row-actions">${items.filter(Boolean).join('')}</div>`; }
  function btn(label,action,id,cls='',extra=''){ return `<button class="admin-row-btn ${cls}" data-action="${action}" data-id="${Number(id||0)}" ${extra}>${esc(label)}</button>`; }
  function moneyKurus(v){ return new Intl.NumberFormat('tr-TR',{style:'currency',currency:'TRY'}).format(Number(v||0)/100); }
  function compactKey(k){ const s=String(k||''); return s.length>26?s.slice(0,9)+'…'+s.slice(-8):s; }

  function renderCell(r,key){
    switch(key){
      case 'user': return `<strong>${esc(r.display_name||r.username||'—')}</strong><small>@${esc(r.username||'')} ${r.email?'• '+esc(r.email):''}</small>`;
      case 'account': { const role=r.admin_role_summary?`<small>${esc(r.admin_role_summary)}${Number(r.admin_permanent)?' • süresiz':(r.admin_expires_at?' • '+esc(r.admin_expires_at):'')}</small>`:''; const vip=r.vip_tier&&r.vip_until?`<small>${esc(String(r.vip_tier).toUpperCase())} • ${esc(r.vip_until)}</small>`:''; return `${esc(r.account_mode||'viewer')}${Number(r.is_admin)?' • ADMIN':''}${role}${vip}`; }
      case 'status': return status(r.account_status??r.status??(Number(r.is_live)?'live':'offline'));
      case 'active_flag': return status(Number(r.is_active)?'active':'disabled');
      case 'money': return `${fmt(r.wallet_balance||0)} / ${fmt(r.creator_earnings||0)}`;
      case 'created_at': case 'updated_at': case 'last_seen_at': case 'vip_until': return esc(r[key]||'—');
      case 'stream': return `<strong>${esc(r.title||'—')}</strong><small>@${esc(r.username||'')} • ${esc(r.category||'')}</small>`;
      case 'viewers': case 'gift_total': case 'device_count': case 'report_count': case 'family_slots': case 'item_count': case 'season_number': case 'episode_number': return fmt(r[key]||0);
      case 'delay': return `${Number(r.delay_seconds||0)} sn`;
      case 'adult': return Number(r.adult_only)?'18+':'—';
      case 'reporter': return '@'+esc(r.reporter_username||'—');
      case 'target': return `<strong>@${esc(r.target_username||'—')}</strong>${r.target_snapshot?`<small>${esc(r.target_snapshot)}</small>`:''}`;
      case 'description': case 'message': case 'admin_reply': case 'body': return `<span style="white-space:normal;display:block;max-width:360px">${esc(r[key]||'—')}</span>`;
      case 'amount': return moneyKurus(r.amount_kurus||0);
      case 'plan_price': return moneyKurus(r.price_kurus||0);
      case 'revenue_kurus': return moneyKurus(r.revenue_kurus||0);
      case 'ads_enabled': return Number(r.ads_enabled)?'Reklamlı':'Reklamsız';
      case 'widevine_enabled': case 'fairplay_enabled': return Number(r[key])?'Açık':'Kapalı';
      case 'progress': return `${Number(r.progress||0)}%`;
      case 'start_at': case 'end_at': return esc(r[key]||'—');
      case 'file_url': return `<code title="${esc(r.file_url||'')}">${esc(compactKey(r.file_url||'—'))}</code>`;
      case 'video_url': return r.video_url?`<code>${esc(compactKey(r.video_url))}</code>`:'—';
      case 'stream_key': return `<code title="${esc(r.stream_key||'')}">${esc(compactKey(r.stream_key||''))}</code>`;
      case 'permissions_json': return `<code>${esc(compactKey(r.permissions_json||'[]'))}</code>`;
      case 'audit_target': return `${esc(r.target_type||'—')} ${r.target_id?'#'+esc(String(r.target_id)):''}`;
      case 'actions': return rowActions(r);
      default: return esc(String(r[key]??'—'));
    }
  }

  function rowActions(r){
    if(currentView==='users') {
      const self=Number(r.id)===Number(currentAdminId); const items=[];
      items.push(btn(r.vip_tier&&r.vip_until?'VIP Değiştir':'VIP Ver','vip_grant',r.id,'primary'));
      if(r.vip_tier&&r.vip_until) items.push(btn('VIP Kaldır','vip_revoke',r.id,'danger'));
      if(!self){
        if(Number(r.is_admin)) items.push(btn('Adminliği Kaldır','admin_revoke',r.id,'danger'));
        else items.push(btn('Admin Yap','admin_grant',r.id));
        const accountStatus=String(r.account_status||'active');
        if(accountStatus==='active'){
          items.push(btn('Engelle','user_block',r.id));items.push(btn('Banla','user_ban',r.id,'danger'));
        }else if(['banned','blocked'].includes(accountStatus)) items.push(btn('Aktifleştir','user_activate',r.id,'primary'));
      }else items.push('<span class="admin-self-chip">Bu sensin</span>');
      return actions(...items);
    }
    if(currentView==='streams') return Number(r.is_live)?actions(btn('Yayını kapat','stream_stop',r.id,'danger')):'—';
    if(currentView==='stream_keys') return r.status==='active'?actions(btn('İptal et','stream_key_revoke',r.id,'danger')):'—';
    if(currentView==='epg') return actions(btn('Sil','epg_delete',r.id,'danger'));
    if(currentView==='transcoding') return actions(btn('İşleniyor','transcode_status',r.id,'',`data-status="processing" data-progress="30"`),btn('Tamamlandı','transcode_status',r.id,'primary',`data-status="completed" data-progress="100"`),btn('Hata','transcode_status',r.id,'danger',`data-status="failed" data-progress="0"`));
    if(currentView==='drm') return actions(btn(r.status==='active'?'Taslağa al':'Aktifleştir','drm_toggle',r.id),btn('Sil','drm_delete',r.id,'danger'));
    if(currentView==='reports') return actions(btn('Yanıtla','report_reply',r.id,'primary'),r.status==='resolved'?btn('Yeniden aç','report_reopen',r.id):btn('Çöz','report_resolve',r.id));
    if(currentView==='support'||currentView==='site_complaints') return actions(btn('Yanıtla','support_reply',r.id,'primary'),r.status==='closed'?'':btn('Kapat','support_close',r.id));
    if(currentView==='content_titles') return actions(btn(r.status==='published'?'Taslağa al':'Yayınla','cms_title_publish',r.id),btn('Sil','cms_title_delete',r.id,'danger'));
    if(currentView==='assets') return actions(btn('Önizle','asset_preview',r.id,'primary',`data-url="${esc(r.file_url||'')}" data-kind="${esc(r.asset_type||'')}" data-title="${esc(r.label||r.title||'Medya')}"`),btn('Sil','asset_delete',r.id,'danger'));
    if(currentView==='seasons') return actions(btn('Sil','season_delete',r.id,'danger'));
    if(currentView==='episodes') return actions(r.video_url?btn('Önizle','asset_preview',r.id,'primary',`data-url="${esc(r.video_url)}" data-kind="video" data-title="${esc(r.title||'Bölüm')}"`):'',btn('Sil','episode_delete',r.id,'danger'));
    if(currentView==='collections') return actions(btn(Number(r.is_active)?'Pasifleştir':'Aktifleştir','collection_toggle',r.id),btn('Sil','collection_delete',r.id,'danger'));
    if(currentView==='categories') return actions(btn(Number(r.is_active)?'Pasifleştir':'Aktifleştir','category_toggle',r.id),btn('Sil','category_delete',r.id,'danger'));
    if(currentView==='memberships') return actions(btn(Number(r.is_active)?'Pasifleştir':'Aktifleştir','membership_toggle',r.id));
    if(currentView==='vod_clips'){ const next=r.visibility==='private'?'public':'private'; return actions(btn(next==='private'?'Gizle':'Yayınla','content_visibility',r.id,'',`data-kind="${esc(r.kind)}" data-visibility="${next}"`),btn('Sil','content_delete',r.id,'danger',`data-kind="${esc(r.kind)}"`)); }
    if(currentView==='security') return actions(btn(Number(r.is_active)?'Pasifleştir':'Aktifleştir','ip_block_toggle',r.id),btn('Sil','ip_block_delete',r.id,'danger'));
    if(currentView==='role_users') return actions(btn('Rolü kaldır','role_unassign',r.id,'danger'));
    if(currentView==='announcements') return actions(btn(Number(r.is_active)?'Pasifleştir':'Aktifleştir','announcement_toggle',r.id));
    if(currentView==='settings') return actions(btn('Düzenle','setting_edit',0,'primary',`data-key="${esc(r.setting_key)}" data-value="${esc(r.setting_value||'')}" data-type="${esc(r.setting_type||'string')}" data-public="${Number(r.is_public)?1:0}"`));
    return '—';
  }

  function renderTable(){
    const schema=schemas[currentView]||[];
    document.getElementById('adminTableHead').innerHTML=`<tr>${schema.map(([l])=>`<th>${esc(l)}</th>`).join('')}</tr>`;
    document.getElementById('adminTableBody').innerHTML=currentRows.map(r=>`<tr data-row-id="${Number(r.id||0)}">${schema.map(([,k])=>`<td>${renderCell(r,k)}</td>`).join('')}</tr>`).join('');
    document.getElementById('adminRowCount').textContent=`${currentRows.length} kayıt`;
    document.getElementById('adminTableEmpty').classList.toggle('hidden',currentRows.length>0);
    if(currentView==='collections') enableCollectionDrag();
  }

  function contextButton(){
    const cfg=formConfigs[currentView];
    const host=document.getElementById('adminContextActions');
    const tools=document.getElementById('adminTableActions');
    host.innerHTML='';tools.innerHTML='';
    if(cfg){ const html=`<button class="admin-quick-btn" data-add-view="${currentView}">+ ${esc(cfg.title)}</button>`; host.innerHTML=html; tools.innerHTML=html; }
  }

  async function loadView(view){
    currentView=view;
    document.querySelectorAll('[data-view]').forEach(b=>b.classList.toggle('active',b.dataset.view===view));
    const [title,desc]=meta[view]||[view,'']; document.getElementById('adminPageTitle').textContent=title; document.getElementById('adminPageDesc').textContent=desc;
    document.getElementById('adminDashboard').classList.toggle('hidden',view!=='dashboard'); document.getElementById('adminDataView').classList.toggle('hidden',view==='dashboard');
    contextButton();
    if(view==='dashboard'){ await loadDashboard(); return; }
    document.getElementById('adminTableTitle').textContent=title;
    try{ const q=document.getElementById('adminSearch').value.trim(); const data=await api(`/admin/list.php?type=${encodeURIComponent(view)}&q=${encodeURIComponent(q)}`); currentRows=data.rows||[]; renderTable(); }
    catch(e){ currentRows=[];renderTable();toast(e.message,'error'); }
  }

  async function loadDashboard(){
    try{
      const [d,rev]=await Promise.all([api('/admin/overview.php'),api('/admin/list.php?type=revenue')]);
      const m=d.metrics||{}; document.getElementById('reportBadge').textContent=m.openReports||'';document.getElementById('supportBadge').textContent=m.openSupport||'';
      document.getElementById('reportBadge').style.display=m.openReports?'grid':'none'; document.getElementById('supportBadge').style.display=m.openSupport?'grid':'none';
      const cards=[['Toplam kullanıcı',m.users],['Canlı yayın',m.liveStreams],['Aktif kullanıcı',m.activeUsers],['Açık şikayet',m.openReports],['Açık destek',m.openSupport],['Bugünkü gelir',moneyKurus(m.todayRevenueKurus)]];
      document.getElementById('adminMetrics').innerHTML=cards.map(x=>`<article class="admin-metric-card"><span>${esc(x[0])}</span><strong>${typeof x[1]==='number'?fmt(x[1]):esc(x[1]||'0')}</strong><small>Canlı sistem verisi</small></article>`).join('');
      const h=d.health||{}; document.getElementById('adminHealth').innerHTML=[['Veritabanı',h.database||'—'],['PHP',h.php||'—'],['Bellek',h.memoryUsageMb!=null?`${h.memoryUsageMb} MB`:'—'],['Disk boş',h.diskFreeGb!=null?`${h.diskFreeGb} GB`:'—'],['CDN',h.cdn==='not_configured'?'Provider bağlı değil':h.cdn],['Users',h.tables?.users?'OK':'Eksik'],['Streams',h.tables?.streams?'OK':'Eksik'],['Notifications',h.tables?.notifications?'OK':'Eksik'],['Sunucu saati',h.serverTime||'—']].map(x=>`<div class="health-item"><span>${esc(x[0])}</span><strong>${esc(String(x[1]))}</strong></div>`).join('');
      document.getElementById('recentReports').innerHTML=(d.recentReports||[]).length?(d.recentReports||[]).map(r=>`<div class="admin-activity-item"><div><strong>#${r.id} @${esc(r.reporter||'')}</strong><small>${esc(r.report_scope||'')} → @${esc(r.target||'')}</small></div><em>${esc(r.status||'')}</em></div>`).join(''):'<div class="admin-empty">Şikayet yok.</div>';
      document.getElementById('recentSupport').innerHTML=(d.recentSupport||[]).length?(d.recentSupport||[]).map(r=>`<div class="admin-activity-item"><div><strong>#${r.id} ${esc(r.subject||'')}</strong><small>@${esc(r.username||'')} • ${esc(r.ticket_type||'')}</small></div><em>${esc(r.status||'')}</em></div>`).join(''):'<div class="admin-empty">Destek talebi yok.</div>';
      document.getElementById('liveOperations').innerHTML=(d.topStreams||[]).length?(d.topStreams||[]).map(r=>`<div class="admin-activity-item"><div><strong>${esc(r.title||'')}</strong><small>@${esc(r.username||'')} • ${fmt(r.gift_total||0)} destek</small></div><em>${Number(r.is_live)?'CANLI':'BİTTİ'}</em></div>`).join(''):'<div class="admin-empty">Yayın yok.</div>';
      const rr=(rev.rows||[]).slice().reverse(); const max=Math.max(1,...rr.map(x=>Number(x.revenue_kurus||0))); document.getElementById('revenueChart').innerHTML=rr.length?rr.map(x=>`<div class="admin-bar" style="height:${Math.max(8,Math.round(Number(x.revenue_kurus||0)/max*150))}px"><b>${esc(moneyKurus(x.revenue_kurus))}</b><span>${esc(x.period||'')}</span></div>`).join(''):'<div class="admin-empty">Henüz gelir verisi yok.</div>';
    }catch(e){toast(e.message,'error');}
  }

  function openModal(id){document.getElementById(id)?.classList.add('open')}
  function closeModal(id){document.getElementById(id)?.classList.remove('open')}
  document.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click',()=>closeModal(b.dataset.close)));
  document.querySelectorAll('.admin-modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)closeModal(m.id)}));

  function fieldHtml(f){ const [name,label,type,opts]=f; if(type==='textarea')return `<label class="full"><span>${esc(label)}</span><textarea name="${name}" rows="4"></textarea></label>`; if(type==='select')return `<label><span>${esc(label)}</span><select name="${name}">${opts.map(o=>`<option value="${esc(o[0])}">${esc(o[1])}</option>`).join('')}</select></label>`; return `<label><span>${esc(label)}</span><input name="${name}" type="${type}" ${type==='number'?'step="1"':''}></label>`; }
  function openForm(view,prefill={}){ const cfg=formConfigs[view]; if(!cfg)return; document.getElementById('adminFormTitle').textContent=cfg.title; const form=document.getElementById('adminDynamicForm'); form.dataset.action=cfg.action; form.dataset.view=view; form.innerHTML=cfg.fields.map(fieldHtml).join('')+`<button class="admin-primary-btn full" type="submit">Kaydet</button>`; Object.entries(prefill).forEach(([k,v])=>{const el=form.elements[k];if(el)el.value=v??''}); openModal('adminFormModal'); }

  document.addEventListener('click',e=>{ const a=e.target.closest('[data-add-view]'); if(a)openForm(a.dataset.addView); const q=e.target.closest('[data-quick]'); if(q)openForm(q.dataset.quick); const j=e.target.closest('[data-jump]'); if(j)loadView(j.dataset.jump); });

  document.getElementById('adminDynamicForm').addEventListener('submit',async e=>{ e.preventDefault(); const form=e.currentTarget; const fd=new FormData(form); const data=Object.fromEntries(fd.entries()); Object.keys(data).forEach(k=>{if(['titleId','seasonNumber','seasonId','episodeNumber','durationSeconds','sortOrder','priceKurus','familySlots','roleId'].includes(k))data[k]=Number(data[k]||0); if(['adsEnabled','isPublic','widevineEnabled','fairplayEnabled'].includes(k))data[k]=String(data[k])==='1';}); try{if(form.dataset.action==='asset_create'){const file=form.elements.file?.files?.[0];if(file){const upload=new FormData();upload.append('file',file);const u=await api('/admin/upload-asset.php',{method:'POST',body:upload});data.fileUrl=u.url;data.mimeType=u.mimeType||data.mimeType||'';}delete data.file;if(!data.fileUrl)throw new Error('Dosya seç veya dosya URL/yolu gir.');}const res=await api('/admin/action.php',{method:'POST',body:JSON.stringify({action:form.dataset.action,...data})});toast(res.message||'Kaydedildi');closeModal('adminFormModal');await Promise.all([loadView(currentView),loadDashboard()]);}catch(err){toast(err.message,'error')}});

  document.getElementById('adminReplyForm').addEventListener('submit',async e=>{e.preventDefault();const form=e.currentTarget;const d=Object.fromEntries(new FormData(form).entries());try{await api('/admin/action.php',{method:'POST',body:JSON.stringify({action:d.action,id:Number(d.id),reply:d.reply})});toast('Yanıt gönderildi ve kullanıcının bildirim kutusuna iletildi.');closeModal('adminReplyModal');form.reset();await Promise.all([loadView(currentView),loadDashboard()]);}catch(err){toast(err.message,'error')}});


  document.getElementById('adminVipForm')?.addEventListener('submit',async e=>{
    e.preventDefault();const form=e.currentTarget;const d=Object.fromEntries(new FormData(form).entries());
    try{const res=await api('/admin/action.php',{method:'POST',body:JSON.stringify({action:'vip_grant',id:Number(d.userId),tier:d.tier,days:Number(d.days||30)})});toast(res.message||'Üyelik tanımlandı.');closeModal('adminVipModal');form.reset();await loadView(currentView);}catch(err){toast(err.message,'error')}
  });
  document.getElementById('adminGrantForm')?.addEventListener('submit',async e=>{
    e.preventDefault();const form=e.currentTarget;const d=Object.fromEntries(new FormData(form).entries());
    try{const res=await api('/admin/action.php',{method:'POST',body:JSON.stringify({action:'admin_grant',id:Number(d.userId),roleCode:d.roleCode,duration:d.duration})});toast(res.message||'Admin yetkisi verildi.');closeModal('adminGrantModal');form.reset();await loadView(currentView);}catch(err){toast(err.message,'error')}
  });
  document.getElementById('adminAccountBtn')?.addEventListener('click',()=>{
    const form=document.getElementById('adminEmailForm');if(form&&adminSession?.admin?.email)form.elements.email.value=adminSession.admin.email;openModal('adminAccountModal');
  });
  document.getElementById('adminEmailForm')?.addEventListener('submit',async e=>{
    e.preventDefault();const form=e.currentTarget;const data=Object.fromEntries(new FormData(form).entries());const msg=document.getElementById('adminEmailMessage');
    try{const res=await api('/admin/profile.php',{method:'POST',body:JSON.stringify({action:'change_email',email:data.email,currentPassword:data.currentPassword})});msg.className='form-message success full';msg.textContent=res.message;adminSession.admin=res.admin||adminSession.admin;form.elements.currentPassword.value='';toast(res.message);}catch(err){msg.className='form-message error full';msg.textContent=err.message;}
  });
  document.getElementById('adminPasswordForm')?.addEventListener('submit',async e=>{
    e.preventDefault();const form=e.currentTarget;const data=Object.fromEntries(new FormData(form).entries());const msg=document.getElementById('adminPasswordMessage');
    try{const res=await api('/admin/profile.php',{method:'POST',body:JSON.stringify({action:'change_password',currentPassword:data.currentPassword,newPassword:data.newPassword,confirmPassword:data.confirmPassword})});msg.className='form-message success full';msg.textContent=res.message;form.reset();toast(res.message);}catch(err){msg.className='form-message error full';msg.textContent=err.message;}
  });

  document.getElementById('adminTableBody').addEventListener('click',async e=>{
    const b=e.target.closest('[data-action]'); if(!b)return; const action=b.dataset.action,id=Number(b.dataset.id||0);
    if(action==='asset_preview'){ const url=b.dataset.url||''; const kind=b.dataset.kind||''; document.getElementById('previewTitle').textContent=b.dataset.title||'Önizleme'; document.getElementById('adminPreviewBody').innerHTML=(kind==='poster'||/\.(png|jpe?g|webp|gif)$/i.test(url))?`<img src="${esc(url)}" alt="Önizleme">`:`<video src="${esc(url)}" controls autoplay></video>`;openModal('adminPreviewModal');return; }
    if(action==='report_reply'||action==='support_reply'){ const form=document.getElementById('adminReplyForm');form.elements.id.value=id;form.elements.action.value=action;document.getElementById('adminReplyTitle').textContent=action==='report_reply'?'Şikayeti yanıtla':'Destek talebini yanıtla';openModal('adminReplyModal');return; }
    if(action==='setting_edit'){openForm('settings',{key:b.dataset.key,value:b.dataset.value,settingType:b.dataset.type,isPublic:b.dataset.public});return;}
    if(action==='vip_grant'){ const form=document.getElementById('adminVipForm');form.elements.userId.value=id;form.elements.tier.value='plus';form.elements.days.value='30';openModal('adminVipModal');return; }
    if(action==='admin_grant'){ const form=document.getElementById('adminGrantForm');form.elements.userId.value=id;form.elements.roleCode.value='super_admin';form.elements.duration.value='1d';openModal('adminGrantModal');return; }
    let extra={};
    if(action==='content_visibility'||action==='content_delete')extra={kind:b.dataset.kind,visibility:b.dataset.visibility};
    if(action==='transcode_status')extra={status:b.dataset.status,progress:Number(b.dataset.progress||0)};
    const danger=['user_ban','user_block','stream_stop','cms_title_delete','asset_delete','season_delete','episode_delete','collection_delete','category_delete','content_delete','ip_block_delete','role_unassign','admin_revoke','vip_revoke','stream_key_revoke','epg_delete','drm_delete']; if(danger.includes(action)&&!confirm('Bu işlemi uygulamak istediğine emin misin?'))return;
    try{const res=await api('/admin/action.php',{method:'POST',body:JSON.stringify({action,id,...extra})});toast(res.message||'İşlem tamamlandı.');await Promise.all([loadView(currentView),loadDashboard()]);}catch(err){toast(err.message,'error')}
  });

  function enableCollectionDrag(){
    const body=document.getElementById('adminTableBody'); let drag=null;
    body.querySelectorAll('tr').forEach(tr=>{tr.draggable=true;tr.title='Sıralamak için sürükle';tr.addEventListener('dragstart',()=>{drag=tr;tr.style.opacity='.45'});tr.addEventListener('dragover',e=>{e.preventDefault();if(drag&&drag!==tr){const rect=tr.getBoundingClientRect();body.insertBefore(drag,e.clientY<rect.top+rect.height/2?tr:tr.nextSibling)}});tr.addEventListener('dragend',async()=>{tr.style.opacity='';drag=null;const order=[...body.querySelectorAll('tr')].map(x=>Number(x.dataset.rowId||0)).filter(Boolean);try{await api('/admin/action.php',{method:'POST',body:JSON.stringify({action:'collection_reorder',order})});toast('Koleksiyon sırası kaydedildi.');}catch(err){toast(err.message,'error');await loadView('collections');}});});
  }

  document.getElementById('adminNav').addEventListener('click',e=>{const b=e.target.closest('[data-view]');if(b)loadView(b.dataset.view)});
  document.getElementById('adminRefreshBtn').addEventListener('click',()=>loadView(currentView));
  const searchBox=document.getElementById('adminSearch'); const searchResults=document.getElementById('adminSearchResults');
  searchBox.addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(async()=>{const q=searchBox.value.trim();if(currentView!=='dashboard')loadView(currentView);if(q.length<2){searchResults.classList.add('hidden');searchResults.innerHTML='';return;}try{const d=await api(`/admin/global-search.php?q=${encodeURIComponent(q)}`);const items=d.results||[];searchResults.innerHTML=items.length?items.map(x=>`<button class="admin-search-result" data-search-view="${esc(x.view)}" data-search-id="${Number(x.id||0)}"><strong>${esc(x.title)}</strong><small>${esc(x.subtitle||'')}</small></button>`).join(''):'<div class="admin-empty" style="padding:16px">Sonuç bulunamadı.</div>';searchResults.classList.remove('hidden');}catch(_){searchResults.classList.add('hidden')}},260)});
  searchResults.addEventListener('click',e=>{const b=e.target.closest('[data-search-view]');if(!b)return;searchBox.value=String(b.dataset.searchId||'');searchResults.classList.add('hidden');loadView(b.dataset.searchView);});
  document.addEventListener('click',e=>{if(!e.target.closest('.admin-global-search-wrap'))searchResults.classList.add('hidden')});
  document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();searchBox.focus()}});
  document.getElementById('adminSidebarCollapse').addEventListener('click',()=>document.body.classList.toggle('admin-sidebar-collapsed'));
  document.getElementById('adminMobileMenu').addEventListener('click',()=>document.getElementById('adminSidebar').classList.toggle('mobile-open'));
  document.getElementById('adminLogoutBtn').addEventListener('click',async()=>{await api('/auth/admin-logout.php',{method:'POST',body:'{}'}).catch(()=>{});location.replace('./admin-login.php')});

  try{
    const adminLang=(localStorage.getItem('buff_admin_language')||'tr').toLowerCase();
    window.LogiI18n?.setScopedLanguage?.(['tr','en'].includes(adminLang)?adminLang:'tr','buff_admin_language');
    const langSelect=document.getElementById('adminLanguageSelect'); if(langSelect){langSelect.value=['tr','en'].includes(adminLang)?adminLang:'tr';langSelect.addEventListener('change',()=>{window.LogiI18n?.setScopedLanguage?.(langSelect.value,'buff_admin_language');});}
    const sess=await api('/auth/admin-session.php'); adminSession=sess; currentAdminId=Number(sess.admin?.id||0); const name=sess.admin?.displayName||'BUFF Admin'; document.querySelector('.admin-v13-user strong').textContent=name; document.querySelector('.admin-v13-user small').textContent=(sess.roles||[]).map(r=>r.name).join(', ')||'Süper Admin'; await loadDashboard();
  }catch(e){location.replace('./admin-login.php?expired=1');}
});
