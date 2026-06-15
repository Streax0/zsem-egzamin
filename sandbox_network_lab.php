<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin(true);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratorium sieci INF.02 - ZSEM Tech</title>
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/network-lab.css')); ?>">
</head>
<body>
<div class="layout">

  <!-- ════ TASK PANEL ════ -->
  <div class="task-panel">
    <div class="task-hdr">📋 Arkusz egzaminacyjny INF.02</div>
    <div class="pdf-ctrl">
      <label>Sesja:</label>
      <select id="exam-sel">
        <option value="2025-cze">2025 – sesja letnia</option>
        <option value="2024-cze">2024 – sesja letnia</option>
        <option value="2024-sty">2024 – sesja zimowa</option>
        <option value="2023-cze">2023 – sesja letnia</option>
        <option value="2023-sty">2023 – sesja zimowa</option>
        <option value="2022-cze">2022 – sesja letnia</option>
        <option value="2022-sty">2022 – sesja zimowa</option>
        <option value="2021-cze">2021 – sesja letnia</option>
        <option value="cke">Przykładowy (CKE)</option>
      </select>
      <a id="pdf-ext" href="#" target="_blank" rel="noopener noreferrer" class="pdf-ext-btn" title="Otwórz PDF w nowej karcie">↗</a>
    </div>
    <div class="pdf-wrap">
      <div class="pdf-loading" id="pdf-loading">
        <div class="pdf-spinner"></div>
        <p>Ładowanie arkusza PDF…<br><span style="font-size:10px;color:#444">Jeśli PDF nie pojawi się, kliknij przycisk ↗</span></p>
        <a id="pdf-fallback-btn" href="#" target="_blank" rel="noopener noreferrer">Otwórz PDF w nowej karcie</a>
      </div>
      <iframe id="pdf-frame" class="pdf-frame" title="Arkusz egzaminacyjny INF.02" src="" allowfullscreen
        ></iframe>
    </div>
  </div>

  <div class="splitter" id="splitter"></div>

  <!-- ════ EMULATOR AREA ════ -->
  <div class="emu-area">
    <div class="dev-tabs">
      <button class="dev-tab active" id="tab-router">🖥 Router</button>
      <button class="dev-tab" id="tab-switch">⇄ Switch (SG108E)</button>
      <div id="router-model-wrap">
        <label>Model:</label>
        <select id="router-model-sel">
          <option value="cisco">Cisco RV132W</option>
          <option value="tplink">TP-Link TL-WR841ND</option>
          <option value="mikrotik-wb">MikroTik (WinBox)</option>
          <option value="mikrotik-wf">MikroTik (WebFig)</option>
        </select>
      </div>
    </div>

    <!-- ROUTER -->
    <div class="cisco-wrap" id="wrap-router">
      <div class="cisco-header">
        <div class="cisco-logo-cell"><span class="device-logo-text">Cisco</span></div>
        <div class="cisco-prodname">
          <div class="cisco-appname">RV132W</div>
          <div class="cisco-prodmodel">Wireless-N VPN Firewall</div>
        </div>
        <div class="cisco-header-right">
          <span>admin | RV132W</span>
          <a>Log Out</a><a>Help</a><a>About</a>
        </div>
      </div>
      <div class="cisco-body">
        <nav class="cisco-nav" id="cnav-router"></nav>
        <div class="cisco-content" id="ccontent-router"></div>
      </div>
    </div>

    <!-- TP-LINK ROUTER -->
    <div class="tplink-wrap" id="wrap-tplink" style="display:none">
      <div class="tplink-hdr">
        <div class="tplink-logo-area">
          <span class="tplink-logo">TP-LINK<sup>®</sup></span>
        </div>
        <div class="tplink-hdr-right">
          <div class="tplink-prodmodel">300M Wireless N Router</div>
          <div class="tplink-prodtype">Model No. TL-WR841N / TL-WR841ND</div>
        </div>
      </div>
      <div class="tplink-body">
        <nav class="tplink-nav" id="tpnav"></nav>
        <div class="tplink-content" id="tpcontent"></div>
        <div class="tplink-help" id="tphelp"></div>
      </div>
    </div>

    <!-- MIKROTIK WINBOX -->
    <div class="mt-wb-wrap" id="wrap-mikrotik-wb" style="display:none">
      <div class="mt-wb-toolbar">
        <button class="mt-wb-navbtn">◄</button>
        <button class="mt-wb-navbtn">►</button>
        <div class="mt-wb-sep"></div>
        <button class="mt-wb-safebtn">Safe Mode</button>
        <span class="mt-wb-session-lbl">Session:</span>
        <input class="mt-wb-session-inp" value="08:00:27:FB:A2:59" readonly>
      </div>
      <div class="mt-wb-body">
        <div class="mt-wb-brand">RouterOS WinBox</div>
        <nav class="mt-wb-nav" id="mt-wb-nav"></nav>
        <div class="mt-wb-content" id="mt-wb-content"></div>
      </div>
    </div>

    <!-- MIKROTIK WEBFIG -->
    <div class="mt-wf-wrap" id="wrap-mikrotik-wf" style="display:none">
      <div class="mt-wf-header">
        <div class="mt-wf-logo"><span class="mt-wf-tri">▲</span><span class="mt-wf-brand">MikroTik</span></div>
        <div class="mt-wf-hinfo"><span id="mt-wf-identity">MikroTik</span><span class="mt-wf-ver">RouterOS v7.15</span></div>
        <div class="mt-wf-hright">admin &nbsp;|&nbsp; <a class="mt-wf-logout">Log out</a></div>
      </div>
      <div class="mt-wf-body">
        <nav class="mt-wf-nav" id="mt-wf-nav"></nav>
        <div class="mt-wf-content" id="mt-wf-content"></div>
      </div>
    </div>

    <!-- SWITCH -->
    <div class="cisco-wrap" id="wrap-switch" style="display:none">
      <div class="cisco-header">
        <div class="cisco-logo-cell"><span class="device-logo-text">TP-Link</span></div>
        <div class="cisco-prodname">
          <div class="cisco-appname">TL-SG108E</div>
          <div class="cisco-prodmodel">8-Port Gigabit Easy Smart Switch</div>
        </div>
        <div class="cisco-header-right">
          <span>admin</span>
          <a>Log Out</a><a>Help</a><a>About</a>
        </div>
      </div>
      <div class="cisco-body">
        <nav class="cisco-nav" id="cnav-switch"></nav>
        <div class="cisco-content" id="ccontent-switch"></div>
      </div>
    </div>

    <div class="verify-bar">
      <button class="btn-reset" id="btn-reset" title="Zresetuj konfigurację do stanu fabrycznego">↺ Resetuj urządzenia</button>
      <span style="flex:1"></span>
      <button class="btn-verify" id="btn-verify">✓ Sprawdź konfigurację</button>
    </div>
  </div>
</div>

<div class="modal-ov" id="modal">
  <div class="modal-box">
    <div class="modal-title-bar">
      <span>Wyniki weryfikacji — INF.02</span>
      <button class="cbtn" style="margin:0;padding:2px 10px" id="modal-close-x">✕</button>
    </div>
    <div class="modal-body" id="modal-body"></div>
  </div>
</div>
<script src="<?php echo htmlspecialchars(assetUrl('assets/js/network-lab.js')); ?>"></script>
</body>
</html>
