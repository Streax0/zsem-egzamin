<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin(true);

$tools = [
    'logic' => ['title' => 'Bramki logiczne', 'icon' => 'bi-diagram-3', 'desc' => 'Buduj układy z wejść, bramek i LED, sprawdzaj połączenia oraz tabelę prawdy.'],
    'psu' => ['title' => 'Kalkulator PSU', 'icon' => 'bi-pc-display', 'desc' => 'Policz pobór zestawu PC, zapas mocy, obciążenie zasilacza i pobór z gniazdka.'],
    'subnet' => ['title' => 'Podsieci IP', 'icon' => 'bi-router', 'desc' => 'Wylicz sieć, broadcast, maskę, hosty i podstawowe parametry IPv4 oraz IPv6.'],
    'router' => ['title' => 'Laboratorium sieci', 'icon' => 'bi-hdd-network', 'desc' => 'Buduj topologie, przesuwaj urządzenia, łącz porty i ćwicz CLI Cisco, MikroTik oraz TP-Link.'],
    'numbers' => ['title' => 'Systemy liczbowe', 'icon' => 'bi-123', 'desc' => 'Konwertuj BIN/OCT/DEC/HEX, sprawdzaj zapis 8-bitowy, U2 i operacje bitowe.'],
    'ohm' => ['title' => 'Prawo Ohma', 'icon' => 'bi-lightning-charge', 'desc' => 'Licz napięcie, prąd, opór, moc oraz najbliższy rezystor do zasilania LED.'],
    'live' => ['title' => 'Live HTML/CSS/JS', 'icon' => 'bi-code-slash', 'desc' => 'Testuj HTML, CSS i JavaScript w izolowanym podglądzie z zapisem szkicu po odświeżeniu.'],
];
$tool = $_GET['tool'] ?? 'home';
if ($tool !== 'home' && !isset($tools[$tool])) $tool = 'home';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sandbox – ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/dashboard-new.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/sandbox.css')); ?>">
</head>
<body>
<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include 'includes/topbar.php'; ?>
        <main role="main" class="content-body">
            <div class="container-fluid p-0 sandbox-shell">
                <header class="sandbox-hero mb-4">
                    <div>
                        <h2 class="fw-900 mb-2">Sandbox ZSEM Tech</h2>
                        <p class="mb-0">Narzędzia do szybkiego ćwiczenia sprzętu, sieci, logiki cyfrowej, kodu i systemów liczbowych.</p>
                    </div>
                    <?php if ($tool !== 'home'): ?>
                        <a href="sandbox.php" class="btn btn-light rounded-pill px-4"><i class="bi bi-grid me-1"></i>Wszystkie narzędzia</a>
                    <?php endif; ?>
                </header>

                <?php if ($tool === 'home'): ?>
                    <section class="sandbox-tool-grid">
                        <?php foreach ($tools as $key => $meta): ?>
                            <a class="sandbox-tool-tile" href="sandbox.php?tool=<?= htmlspecialchars($key) ?>">
                                <span class="sandbox-tool-icon"><i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i></span>
                                <strong><?= htmlspecialchars($meta['title']) ?></strong>
                                <span><?= htmlspecialchars($meta['desc']) ?></span>
                                <span class="sandbox-tool-chip">Uruchom narzędzie</span>
                                <i class="bi bi-arrow-right-short sandbox-arrow"></i>
                            </a>
                        <?php endforeach; ?>
                    </section>
                <?php else: ?>
                    <nav class="sandbox-tabs mb-4" aria-label="Narzędzia sandbox">
                        <?php foreach ($tools as $key => $meta): ?>
                            <a href="sandbox.php?tool=<?= htmlspecialchars($key) ?>" class="<?= $tool === $key ? 'active' : '' ?>">
                                <i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i><?= htmlspecialchars($meta['title']) ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>

                <?php if ($tool === 'logic'): ?>
                    <section class="sandbox-workbench logic-workbench" data-tool="logic">
                        <aside class="sandbox-rail">
                            <h5 class="fw-800 mb-3">Komponenty</h5>
                            <div class="toolbox-group">
                                <span>Wejścia</span>
                                <button type="button" data-logic-input="A" draggable="true"><i class="bi bi-toggle-on"></i>Przełącznik A</button>
                                <button type="button" data-logic-input="B" draggable="true"><i class="bi bi-toggle-on"></i>Przełącznik B</button>
                                <button type="button" data-logic-const="1" draggable="true"><i class="bi bi-1-circle"></i>Stała 1</button>
                                <button type="button" data-logic-const="0" draggable="true"><i class="bi bi-0-circle"></i>Stała 0</button>
                            </div>
                            <div class="toolbox-group">
                                <span>Bramki</span>
                                <?php foreach (['BUFFER','NOT','AND','NAND','OR','NOR','XOR','XNOR'] as $gate): ?>
                                    <button type="button" data-gate="<?= $gate ?>" draggable="true"><i class="bi bi-cpu"></i><?= $gate ?></button>
                                <?php endforeach; ?>
                            </div>
                            <div class="toolbox-group">
                                <span>Wyjścia</span>
                                <button type="button" data-output="LED" draggable="true"><i class="bi bi-lightbulb"></i>LED</button>
                                <button type="button" data-output="TABLE" draggable="true"><i class="bi bi-table"></i>Tabela prawdy</button>
                            </div>
                        </aside>
                        <div class="logic-canvas-panel">
                            <div class="logic-toolbar">
                                <button id="logicDemo" type="button" class="btn btn-sm btn-primary"><i class="bi bi-magic me-1"></i>Demo</button>
                                <button id="logicReset" type="button" class="btn btn-sm btn-light border"><i class="bi bi-arrow-counterclockwise me-1"></i>Wyczyść</button>
                                <button id="logicExportPdf" type="button" class="btn btn-sm btn-outline-primary"><i class="bi bi-filetype-pdf me-1"></i>PDF</button>
                                <span id="logicHint" class="small text-muted">Kliknij wyjście, potem wejście. Węzły można przeciągać.</span>
                            </div>
                            <div class="logic-canvas" id="logicBoard">
                                <svg id="logicWireLayer" class="logic-wire-layer" aria-hidden="true"></svg>
                            </div>
                            <div class="truth-table-wrap">
                                <table class="table table-sm mb-0" id="truthTable"></table>
                            </div>
                        </div>
                    </section>
                <?php elseif ($tool === 'psu'): ?>
                    <section class="sandbox-workbench psu-workbench" data-tool="psu">
                        <div class="sandbox-panel">
                            <h5 class="fw-800 mb-3">Konfiguracja komputera</h5>
                            <div class="component-grid">
                                <label>CPU TDP (W)<input id="psuCpuTdp" class="form-control" type="number" value="65" min="0" max="350"></label>
                                <label>GPU TBP (W)<input id="psuGpuTbp" class="form-control" type="number" value="180" min="0" max="700"></label>
                                <label>Płyta + RAM (W)<input id="psuBoard" class="form-control" type="number" value="65" min="20" max="220"></label>
                                <label>Dyski SSD/HDD (szt.)<input id="psuDriveCount" class="form-control" type="number" value="2" min="0" max="12"></label>
                                <label>Wentylatory (szt.)<input id="psuFanCount" class="form-control" type="number" value="4" min="0" max="16"></label>
                                <label>USB/RGB/inne (W)<input id="psuExtra" class="form-control" type="number" value="20" min="0" max="180"></label>
                                <label>Zapas mocy (%)<input id="psuHeadroom" class="form-control" type="number" value="30" min="10" max="80"></label>
                                <label>Certyfikat<select id="psuEfficiency" class="form-select"><option value="80">80 PLUS</option><option value="85" selected>Bronze/Gold</option><option value="90">Gold/Platinum</option></select></label>
                            </div>
                        </div>
                        <aside class="sandbox-result-panel">
                            <span>Rekomendowany zasilacz</span>
                            <strong id="psuRecommended">0 W</strong>
                            <p id="psuDetails" class="mb-0"></p>
                        </aside>
                    </section>
                <?php elseif ($tool === 'subnet'): ?>
                    <section class="sandbox-workbench subnet-workbench" data-tool="subnet">
                        <div class="sandbox-panel subnet-explain-panel">
                            <h5 class="fw-800 mb-3"><i class="bi bi-question-circle me-2 text-primary"></i>Jak to liczyć</h5>
                            <ol class="small text-muted mb-0">
                                <li>Wpisz adres i prefiks CIDR, np. <strong>/24</strong>.</li>
                                <li>Prefiks zamienia się na maskę, która odcina część sieciową od hostów.</li>
                                <li>Adres sieci to IP po operacji AND z maską; broadcast to ostatni adres podsieci.</li>
                                <li>Zakres hostów jest między adresem sieci i broadcastem; dla IPv6 pokazujemy prefiks i wielkość puli.</li>
                            </ol>
                        </div>
                        <div class="sandbox-panel">
                            <h5 class="fw-800 mb-3">IPv4</h5>
                            <div class="row g-3">
                                <div class="col-md-8"><input id="ipv4Input" class="form-control" value="192.168.10.34"></div>
                                <div class="col-md-4"><input id="ipv4Cidr" class="form-control" type="number" min="1" max="32" value="24"></div>
                            </div>
                            <div id="ipv4Out" class="result-grid mt-3"></div>
                        </div>
                        <div class="sandbox-panel">
                            <h5 class="fw-800 mb-3">IPv6</h5>
                            <div class="row g-3">
                                <div class="col-md-8"><input id="ipv6Input" class="form-control" value="2001:db8:abcd:0012::1"></div>
                                <div class="col-md-4"><input id="ipv6Prefix" class="form-control" type="number" min="1" max="128" value="64"></div>
                            </div>
                            <div id="ipv6Out" class="result-grid mt-3"></div>
                        </div>
                    </section>
                <?php elseif ($tool === 'router'): ?>
                    <section class="router-web-emulator" data-tool="router">
                        <aside class="router-web-nav" aria-label="Menu routera">
                            <div class="router-web-brand">
                                <strong>ZSEM RouterOS</strong>
                                <span>AC750 Wireless Dual Band Router</span>
                            </div>
                            <a href="#router-wan" class="active">WAN</a>
                            <a href="#router-lan">LAN</a>
                            <a href="#router-dhcp">DHCP</a>
                            <a href="#router-wireless">Wireless</a>
                            <a href="#router-security">Security</a>
                            <a href="#router-system">System Tools</a>
                        </aside>
                        <div class="router-web-main">
                            <header class="router-web-top">
                                <div>
                                    <h3 class="fw-900 mb-1">ZSEM Tech Router Configuration</h3>
                                    <p class="mb-0">Ćwicz konfigurację WAN, LAN, DHCP i Wi-Fi w bezpiecznym emulatorze panelu routera.</p>
                                </div>
                                <span id="routerConfigStatus" class="router-web-status">Niezapisane</span>
                            </header>

                            <div class="router-web-grid">
                                <section class="router-config-card" id="router-wan">
                                    <h5>WAN</h5>
                                    <label>Connection Type
                                        <select id="routerWanType" class="form-select">
                                            <option>Dynamic IP</option>
                                            <option>Static IP</option>
                                            <option>PPPoE</option>
                                        </select>
                                    </label>
                                    <label>WAN IP Address<input id="routerWanIp" class="form-control" value="10.0.0.2"></label>
                                    <label>Default Gateway<input id="routerGateway" class="form-control" value="10.0.0.1"></label>
                                    <label>MAC Clone<input id="routerWanMac" class="form-control" value="00:AB:E1:37:B8:00"></label>
                                    <button type="button" id="routerCloneMac" class="btn btn-sm btn-outline-primary">Clone PC MAC Address</button>
                                </section>

                                <section class="router-config-card" id="router-lan">
                                    <h5>LAN</h5>
                                    <label>LAN IP Address<input id="routerLanIp" class="form-control" value="192.168.0.1"></label>
                                    <label>Subnet Mask<input id="routerLanMask" class="form-control" value="255.255.255.0"></label>
                                    <label>DNS Server<input id="routerDns" class="form-control" value="1.1.1.1"></label>
                                </section>

                                <section class="router-config-card" id="router-dhcp">
                                    <h5>DHCP</h5>
                                    <label class="router-toggle-row"><input id="routerDhcpToggle" type="checkbox" checked> DHCP Server Enabled</label>
                                    <label>Start IP<input id="routerDhcpStart" class="form-control" value="192.168.0.100"></label>
                                    <label>End IP<input id="routerDhcpEnd" class="form-control" value="192.168.0.199"></label>
                                    <label>Lease Time<input id="routerLease" class="form-control" value="120 min"></label>
                                </section>

                                <section class="router-config-card" id="router-wireless">
                                    <h5>Wireless</h5>
                                    <label>SSID<input id="routerSsid" class="form-control" value="ZSEM-Tech-Lab"></label>
                                    <label>Security
                                        <select id="routerWifiSecurity" class="form-select">
                                            <option>WPA2-PSK AES</option>
                                            <option>WPA3-SAE</option>
                                            <option>Disabled</option>
                                        </select>
                                    </label>
                                    <label>Channel<input id="routerChannel" class="form-control" value="6"></label>
                                </section>
                            </div>

                            <footer class="router-web-footer">
                                <div id="routerSummary" class="router-summary"></div>
                                <div class="d-flex gap-2">
                                    <button type="button" id="routerResetConfig" class="btn btn-light border">Restore Factory MAC</button>
                                    <button type="button" id="routerSaveConfig" class="btn btn-primary">Save</button>
                                </div>
                            </footer>
                        </div>
                    </section>
                <?php elseif ($tool === 'numbers'): ?>
                    <section class="sandbox-workbench numbers-workbench" data-tool="numbers">
                        <div class="sandbox-panel">
                            <h5 class="fw-800 mb-3">Konwerter</h5>
                            <div class="row g-3">
                                <div class="col-md-8"><input id="numInput" class="form-control" value="255"></div>
                                <div class="col-md-4"><select id="numBase" class="form-select"><option value="10">DEC</option><option value="2">BIN</option><option value="8">OCT</option><option value="16">HEX</option></select></div>
                            </div>
                            <div id="numOut" class="result-grid mt-3"></div>
                        </div>
                        <div class="sandbox-panel">
                            <h5 class="fw-800 mb-3">Operacje bitowe</h5>
                            <div class="row g-3">
                                <div class="col-md-5"><input id="bitA" class="form-control" value="170"></div>
                                <div class="col-md-2"><select id="bitOp" class="form-select"><option>AND</option><option>OR</option><option>XOR</option><option>SHL</option><option>SHR</option></select></div>
                                <div class="col-md-5"><input id="bitB" class="form-control" value="85"></div>
                            </div>
                            <div id="bitOut" class="result-grid mt-3"></div>
                        </div>
                    </section>
                <?php elseif ($tool === 'ohm'): ?>
                    <section class="sandbox-workbench numbers-workbench" data-tool="ohm">
                        <div class="sandbox-panel">
                            <h5 class="fw-800 mb-3">Prawo Ohma</h5>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label">Napięcie V<input id="ohmVoltage" class="form-control" type="number" step="0.01" value="5"></label></div>
                                <div class="col-md-4"><label class="form-label">Prąd A<input id="ohmCurrent" class="form-control" type="number" step="0.001" value="0.02"></label></div>
                                <div class="col-md-4"><label class="form-label">Opór Ω<input id="ohmResistance" class="form-control" type="number" step="0.1" value=""></label></div>
                            </div>
                            <div id="ohmOut" class="result-grid mt-3"></div>
                        </div>
                        <div class="sandbox-panel">
                            <h5 class="fw-800 mb-3">Rezystor LED</h5>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label">Zasilanie V<input id="ledSupply" class="form-control" type="number" step="0.1" value="5"></label></div>
                                <div class="col-md-4"><label class="form-label">Spadek LED V<input id="ledForward" class="form-control" type="number" step="0.1" value="2"></label></div>
                                <div class="col-md-4"><label class="form-label">Prąd mA<input id="ledCurrent" class="form-control" type="number" step="1" value="20"></label></div>
                            </div>
                            <div id="ledOut" class="result-grid mt-3"></div>
                        </div>
                    </section>
                <?php elseif ($tool === 'live'): ?>
                    <section class="sandbox-workbench live-workbench" data-tool="live">
                        <div class="sandbox-panel">
                            <h5 class="fw-800 mb-3">Edytor</h5>
                            <div class="code-editors">
                                <label>HTML<textarea id="htmlCode" class="form-control"><h1>ZSEM Tech</h1>
