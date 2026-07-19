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
                        <div class="sandbox-panel">
                            <h2 class="fw-800 mb-3 fs-5">Edytor</h2>
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
                                    <textarea id="cryptoInput" class="form-control font-monospace" rows="4" placeholder="Wpisz tekst tutaj..."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Wynik</label>
                                    <textarea id="cryptoOutput" class="form-control font-monospace" rows="4" readonly></textarea>
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

