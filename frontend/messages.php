<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/bootstrap.php';
$user=current_user();if(!$user){header('Location: ./login.php?next=messages.php');exit;}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="tr" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>BUFF • Mesajlar</title>
<link rel="icon" href="./assets/img/buff-logo.png">
<link rel="stylesheet" href="./assets/css/style.css?v=18.0.0">
<link rel="stylesheet" href="./assets/css/v12.css?v=18.0.0">
<link rel="stylesheet" href="./assets/css/v14.css?v=18.0.0">
<link rel="stylesheet" href="./assets/css/motion-v15.css?v=18.0.0">
<link rel="stylesheet" href="./assets/css/v16-social.css?v=18.0.0">
<link rel="stylesheet" href="./assets/css/v17.css?v=18.0.0"><script src="./assets/js/v17.js?v=18.0.0" defer></script>
<link rel="stylesheet" href="./assets/css/v18.css?v=18.0.0">
</head>
<body class="app-body messages-body" data-page="messages">
<header class="app-header kick-header-v9">
  <button class="hamburger-btn" id="sidebarCollapseBtn" type="button">☰</button>
  <a class="logo-lockup" href="./index.php"><img src="./assets/img/buff-logo.png" alt="BUFF"><span>BUFF</span></a>
  <nav class="header-nav"></nav>
  <div class="header-search v9-search"><span>⌕</span><input id="messageGlobalSearch" type="search" placeholder="Kullanıcı ID, kullanıcı adı veya isim ara"><kbd>Ctrl K</kbd></div>
  <div id="authActions" class="header-actions"></div>
</header>

<main class="messenger-shell">
  <aside class="messenger-rooms surface-card">
    <div class="messenger-panel-head">
      <div><span class="section-kicker">SOSYAL</span><h1>Mesajlar</h1></div>
      <button class="btn btn-primary btn-small" id="newGroupBtn" type="button">+ Grup</button>
    </div>
    <div class="id-search-box">
      <label for="peopleSearch">Kullanıcı bul</label>
      <div class="id-search-input"><span>#</span><input id="peopleSearch" type="search" placeholder="BF00000001 veya @kullanıcı"></div>
      <div id="peopleResults" class="people-results hidden"></div>
    </div>
    <div class="room-tabs"><button class="active" data-room-tab="all">Tümü</button><button data-room-tab="direct">Birebir</button><button data-room-tab="group">Gruplar</button></div>
    <div id="roomList" class="room-list"><div class="menu-empty">Sohbetler yükleniyor…</div></div>
  </aside>

  <section class="messenger-chat surface-card">
    <div id="chatEmpty" class="messenger-empty-state">
      <div class="messenger-empty-icon">💬</div>
      <h2>BUFF Mesajlar</h2>
      <p>Kullanıcı ID'siyle arkadaşını bul, karşılıklı takip et ve birebir ya da grup sohbetine başla.</p>
      <small>Fotoğraf, sesli mesaj, sesli arama ve coin hediyesi desteklenir.</small>
    </div>
    <div id="chatActive" class="messenger-active hidden">
      <header class="chat-room-head">
        <div class="chat-room-ident"><span id="roomAvatar" class="avatar-circle">B</span><div><strong id="roomTitle">Sohbet</strong><small id="roomMeta">—</small></div></div>
        <div class="chat-room-actions">
          <button id="voiceCallBtn" class="round-control" type="button" title="Sesli ara">☎</button>
          <button id="roomInfoBtn" class="round-control" type="button" title="Oda bilgisi">ⓘ</button>
        </div>
      </header>
      <div id="messageList" class="dm-message-list"></div>
      <form id="messageComposer" class="dm-composer">
        <input type="file" id="messageImageInput" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
        <button type="button" class="dm-tool" id="imageMessageBtn" title="Fotoğraf gönder">▧</button>
        <button type="button" class="dm-tool" id="voiceMessageBtn" title="Sesli mesaj kaydet">◉</button>
        <button type="button" class="dm-tool coin-tool" id="coinGiftBtn" title="Coin gönder">◈</button>
        <textarea id="messageText" rows="1" maxlength="2000" placeholder="Mesaj yaz…"></textarea>
        <button class="dm-send" type="submit">Gönder</button>
      </form>
      <div id="voiceRecordState" class="voice-record-state hidden"><i></i><span>Ses kaydediliyor…</span><strong id="voiceRecordTimer">0:00</strong><button id="cancelVoiceBtn" type="button">İptal</button><button id="sendVoiceBtn" type="button">Gönder</button></div>
    </div>
  </section>

  <aside id="roomSidebar" class="messenger-info surface-card">
    <div class="messenger-info-placeholder"><strong>Oda bilgisi</strong><p>Bir sohbet seçtiğinde üyeler ve güvenli sohbet seçenekleri burada görünür.</p></div>
  </aside>
</main>

<div class="app-modal" id="groupModal"><div class="modal-card v16-modal"><div class="modal-head"><div><span class="section-kicker">YENİ GRUP</span><h3>Grup oluştur</h3></div><button data-close-modal="groupModal" type="button">×</button></div>
<form id="groupForm" class="pro-form"><label><span>Grup adı</span><input name="title" maxlength="120" placeholder="Oyun ekibi"></label><div><span class="form-label">Karşılıklı takip ettiklerin</span><div id="groupContactList" class="group-contact-list"></div></div><small>Sen dahil en az 3 kişi gerekir. Gruba yalnızca karşılıklı takip ettiğin kullanıcılar eklenebilir.</small><button class="btn btn-primary" type="submit">Grubu Oluştur</button><div id="groupMessage" class="form-message"></div></form></div></div>

<div class="app-modal" id="coinModal"><div class="modal-card v16-modal compact"><div class="modal-head"><div><span class="section-kicker">COIN HEDİYESİ</span><h3>Arkadaşına coin gönder</h3></div><button data-close-modal="coinModal" type="button">×</button></div>
<form id="coinForm" class="pro-form"><label><span>Coin miktarı</span><input name="amount" type="number" min="1" max="1000000" value="50"></label><label><span>Not</span><input name="note" maxlength="160" placeholder="İyi yayınlar!"></label><button class="btn btn-primary" type="submit">Coin Gönder</button><div id="coinMessage" class="form-message"></div></form></div></div>

<div class="call-overlay hidden" id="callOverlay"><div class="call-card"><div id="callPulse" class="call-avatar">B</div><span id="callState">Aranıyor…</span><h3 id="callName">BUFF Kullanıcısı</h3><div class="call-actions"><button id="acceptCallBtn" class="call-accept hidden" type="button">✓ Kabul</button><button id="hangupCallBtn" class="call-hangup" type="button">✕ Kapat</button></div><audio id="remoteCallAudio" autoplay></audio></div></div>

<script src="./assets/js/i18n.js?v=18.0.0"></script>
<script src="./assets/js/common.js?v=18.0.0"></script>
<script src="./assets/js/v12.js?v=18.0.0"></script>
<script src="./assets/js/v14.js?v=18.0.0"></script>
<script src="./assets/js/motion-v15.js?v=18.0.0"></script>
<script src="./assets/js/messages.js?v=18.0.0"></script>
</body></html>