<button id="btn">Kliknij</button></textarea></label>
                                <label>CSS<textarea id="cssCode" class="form-control">body { font-family: Inter, sans-serif; padding: 24px; }
button { padding: 10px 16px; border-radius: 8px; }</textarea></label>
                                <label>JS<textarea id="jsCode" class="form-control">document.getElementById('btn').onclick = () => {
  document.getElementById('out').textContent = 'Działa';
};</textarea></label>
                            </div>
                            <div id="codeWarning" class="alert alert-warning d-none mt-3 mb-0"></div>
                            <div class="d-flex gap-2 mt-3">
                                <button id="runCode" class="btn btn-primary rounded-pill" type="button"><i class="bi bi-play-fill me-1"></i>Uruchom</button>
                                <button id="liveDemo" class="btn btn-outline-primary rounded-pill" type="button"><i class="bi bi-stars me-1"></i>Demo</button>
                                <button id="clearCode" class="btn btn-light border rounded-pill" type="button"><i class="bi bi-eraser me-1"></i>Wyczyść</button>
                            </div>
                        </div>
                        <iframe id="codePreview" class="preview-frame" sandbox="allow-scripts allow-forms allow-modals allow-popups"></iframe>
                    </section>
                <?php endif; ?>
            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
<script src="<?php echo htmlspecialchars(assetUrl('assets/js/sandbox.js')); ?>"></script>
</body>
</html>
