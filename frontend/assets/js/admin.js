document.addEventListener('DOMContentLoaded', async () => {
  const me = await LogiApp.getCurrentUser();
  if (!me) { location.href = './admin-login.php?next=' + encodeURIComponent(location.pathname + location.search); return; }
  if (!me.isAdmin) {
    document.querySelector('.admin-main').innerHTML = '<section class="surface-card access-denied"><h1>Yetkisiz erişim</h1><p>Bu alan yalnızca BUFF yöneticileri içindir.</p><a class="btn btn-primary" href="./index.php">Ana Sayfa</a></section>';
    window.LogiI18n?.apply(document);
    return;
  }

  const esc = LogiApp.escapeHtml;
  const tr = (key, fallback) => window.LogiI18n?.t(key, fallback) || fallback;
  let view = 'users', rows = [];
  const labels = {
    users:['users','Kullanıcılar'], creators:['creators','Yayıncılar'], streams:['live_streams','Canlı Yayınlar'], content:['vod_clips','VOD / Klipler'], chat:['chat_moderation','Chat / Moderasyon'], reports:['reports','Yayıncı / İçerik Şikayetleri'], site_complaints:['site_complaints','Site Şikayetleri'], support:['support_tickets','Destek Talepleri'], payments:['coin_payments','Coin Ödemeleri'], vip_payments:['vip_payments','VIP Ödemeleri'], payouts:['payouts','Yayıncı Çekimleri'], banks:['iban_accounts','IBAN Hesapları'], refunds:['refunds','Hediye İadeleri'], categories:['categories','Kategoriler'], settings:['site_settings','Site Ayarları'], logs:['audit_logs','Denetim Kayıtları']
  };
  const label = key => { const v=labels[key]||[key,key]; return tr(v[0],v[1]); };
  const money = k => new Intl.NumberFormat((window.LogiI18n?.currentLanguage?.()||'tr')==='tr'?'tr-TR':undefined,{style:'currency',currency:'TRY'}).format(Number(k||0)/100);
  const status = s => `<span class="admin-status status-${esc(String(s||'').toLowerCase())}">${esc(String(s||'—'))}</span>`;
  const button = (action,id,text,cls='', extra='') => `<button class="admin-action ${cls}" data-action="${action}" data-id="${Number(id||0)}" ${extra} type="button">${esc(text)}</button>`;

  async function overview() {
    try {
      const d = await LogiApp.api('/admin/overview.php'), m = d.metrics || {};
      const metrics = [
        [tr('active_streams','Aktif yayın'),m.liveStreams],[tr('active_users','Aktif kullanıcı'),m.activeUsers],[tr('today_revenue','Bugünkü ödeme'),money(m.todayRevenueKurus)],
        [tr('pending_payouts','Bekleyen çekim'),m.pendingPayouts],[tr('open_reports','Açık şikayet'),m.openReports],[tr('open_support','Açık destek'),m.openSupport],
        [tr('total_users','Toplam kullanıcı'),m.users],[tr('ready_vod','Hazır VOD'),m.recordings]
      ];
      document.getElementById('adminMetrics').innerHTML = metrics.map(([a,b])=>`<article><span>${esc(a)}</span><strong>${esc(String(b??0))}</strong></article>`).join('');
      document.getElementById('recentReports').innerHTML = (d.recentReports||[]).map(r=>`<button data-open-admin-view="reports"><strong>#${r.id} ${esc(r.category)}</strong><small>${esc(r.reporter)} → ${esc(r.target||r.target_username_snapshot||r.stream_title_snapshot||r.target_snapshot||'hedef')} • ${esc(LogiApp.timeAgo(r.created_at))}</small></button>`).join('') || `<div class="compact-empty">${esc(tr('no_new_reports','Yeni şikayet yok.'))}</div>`;
      document.getElementById('recentPayouts').innerHTML = (d.recentPayouts||[]).map(p=>`<button data-open-admin-view="payouts"><strong>#${p.id} ${esc(p.display_name||p.username)}</strong><small>${LogiApp.formatNumber(p.earning_coins)} coin • ${esc(p.status)} • ${esc(LogiApp.timeAgo(p.created_at))}</small></button>`).join('') || `<div class="compact-empty">${esc(tr('no_payout_requests','Çekim talebi yok.'))}</div>`;
      window.LogiI18n?.apply(document.getElementById('adminMetrics'));
    } catch (e) { LogiApp.toast(e.message,'error'); }
  }

  function schema() {
    const commonUser = [[tr('id','ID'),'id'],[tr('user','Kullanıcı'),'user'],[tr('mode','Mod'),'mode'],[tr('balance','Bakiye'),'money'],[tr('status','Durum'),'status'],[tr('action','İşlem'),'actions']];
    if (view==='users' || view==='creators') return commonUser;
    if (view==='streams') return [[tr('id','ID'),'id'],[tr('streaming','Yayın'),'stream'],[tr('categories','Kategori'),'category'],[tr('viewers','İzleyici'),'viewers'],[tr('status','Durum'),'status'],[tr('action','İşlem'),'actions']];
    if (view==='content') return [[tr('type','Tür'),'kind'],[tr('content','İçerik'),'content'],[tr('channel','Kanal'),'username'],[tr('duration','Süre'),'duration'],[tr('visibility','Görünürlük'),'visibility'],[tr('date','Tarih'),'date'],[tr('action','İşlem'),'actions']];
    if (view==='chat') return [[tr('id','ID'),'id'],[tr('user','Kullanıcı'),'user'],[tr('streaming','Yayın'),'stream_title'],[tr('message','Mesaj'),'message'],[tr('date','Tarih'),'date'],[tr('action','İşlem'),'actions']];
    if (view==='reports') return [[tr('id','ID'),'id'],[tr('scope','Kapsam'),'scope'],[tr('reason','Neden'),'category'],[tr('reporter','Bildiren'),'reporter'],[tr('target','Hedef'),'report_target'],[tr('description','Açıklama'),'report_description'],[tr('status','Durum'),'status'],[tr('action','İşlem'),'actions']];
    if (view==='support' || view==='site_complaints') return [[tr('id','ID'),'id'],[tr('user','Kullanıcı'),'user'],[tr('type','Tür'),'type'],[tr('subject','Konu'),'subject'],[tr('message','Mesaj'),'support_message'],[tr('status','Durum'),'status'],[tr('action','İşlem'),'actions']];
    if (view==='payments' || view==='vip_payments') return [[tr('id','ID'),'id'],[tr('user','Kullanıcı'),'user'],[view==='payments'?tr('coin','Coin'):'VIP','coins'],[tr('amount','Tutar'),'amount'],[tr('status','Durum'),'status'],[tr('date','Tarih'),'date']];
    if (view==='payouts') return [[tr('id','ID'),'id'],[tr('user','Kullanıcı'),'user'],[tr('coin','Coin'),'coins'],[tr('amount','Tutar'),'amount'],['IBAN','iban'],[tr('status','Durum'),'status'],[tr('action','İşlem'),'actions']];
    if (view==='banks') return [[tr('id','ID'),'id'],[tr('user','Kullanıcı'),'user'],[tr('account_holder','Hesap sahibi'),'account_holder'],['IBAN','iban'],[tr('date','Tarih'),'date'],[tr('action','İşlem'),'actions']];
    if (view==='refunds') return [[tr('id','ID'),'id'],[tr('requester','Talep eden'),'requester'],[tr('recipient','Alıcı'),'recipient'],[tr('status','Durum'),'status'],[tr('date','Tarih'),'date'],[tr('action','İşlem'),'actions']];
    if (view==='categories') return [[tr('id','ID'),'id'],[tr('categories','Kategori'),'category'],['Slug','slug'],[tr('sort','Sıra'),'sort'],[tr('status','Durum'),'status'],[tr('action','İşlem'),'actions']];
    if (view==='settings') return [[tr('setting','Ayar'),'setting_key'],[tr('value','Değer'),'setting_value'],[tr('type','Tür'),'setting_type'],[tr('public','Herkese açık'),'is_public'],[tr('date','Tarih'),'date'],[tr('action','İşlem'),'actions']];
    return [[tr('id','ID'),'id'],['Admin','admin'],[tr('action','Aksiyon'),'audit_action'],[tr('target','Hedef'),'audit_target'],[tr('date','Tarih'),'date']];
  }

  function cell(r,key) {
    switch(key) {
      case 'id': return '#'+r.id;
      case 'user': return `<strong>${esc(r.display_name||r.username||'—')}</strong><small>@${esc(r.username||'')}</small>`;
      case 'mode': return esc(r.account_mode||'—') + (Number(r.is_admin)?' • ADMIN':'') + (view==='creators'?`<small>${LogiApp.formatNumber(r.stream_count||0)} yayın • ${LogiApp.formatNumber(r.lifetime_gifts||0)} coin</small>`:'');
      case 'money': return `${LogiApp.formatNumber(r.wallet_balance||0)} / ${LogiApp.formatNumber(r.creator_earnings||0)}`;
      case 'status': {
        let s = r.account_status || r.status;
        if (!s && r.is_live != null) s = Number(r.is_live)?'live':'offline';
        if (!s && r.is_active != null) s = Number(r.is_active)?'active':'disabled';
        return status(s||'—');
      }
      case 'actions':
        if (view==='users' || view==='creators') { const locked=['banned','blocked'].includes(String(r.account_status)); return `${locked?button('user_activate',r.id,'Aktifleştir'):button('user_block',r.id,'Engelle')}${locked?'':button('user_ban',r.id,tr('ban','Banla'),'danger')}${button('vip_grant',r.id,'VIP')}`; }
        if (view==='streams') return Number(r.is_live)?button('stream_stop',r.id,tr('stop_stream','Yayını Kapat'),'danger'):'—';
        if (view==='content') { const next=r.visibility==='private'?'public':'private'; return `${button('content_visibility',r.id,next==='private'?tr('hide','Gizle'):tr('publish','Yayınla'),'','data-kind="'+esc(r.kind)+'" data-visibility="'+next+'"')}${button('content_delete',r.id,tr('delete','Sil'),'danger','data-kind="'+esc(r.kind)+'"')}`; }
        if (view==='chat') return button('chat_delete',r.id,tr('delete','Sil'),'danger');
        if (view==='reports') return r.status==='resolved'?button('report_reopen',r.id,tr('reopen','Yeniden Aç')):button('report_resolve',r.id,tr('resolve','Çöz'));
        if (view==='support' || view==='site_complaints') return r.status==='closed'?'—':button('support_close',r.id,tr('close','Kapat'));
        if (view==='payouts') return r.status==='pending'?`${button('payout_approve',r.id,tr('approve','Onayla'))}${button('payout_reject',r.id,tr('reject','Reddet'),'danger')}`:'—';
        if (view==='banks') return button('bank_delete',r.id,tr('delete','Sil'),'danger');
        if (view==='refunds') return r.status==='pending'?`${button('refund_approve',r.id,tr('approve','Onayla'))}${button('refund_reject',r.id,tr('reject','Reddet'),'danger')}`:'—';
        if (view==='categories') return `${button('category_toggle',r.id,Number(r.is_active)?tr('disable','Pasifleştir'):tr('enable','Aktifleştir'))}${button('category_delete',r.id,tr('delete','Sil'),'danger')}`;
        if (view==='settings') return button('setting_update',0,tr('edit','Düzenle'),'','data-setting-key="'+esc(r.setting_key)+'" data-setting-value="'+esc(r.setting_value||'')+'" data-setting-type="'+esc(r.setting_type||'string')+'" data-setting-public="'+(Number(r.is_public)?'1':'0')+'"');
        return '—';
      case 'stream': return `<strong>${esc(r.title)}</strong><small>@${esc(r.username)} • ${esc(r.display_name)}</small>`;
      case 'stream_title': return `<strong>${esc(r.stream_title||'—')}</strong><small>#${Number(r.stream_id||0)}</small>`;
      case 'message': return `<span class="admin-message-cell">${esc(r.message||'—')}</span>`;
      case 'category': return esc(r.name||r.category||'—');
      case 'viewers': return LogiApp.formatNumber(r.viewers||0);
      case 'kind': return esc(String(r.kind||'').toUpperCase());
      case 'content': return `<strong>${esc(r.title)}</strong><small>#${r.id}</small>`;
      case 'username': return '@'+esc(r.username||'');
      case 'duration': return `${Number(r.duration_seconds||0)} ${tr('seconds_short','sn')}`;
      case 'visibility': return esc(r.visibility||'—');
      case 'date': return esc(LogiApp.timeAgo(r.created_at||r.updated_at));
      case 'scope': return esc(r.report_scope||'—');
      case 'reporter': return '@'+esc(r.reporter_username||'—');
      case 'report_target': return `<strong>@${esc(r.target_username||r.target_username_snapshot||'—')}</strong>${r.target_snapshot?`<small>${esc(r.target_snapshot)}</small>`:''}`;
      case 'report_description': return `<span class="admin-message-cell">${esc(r.description||'—')}</span>`;
      case 'type': return esc(r.ticket_type||r.type||'—');
      case 'subject': return esc(r.subject||'—');
      case 'support_message': return `<span class="admin-message-cell">${esc(r.message||'—')}</span>`;
      case 'coins': {
        if (view==='vip_payments') { try { const meta=JSON.parse(r.order_meta_json||'{}'); return esc(String(meta.membershipTier||'VIP').toUpperCase()); } catch(_) { return 'VIP'; } }
        return LogiApp.formatNumber(r.coin_amount||r.earning_coins||0);
      }
      case 'amount': return r.amount_kurus!=null?money(r.amount_kurus):`${esc(String(r.money_amount||'—'))} ${esc(r.currency||'')}`;
      case 'iban': return '•••• '+esc(r.iban_last4||'—');
      case 'account_holder': return esc(r.account_holder||'—');
      case 'requester': return '@'+esc(r.requester_username||'—');
      case 'recipient': return '@'+esc(r.recipient_username||'—');
      case 'slug': return esc(r.slug||'—');
      case 'sort': return esc(String(r.sort_order??'—'));
      case 'admin': return '@'+esc(r.admin_username||'—');
      case 'audit_action': return esc(r.action_name||r.action||'—');
      case 'audit_target': return `${esc(r.target_type||'—')} ${r.target_id?'#'+esc(String(r.target_id)):''}`;
      case 'setting_key': return `<strong>${esc(r.setting_key||'')}</strong>`;
      case 'setting_value': return `<code>${esc(r.setting_value||'')}</code>`;
      case 'setting_type': return esc(r.setting_type||'string');
      case 'is_public': return Number(r.is_public)?tr('yes','Evet'):tr('no','Hayır');
      default: return esc(String(r[key]??'—'));
    }
  }

  function render() {
    const sc=schema();
    document.getElementById('adminTableHead').innerHTML='<tr>'+sc.map(([l])=>`<th>${esc(l)}</th>`).join('')+'</tr>';
    document.getElementById('adminTableBody').innerHTML=rows.map(r=>'<tr>'+sc.map(([,k])=>`<td>${cell(r,k)}</td>`).join('')+'</tr>').join('');
    document.getElementById('adminRowCount').textContent=String(rows.length);
    document.getElementById('adminTableEmpty').classList.toggle('hidden',rows.length>0);
    document.getElementById('categoryCreateBtn').classList.toggle('hidden',view!=='categories');
    window.LogiI18n?.apply(document.getElementById('adminTableBody'));
  }

  async function load(next=view) {
    view=next;
    document.querySelectorAll('[data-admin-view]').forEach(b=>b.classList.toggle('active',b.dataset.adminView===view));
    document.getElementById('adminViewTitle').textContent=label(view);
    try {
      const q=document.getElementById('adminSearch').value.trim();
      const d=await LogiApp.api(`/admin/list.php?type=${encodeURIComponent(view)}&q=${encodeURIComponent(q)}`);
      rows=d.rows||[]; render();
    } catch(e) { rows=[];render();LogiApp.toast(e.message,'error'); }
  }

  document.getElementById('adminNav').addEventListener('click',e=>{const b=e.target.closest('[data-admin-view]');if(b)load(b.dataset.adminView);});
  document.body.addEventListener('click',e=>{const b=e.target.closest('[data-open-admin-view]');if(b)load(b.dataset.openAdminView);});
  document.getElementById('adminRefreshBtn').addEventListener('click',()=>Promise.all([overview(),load()]));
  let searchTimer; document.getElementById('adminSearch').addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>load(),280);});

  document.getElementById('adminTableBody').addEventListener('click',async e=>{
    const b=e.target.closest('[data-action]'); if(!b)return;
    let extra={}; const action=b.dataset.action;
    if(action==='vip_grant'){
      const tier=prompt('VIP seviyesi: plus / pro / premium','plus'); if(!tier)return;
      const days=Number(prompt('Kaç gün?','30')||30); extra={tier,days};
    }
    if(action==='content_visibility'||action==='content_delete') extra={kind:b.dataset.kind,visibility:b.dataset.visibility};
    if(action==='setting_update'){
      const value=prompt(`${b.dataset.settingKey} =`,b.dataset.settingValue||''); if(value===null)return;
      extra={key:b.dataset.settingKey,value,settingType:b.dataset.settingType||'string',isPublic:b.dataset.settingPublic==='1'};
    }
    if(['user_ban','user_block','stream_stop','payout_reject','category_delete','content_delete','chat_delete','bank_delete','refund_approve','refund_reject'].includes(action) && !confirm(tr('confirm_action','Bu işlemi uygulamak istediğine emin misin?'))) return;
    try {
      await LogiApp.api('/admin/action.php',{method:'POST',body:JSON.stringify({action,id:Number(b.dataset.id||0),...extra})});
      LogiApp.toast(tr('action_completed','İşlem tamamlandı.'),'success'); await Promise.all([overview(),load()]);
    } catch(err){LogiApp.toast(err.message,'error');}
  });

  document.getElementById('categoryCreateBtn').addEventListener('click',()=>LogiApp.openModal('adminCategoryModal'));
  document.getElementById('adminCategoryForm').addEventListener('submit',async e=>{
    e.preventDefault(); const form=e.currentTarget; const d=Object.fromEntries(new FormData(form));
    try { await LogiApp.api('/admin/action.php',{method:'POST',body:JSON.stringify({action:'category_create',name:d.name,icon:d.icon,sortOrder:Number(d.sortOrder||99)})}); LogiApp.closeModal('adminCategoryModal');form.reset();await load('categories'); }
    catch(err){LogiApp.toast(err.message,'error');}
  });
  document.addEventListener('logi:languagechange',()=>{document.getElementById('adminViewTitle').textContent=label(view);render();overview();});
  await Promise.all([overview(),load('users')]);
});
