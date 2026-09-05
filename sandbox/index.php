<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

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
    'crypto' => ['title' => 'Krypto i Hasła', 'icon' => 'bi-shield-lock', 'desc' => 'Generuj silne hasła, koduj/dekoduj tekst w Base64 oraz przeliczaj encje URL.'],
];
$tool = $_GET['tool'] ?? 'home';
// Legacy tool route compatibility: sandbox.php?tool=
if ($tool !== 'home' && !isset($tools[$tool])) $tool = 'home';

$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';

$sandboxRole = function_exists('isGuestMode') && isGuestMode() ? 'guest' : (string)($_SESSION['role'] ?? 'user');
$sandboxElementAdminNotice = null;
$sandboxElementBlocksForRole = [];
$sandboxBlockedElements = [];
if (isset($pdo) && function_exists('getSandboxElementBlockMapForRole')) {
    $sandboxElementBlocksForRole = getSandboxElementBlockMapForRole($pdo, $sandboxRole);
    if (function_exists('roleHasAdminAccess') && roleHasAdminAccess($sandboxRole)) {
        $sandboxElementAdminNotice = !empty($sandboxElementBlocksForRole) ? reset($sandboxElementBlocksForRole) : null;
        if (is_array($sandboxElementAdminNotice)) {
            $_SESSION['sandbox_element_block_notice'] = $sandboxElementAdminNotice;
        }
    } else {
        $sandboxBlockedElements = $sandboxElementBlocksForRole;
    }
}
$sandboxToolBlock = $tool !== 'home' ? ($sandboxBlockedElements['tool.' . $tool] ?? null) : null;
$sandboxElementBlock = static fn(string $key): ?array => $sandboxBlockedElements[$key] ?? null;
$sandboxEsc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$sandboxBlockRoleText = static function (array $block): string {
    $roles = array_filter($block['target_role_labels'] ?? []);
    return !empty($roles) ? implode(', ', $roles) : 'Wybrane role';
};
$sandboxBlockTooltip = static function (array $block, string $fallbackTitle) use ($sandboxBlockRoleText): string {
    $parts = [
        (string)($block['title'] ?? $fallbackTitle),
        (string)($block['body'] ?? ''),
        'Wyłączył: ' . (string)($block['moderator_label'] ?? 'Administrator'),
        'Data: ' . (string)($block['disabled_date'] ?? date('d.m.Y H:i')),
        'Role: ' . $sandboxBlockRoleText($block),
    ];
    return implode(' | ', array_filter($parts, static fn($part) => trim($part) !== ''));
};
$sandboxBlockMetaList = static function (array $block) use ($sandboxEsc, $sandboxBlockRoleText): string {
    return '<dl class="sandbox-tool-disabled-meta">'
        . '<div><dt>Wyłączył</dt><dd>' . $sandboxEsc($block['moderator_label'] ?? 'Administrator') . '</dd></div>'
        . '<div><dt>Data</dt><dd>' . $sandboxEsc($block['disabled_date'] ?? date('d.m.Y H:i')) . '</dd></div>'
        . '<div><dt>Role</dt><dd>' . $sandboxEsc($sandboxBlockRoleText($block)) . '</dd></div>'
        . '</dl>';
};
$sandboxRenderLogicButton = static function (string $elementKey, string $label, string $icon, array $dataAttrs) use ($sandboxElementBlock, $sandboxBlockTooltip): void {
    $block = $sandboxElementBlock($elementKey);
    $esc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $attrs = ' data-sandbox-element-key="' . $esc($elementKey) . '"';
    foreach ($dataAttrs as $name => $value) {
        $attrs .= ' ' . $name . '="' . $esc($value) . '"';
    }
    if ($block) {
        $attrs .= ' disabled data-sandbox-element-blocked="1" title="' . $esc($sandboxBlockTooltip($block, 'Element wyłączony')) . '"';
        echo '<button type="button"' . $attrs . '><i class="bi bi-lock"></i>' . $esc($label) . '</button>';
        return;
    }
    echo '<button type="button"' . $attrs . ' draggable="true"><i class="bi ' . $esc($icon) . '"></i>' . $esc($label) . '</button>';
};
?>
<?php
$pageTitle = 'Sandbox – ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css', 'assets/css/sandbox.css'];
$bodyClasses = ['sandbox-mode'];
if ($embedMode) {
    $bodyClasses[] = 'embed-mode';
}
include '../includes/header.php';
?>
<?php if ($embedMode): ?>
<style>
    .sidebar, .topbar, .sandbox-hero, .sandbox-tabs, footer, .footer-bottom, .footer-section, #chat-widget-container {
        display: none !important;
    }
    .main-container {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    .content-body {
        padding: 0 !important;
    }
    body {
        background: transparent !important;
    }
    .sandbox-shell {
        margin-top: 0 !important;
        padding: 0 !important;
    }
</style>
<?php endif; ?>
<div class="dashboard-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include '../includes/topbar.php'; ?>
        <main role="main" class="content-body">
            <div class="container-fluid p-0 sandbox-shell">
                <header class="sandbox-hero mb-4">
                    <div>
                        <h1 class="h2 fw-900 mb-2">Sandbox ZSEM Tech</h1>
                        <p class="mb-0">Narzędzia do szybkiego ćwiczenia sprzętu, sieci, logiki cyfrowej, kodu i systemów liczbowych.</p>
                    </div>
                    <?php if ($tool !== 'home'): ?>
                        <a href="sandbox/index.php" class="btn btn-light rounded-pill px-4"><i class="bi bi-grid me-1"></i>Wszystkie narzędzia</a>
                    <?php endif; ?>
                </header>

                <?php if ($tool === 'home'): ?>
                    <section class="sandbox-tool-grid">
                        <?php foreach ($tools as $key => $meta): ?>
                            <?php $toolElementKey = 'tool.' . $key; $toolElementBlock = $sandboxElementBlock($toolElementKey); ?>
                            <?php if ($toolElementBlock): ?>
                                <div class="sandbox-tool-tile is-disabled" data-sandbox-element-key="<?= $sandboxEsc($toolElementKey) ?>" data-sandbox-element-blocked="1" title="<?= $sandboxEsc($sandboxBlockTooltip($toolElementBlock, 'Narzędzie wyłączone')) ?>">
                                    <span class="sandbox-tool-icon"><i class="bi bi-lock"></i></span>
                                    <strong><?= $sandboxEsc($meta['title']) ?></strong>
                                    <span><?= $sandboxEsc($toolElementBlock['body'] ?? 'To narzędzie jest obecnie wyłączone dla Twojej roli.') ?></span>
                                    <?= $sandboxBlockMetaList($toolElementBlock) ?>
                                    <span class="sandbox-tool-chip">Wyłączone</span>
                                </div>
                            <?php else: ?>
                            <a class="sandbox-tool-tile" href="index.php?tool=<?= htmlspecialchars($key) ?>">
                                <span class="sandbox-tool-icon"><i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i></span>
                                <strong><?= htmlspecialchars($meta['title']) ?></strong>
                                <span><?= htmlspecialchars($meta['desc']) ?></span>
                                <span class="sandbox-tool-chip">Uruchom narzędzie</span>
                                <i class="bi bi-arrow-right-short sandbox-arrow"></i>
                            </a>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <a class="sandbox-tool-tile" href="cli_lab.php">
                            <span class="sandbox-tool-icon"><i class="bi bi-terminal-fill"></i></span>
                            <strong>CLI Lab — Terminal</strong>
                            <span>Ćwicz polecenia Linux i Windows w symulowanym terminalu. Komendy sieciowe, systemctl, iptables i scenariusze egzaminacyjne.</span>
                            <span class="sandbox-tool-chip">Linux &amp; Windows</span>
                            <i class="bi bi-arrow-right-short sandbox-arrow"></i>
                        </a>

                        <a class="sandbox-tool-tile" href="subnetting_challenge.php">
                            <span class="sandbox-tool-icon"><i class="bi bi-router-fill"></i></span>
                            <strong>Subnetting Challenge</strong>
                            <span>Timed speed challenge — obliczaj sieć, broadcast, hosty z IPv4. Streak multiplier, 4 poziomy trudności i tabela wyników.</span>
                            <span class="sandbox-tool-chip">Mini-gra z XP</span>
                            <i class="bi bi-arrow-right-short sandbox-arrow"></i>
                        </a>
                    </section>
                <?php else: ?>
                    <nav class="sandbox-tabs mb-4" aria-label="Narzędzia sandbox">
                        <?php foreach ($tools as $key => $meta): ?>
                            <?php $toolElementKey = 'tool.' . $key; $toolElementBlock = $sandboxElementBlock($toolElementKey); ?>
                            <?php if ($toolElementBlock): ?>
                                <span class="is-disabled" data-sandbox-element-key="<?= $sandboxEsc($toolElementKey) ?>" data-sandbox-element-blocked="1" title="<?= $sandboxEsc($sandboxBlockTooltip($toolElementBlock, 'Narzędzie wyłączone')) ?>">
                                    <i class="bi bi-lock"></i><?= $sandboxEsc($meta['title']) ?>
                                </span>
                            <?php else: ?>
                            <a href="index.php?tool=<?= htmlspecialchars($key) ?>" class="<?= $tool === $key ? 'active' : '' ?>" data-sandbox-element-key="<?= htmlspecialchars($toolElementKey) ?>">
                                <i class="bi <?= htmlspecialchars($meta['icon']) ?>"></i><?= htmlspecialchars($meta['title']) ?>
                            </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>

                <?php if ($sandboxToolBlock): ?>
                    <section class="sandbox-panel" data-sandbox-element-key="<?php echo htmlspecialchars('tool.' . $tool); ?>" data-sandbox-element-blocked="1">
                        <div class="d-flex align-items-start gap-3">
                            <div class="fs-2 text-warning"><i class="bi bi-lock-fill"></i></div>
                            <div>
                                <span class="badge text-bg-warning rounded-pill mb-2">Narzędzie wyłączone</span>
                                <h3 class="fw-900 mb-2"><?php echo htmlspecialchars($sandboxToolBlock['title'] ?? 'Narzędzie jest wyłączone'); ?></h3>
                                <p class="text-muted mb-3"><?php echo nl2br(htmlspecialchars($sandboxToolBlock['body'] ?? 'Ten element sandboxa jest obecnie niedostępny dla Twojej roli.')); ?></p>
                                <p class="small text-muted mb-2"><?php echo htmlspecialchars($sandboxToolBlock['element_label'] ?? ($tools[$tool]['title'] ?? 'Sandbox')); ?></p>
                                <?= $sandboxBlockMetaList($sandboxToolBlock) ?>
                            </div>
                        </div>
                    </section>
                <?php elseif ($tool === 'logic'): ?>
                    <section class="sandbox-workbench logic-workbench" data-tool="logic">
                        <aside class="sandbox-rail">
                            <h2 class="fw-800 mb-3 fs-5">Komponenty</h2>
                            <div class="toolbox-group">
                                <span>Wejścia</span>
                                <?php $sandboxRenderLogicButton('logic.input_a', 'Przełącznik A', 'bi-toggle-on', ['data-logic-input' => 'A']); ?>
                                <?php $sandboxRenderLogicButton('logic.input_b', 'Przełącznik B', 'bi-toggle-on', ['data-logic-input' => 'B']); ?>
                                <?php $sandboxRenderLogicButton('logic.const_1', 'Stała 1', 'bi-1-circle', ['data-logic-const' => '1']); ?>
                                <?php $sandboxRenderLogicButton('logic.const_0', 'Stała 0', 'bi-0-circle', ['data-logic-const' => '0']); ?>
                            </div>
                            <div class="toolbox-group">
                                <span>Bramki</span>
                                <?php foreach (['BUFFER','NOT','AND','NAND','OR','NOR','XOR','XNOR'] as $gate): ?>
                                    <?php $sandboxRenderLogicButton('logic.gate_' . strtolower($gate), $gate, 'bi-cpu', ['data-gate' => $gate]); ?>
                                <?php endforeach; ?>
                            </div>
                            <div class="toolbox-group">
                                <span>Wyjścia</span>
                                <?php $sandboxRenderLogicButton('logic.output_led', 'LED', 'bi-lightbulb', ['data-output' => 'LED']); ?>
                                <?php $sandboxRenderLogicButton('logic.output_table', 'Tabela prawdy', 'bi-table', ['data-output' => 'TABLE']); ?>
                            </div>
                        </aside>
                        <div class="logic-canvas-panel">
                            <div class="logic-toolbar">
                                <button id="logicDemo" type="button" class="btn btn-sm btn-primary"><i class="bi bi-magic me-1"></i>Demo</button>
                                <button id="logicReset" type="button" class="btn btn-sm btn-light border"><i class="bi bi-arrow-counterclockwise me-1"></i>Wyczyść</button>
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
                            <h2 class="fw-800 mb-3 fs-5">Konfiguracja komputera</h2>
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
                            <h2 class="fw-800 mb-3 fs-5"><i class="bi bi-question-circle me-2 text-primary"></i>Jak to liczyć</h2>
                            <ol class="small text-muted mb-0">
                                <li>Wpisz adres i prefiks CIDR, np. <strong>/24</strong>.</li>
                                <li>Prefiks zamienia się na maskę, która odcina część sieciową od hostów.</li>
                                <li>Adres sieci to IP po operacji AND z maską; broadcast to ostatni adres podsieci.</li>
                                <li>Zakres hostów jest między adresem sieci i broadcastem; dla IPv6 pokazujemy prefiks i wielkość puli.</li>
                            </ol>
                        </div>
                        <div class="sandbox-panel">
                            <h2 class="fw-800 mb-3 fs-5">IPv4</h2>
                            <div class="row g-3">
                                <div class="col-md-8"><input id="ipv4Input" class="form-control" value="192.168.10.34"></div>
                                <div class="col-md-4"><input id="ipv4Cidr" class="form-control" type="number" min="1" max="32" value="24"></div>
                            </div>
                            <div id="ipv4Out" class="result-grid mt-3"></div>
                        </div>
                        <div class="sandbox-panel">
                            <h2 class="fw-800 mb-3 fs-5">IPv6</h2>
                            <div class="row g-3">
                                <div class="col-md-8"><input id="ipv6Input" class="form-control" value="2001:db8:abcd:0012::1"></div>
                                <div class="col-md-4"><input id="ipv6Prefix" class="form-control" type="number" min="1" max="128" value="64"></div>
                            </div>
                            <div id="ipv6Out" class="result-grid mt-3"></div>
                        </div>
                    </section>
                <?php elseif ($tool === 'router'): ?>
                    <section class="network-lab-embed" data-tool="router">
                        <iframe
                            src="network_lab.php"
                            style="border:0;"
                            title="Laboratorium sieci INF.02"
                            class="network-lab-frame"
                            loading="eager"
                            referrerpolicy="same-origin"
                            allowfullscreen></iframe>
                    </section>
                <?php elseif ($tool === 'numbers'): ?>
                    <section class="sandbox-workbench numbers-workbench" data-tool="numbers">
                        <div class="sandbox-panel">
                            <h2 class="fw-800 mb-3 fs-5">Konwerter</h2>
                            <div class="row g-3">
                                <div class="col-md-8"><input id="numInput" class="form-control" value="255"></div>
                                <div class="col-md-4"><select id="numBase" class="form-select"><option value="10">DEC</option><option value="2">BIN</option><option value="8">OCT</option><option value="16">HEX</option></select></div>
                            </div>
                            <div id="numOut" class="result-grid mt-3"></div>
                        </div>
                        <div class="sandbox-panel">
                            <h2 class="fw-800 mb-3 fs-5">Operacje bitowe</h2>
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
                            <h2 class="fw-800 mb-3 fs-5">Prawo Ohma</h2>
                            <div class="row g-3">
                                <div class="col-md-4"><label class="form-label">Napięcie V<input id="ohmVoltage" class="form-control" type="number" step="0.01" value="5"></label></div>
                                <div class="col-md-4"><label class="form-label">Prąd A<input id="ohmCurrent" class="form-control" type="number" step="0.001" value="0.02"></label></div>
                                <div class="col-md-4"><label class="form-label">Opór Ω<input id="ohmResistance" class="form-control" type="number" step="0.1" value=""></label></div>
                            </div>
                            <div id="ohmOut" class="result-grid mt-3"></div>
                        </div>
                        <div class="sandbox-panel">
                            <h2 class="fw-800 mb-3 fs-5">Rezystor LED</h2>
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
                        <div class="sandbox-panel live-editor-panel">
                            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                <div class="d-flex align-items-center gap-2">
                                    <h2 class="fw-800 mb-0 fs-5">Edytor kodu</h2>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill" id="liveStatusBadge">
                                        <i class="bi bi-circle-fill me-1" style="font-size: 0.55rem;"></i>Na żywo
                                    </span>
                                </div>
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <div class="btn-group btn-group-sm" role="group" id="editorViewSwitcher" aria-label="Wybór widoku edytora">
                                        <button type="button" class="btn btn-outline-primary active" data-editor-tab="html"><i class="bi bi-filetype-html me-1"></i>HTML</button>
                                        <button type="button" class="btn btn-outline-primary" data-editor-tab="css"><i class="bi bi-filetype-css me-1"></i>CSS</button>
                                        <button type="button" class="btn btn-outline-primary" data-editor-tab="js"><i class="bi bi-filetype-js me-1"></i>JS</button>
                                        <button type="button" class="btn btn-outline-primary" data-editor-tab="split" title="Pokaż wszystkie 3 edytory obok siebie"><i class="bi bi-columns-gap me-1"></i>Podzielony</button>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill" type="button" id="livePresetsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-collection me-1"></i>Szablony
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="livePresetsDropdown">
                                            <li><button class="dropdown-item active" type="button" data-live-preset="counter"><i class="bi bi-hand-index-thumb me-2 text-primary"></i>Karta i Przycisk (DOM)</button></li>
                                            <li><button class="dropdown-item" type="button" data-live-preset="card"><i class="bi bi-palette me-2 text-success"></i>Animowana karta &amp; Gradient</button></li>
                                            <li><button class="dropdown-item" type="button" data-live-preset="form"><i class="bi bi-input-cursor-text me-2 text-warning"></i>Walidator hasła na żywo</button></li>
                                            <li><button class="dropdown-item" type="button" data-live-preset="theme"><i class="bi bi-moon-stars me-2 text-info"></i>Przełącznik motywu (Dark/Light)</button></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="code-editors-wrapper" id="codeEditorsWrapper" data-active-tab="html">
                                <div class="code-editor-pane" data-pane="html">
                                    <div class="code-editor-header">
                                        <span class="code-lang-tag"><i class="bi bi-filetype-html text-danger me-1"></i>Struktura HTML</span>
                                        <span class="code-hint">Skrót: Tab wstawia 2 spacje</span>
                                    </div>
                                    <textarea id="htmlCode" class="form-control code-textarea" spellcheck="false" placeholder="Wpisz kod HTML...">&lt;div class="app-card"&gt;
  &lt;div class="badge"&gt;ZSEM Tech Live&lt;/div&gt;
  &lt;h1&gt;Interaktywny Przycisk&lt;/h1&gt;
  &lt;p&gt;Zmieniaj kod w edytorze po lewej — podgląd odświeża się w czasie rzeczywistym!&lt;/p&gt;
  &lt;button id="btn" type="button"&gt;Kliknij mnie ✨&lt;/button&gt;
  &lt;div id="out" class="status-box"&gt;Oczekiwanie na kliknięcie...&lt;/div&gt;
&lt;/div&gt;</textarea>
                                </div>

                                <div class="code-editor-pane d-none" data-pane="css">
                                    <div class="code-editor-header">
                                        <span class="code-lang-tag"><i class="bi bi-filetype-css text-primary me-1"></i>Style CSS</span>
                                        <span class="code-hint">Automatyczny reset i font Inter</span>
                                    </div>
                                    <textarea id="cssCode" class="form-control code-textarea" spellcheck="false" placeholder="Wpisz reguły CSS...">body {
  margin: 0;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  color: #f8fafc;
  padding: 24px;
}

.app-card {
  background: rgba(30, 41, 59, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 20px;
  padding: 32px;
  max-width: 440px;
  width: 100%;
  text-align: center;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(16px);
}

.badge {
  display: inline-block;
  background: rgba(59, 130, 246, 0.2);
  color: #60a5fa;
  border: 1px solid rgba(96, 165, 250, 0.3);
  padding: 4px 12px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  margin-bottom: 14px;
}

h1 {
  font-size: 1.5rem;
  font-weight: 800;
  margin: 0 0 10px 0;
  color: #ffffff;
}

p {
  font-size: 0.9rem;
  color: #94a3b8;
  line-height: 1.5;
  margin: 0 0 24px 0;
}

button {
  background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
  color: #ffffff;
  border: none;
  padding: 12px 28px;
  font-size: 0.95rem;
  font-weight: 700;
  border-radius: 999px;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
  transition: transform 0.15s, box-shadow 0.15s;
}

button:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(37, 99, 235, 0.5);
}

button:active {
  transform: translateY(0);
}

.status-box {
  margin-top: 20px;
  padding: 12px;
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: #38bdf8;
  font-weight: 600;
  font-size: 0.88rem;
}</textarea>
                                </div>

                                <div class="code-editor-pane d-none" data-pane="js">
                                    <div class="code-editor-header">
                                        <span class="code-lang-tag"><i class="bi bi-filetype-js text-warning me-1"></i>Skrypt JavaScript</span>
                                        <span class="code-hint">Błędy i logi trafiają do konsoli</span>
                                    </div>
                                    <textarea id="jsCode" class="form-control code-textarea" spellcheck="false" placeholder="Wpisz kod JS...">let count = 0;
const btn = document.getElementById('btn');
const out = document.getElementById('out');

btn.onclick = () => {
  count++;
  out.textContent = `Kliknięto ${count} raz${count === 1 ? '' : (count > 1 && count < 5 ? 'y' : 'y')}! Działa wyśmienicie 🚀`;
  console.log(`Licznik kliknięć: ${count}`);
};</textarea>
                                </div>
                            </div>

                            <div id="codeWarning" class="alert alert-warning d-none mt-3 mb-0"></div>

                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3 pt-2 border-top">
                                <div class="d-flex gap-2 align-items-center">
                                    <button id="runCode" class="btn btn-primary rounded-pill px-3" type="button" title="Uruchom kod (Ctrl+Enter)">
                                        <i class="bi bi-play-fill me-1"></i>Uruchom
                                    </button>
                                    <button id="liveDemo" class="btn btn-outline-primary rounded-pill px-3" type="button">
                                        <i class="bi bi-stars me-1"></i>Demo
                                    </button>
                                    <button id="clearCode" class="btn btn-light border rounded-pill px-3" type="button">
                                        <i class="bi bi-eraser me-1"></i>Wyczyść
                                    </button>
                                </div>
                                <span class="text-muted small d-none d-sm-inline">
                                    <kbd class="bg-light text-dark border">Ctrl</kbd> + <kbd class="bg-light text-dark border">Enter</kbd> aby uruchomić
                                </span>
                            </div>
                        </div>

                        <div class="sandbox-panel live-preview-panel p-0 overflow-hidden d-flex flex-column">
                            <div class="live-preview-header d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="window-dots d-flex gap-1 me-2">
                                        <span class="dot dot-red"></span>
                                        <span class="dot dot-yellow"></span>
                                        <span class="dot dot-green"></span>
                                    </div>
                                    <span class="small fw-bold text-muted d-none d-md-inline">Podgląd na żywo</span>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-light border btn-sm active" id="viewportDesktopBtn" title="Pełna szerokość (Desktop)"><i class="bi bi-display"></i></button>
                                        <button type="button" class="btn btn-light border btn-sm" id="viewportMobileBtn" title="Widok mobilny (375px)"><i class="bi bi-phone"></i></button>
                                    </div>
                                    <button type="button" class="btn btn-light border btn-sm" id="reloadPreviewBtn" title="Przeładuj podgląd"><i class="bi bi-arrow-clockwise"></i></button>
                                    <button type="button" class="btn btn-light border btn-sm" id="toggleConsoleBtn" title="Pokaż/Ukryj konsolę">
                                        <i class="bi bi-terminal me-1"></i>Konsola <span class="badge bg-secondary rounded-pill" id="consoleBadge">0</span>
                                    </button>
                                </div>
                            </div>
                            <div class="live-preview-viewport-wrapper flex-grow-1 d-flex align-items-center justify-content-center" id="previewViewportWrap">
                                <iframe id="codePreview" class="preview-frame" sandbox="allow-scripts allow-forms allow-modals allow-popups" title="Podgląd kodu na żywo"></iframe>
                            </div>
                            <div class="live-console-drawer border-top d-none" id="liveConsoleDrawer">
                                <div class="live-console-header d-flex justify-content-between align-items-center px-3 py-1 bg-dark text-white border-bottom">
                                    <span class="small font-monospace"><i class="bi bi-terminal-fill me-1 text-info"></i>Konsola deweloperska</span>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-link btn-sm text-secondary p-0 text-decoration-none" id="clearConsoleBtn"><i class="bi bi-trash3 me-1"></i>Wyczyść</button>
                                        <button type="button" class="btn-close btn-close-white btn-sm ms-2" id="closeConsoleBtn" aria-label="Zamknij"></button>
                                    </div>
                                </div>
                                <div class="live-console-body font-monospace p-2" id="liveConsoleOutput">
                                    <div class="text-muted small">Brak logów w konsoli. Użyj console.log() w sekcji JS.</div>
                                </div>
                            </div>
                        </div>
                    </section>
                <?php elseif ($tool === 'crypto'): ?>
                    <section class="sandbox-workbench crypto-workbench" data-tool="crypto">
                        <div class="sandbox-panel">
                            <h2 class="fw-800 mb-3 fs-5">Generator Haseł</h2>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label">Długość hasła</label>
                                    <input id="pwdLength" class="form-control" type="number" min="8" max="128" value="16">
                                </div>
                                <div class="col-md-7 d-flex gap-3 align-items-center mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="pwdUpper" checked>
                                        <label class="form-check-label" for="pwdUpper">A-Z</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="pwdLower" checked>
                                        <label class="form-check-label" for="pwdLower">a-z</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="pwdNum" checked>
                                        <label class="form-check-label" for="pwdNum">0-9</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="pwdSym" checked>
                                        <label class="form-check-label" for="pwdSym">!@#$</label>
                                    </div>
                                </div>
                                <div class="col-md-2 text-md-end">
                                    <button id="pwdGenerate" class="btn btn-primary w-100 rounded-pill"><i class="bi bi-arrow-clockwise me-1"></i>Generuj</button>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="input-group input-group-lg">
                                    <input type="text" id="pwdResult" class="form-control font-monospace" readonly placeholder="Kliknij Generuj...">
                                    <button class="btn btn-outline-secondary" type="button" id="pwdCopy" title="Kopiuj do schowka"><i class="bi bi-clipboard"></i></button>
                                </div>
                            </div>
                        </div>

                        <div class="sandbox-panel">
                            <h2 class="fw-800 mb-3 fs-5">Konwerter tekstowy</h2>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Tekst wejściowy</label>
                                    <textarea id="cryptoInput" class="form-control font-monospace" rows="4" style="max-height: 400px; min-height: 120px; resize: vertical;" placeholder="Wpisz tekst tutaj..."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Wynik</label>
                                    <textarea id="cryptoOutput" class="form-control font-monospace" rows="4" style="max-height: 400px; min-height: 120px; resize: vertical;" readonly></textarea>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <button type="button" class="btn btn-outline-primary rounded-pill btn-sm" id="cryptoB64Enc">Base64 Encode</button>
                                <button type="button" class="btn btn-outline-primary rounded-pill btn-sm" id="cryptoB64Dec">Base64 Decode</button>
                                <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm" id="cryptoUrlEnc">URL Encode</button>
                                <button type="button" class="btn btn-outline-secondary rounded-pill btn-sm" id="cryptoUrlDec">URL Decode</button>
                                <button type="button" class="btn btn-light border rounded-pill btn-sm" id="cryptoClear"><i class="bi bi-eraser me-1"></i>Wyczyść</button>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
            </div>
            <script>
            window.sandboxBlockedElements = <?php echo json_encode(array_map(static function (array $block): array {
                return [
                    'title' => (string)($block['title'] ?? 'Element wyłączony'),
                    'body' => (string)($block['body'] ?? ''),
                    'label' => (string)($block['element_label'] ?? ($block['element_key'] ?? 'Element sandboxa')),
                ];
            }, $sandboxBlockedElements), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            </script>
            <script src="<?php echo htmlspecialchars(assetUrl('assets/js/sandbox.js', rtrim($base_url, '/'))); ?>"></script>
        </main>
        <?php include '../includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js', rtrim($base_url, '/'))); ?>"></script>
</body>
</html>

