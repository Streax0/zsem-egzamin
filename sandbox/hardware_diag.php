<?php
declare(strict_types=1);

require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin(true);

$pageTitle = 'Symulator Diagnostyki Sprzętu PC & Pomiary Multimetrem — ZSEM Tech';
$base_url = '../';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="../assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/style.css', '..')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(assetUrl('assets/css/dashboard-new.css', '..')) ?>">
    <style>
        .diag-shell { max-width: 1200px; margin: 0 auto; }
        .multimeter-display {
            font-family: 'JetBrains Mono', 'Courier New', monospace;
            background: #a3b899;
            color: #1a2e15;
            font-size: 2.5rem;
            font-weight: 900;
            padding: 1rem;
            border-radius: 8px;
            border: 4px solid #333;
            text-align: right;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.3);
            letter-spacing: 2px;
        }
        .pin-cell {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.75rem;
            border: 2px solid #222;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            user-select: none;
            border-radius: 4px;
        }
        .pin-cell:hover { transform: scale(1.15); box-shadow: 0 0 10px rgba(99,102,241,0.6); z-index: 2; }
        .pin-3v3 { background: #ea580c; color: #fff; }
        .pin-5v { background: #dc2626; color: #fff; }
        .pin-12v { background: #ca8a04; color: #fff; }
        .pin-gnd { background: #1e293b; color: #fff; }
        .pin-sb { background: #7e22ce; color: #fff; }
        .pin-ok { background: #64748b; color: #fff; }
        .pin-on { background: #16a34a; color: #fff; }
        .post-display {
            font-family: 'JetBrains Mono', monospace;
            background: #111;
            color: #ef4444;
            font-size: 3rem;
            font-weight: bold;
            padding: 0.5rem 1.5rem;
            border-radius: 6px;
            display: inline-block;
            border: 2px solid #333;
            text-shadow: 0 0 12px rgba(239,68,68,0.7);
        }
    </style>
</head>
<body class="bg-body-tertiary">

<div class="container py-4 diag-shell">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <a href="../sandbox/index.php" class="btn btn-sm btn-outline-secondary rounded-pill mb-2"><i class="bi bi-arrow-left me-1"></i> Sandbox</a>
            <h1 class="h3 fw-bold text-gradient mb-0"><i class="bi bi-cpu-fill text-primary me-2"></i>Symulator Diagnostyki Sprzętu PC (INF.02)</h1>
            <p class="text-muted small mb-0">Wirtualny multimetr cyfrowy, kody błędów POST i konfigurator zgodności podzespołów.</p>
        </div>
        <div>
            <span class="badge bg-primary px-3 py-2 fs-6"><i class="bi bi-shield-check me-1"></i> Egzamin INF.02 / EE.08</span>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 gap-2" id="diagTab" role="tablist">
        <li class="nav-item">
            <button class="nav-link active px-4 fw-bold" id="psu-tab" data-bs-toggle="pill" data-bs-target="#tab-psu" type="button"><i class="bi bi-lightning-charge-fill me-2"></i>Pomiary Zasilacza ATX</button>
        </li>
        <li class="nav-item">
            <button class="nav-link px-4 fw-bold" id="post-tab" data-bs-toggle="pill" data-bs-target="#tab-post" type="button"><i class="bi bi-motherboard-fill me-2"></i>Kody POST & Sygnały BIOS</button>
        </li>
        <li class="nav-item">
            <button class="nav-link px-4 fw-bold" id="compat-tab" data-bs-toggle="pill" data-bs-target="#tab-compat" type="button"><i class="bi bi-memory me-2"></i>Zgodność CPU & RAM</button>
        </li>
    </ul>

    <div class="tab-content" id="diagTabContent">

        <!-- TAB 1: MULTIMETER & ATX PSU -->
        <div class="tab-pane fade show active" id="tab-psu" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-body">
                        <h4 class="h5 fw-bold mb-3"><i class="bi bi-speedometer2 text-warning me-2"></i>Multimetr Cyfrowy (DCV 20V)</h4>
                        <div class="multimeter-display mb-3" id="multimeterReadout">0.00 V</div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="small text-muted">Wybrany pin pomiarowy:</span>
                            <strong class="badge bg-secondary fs-6" id="selectedPinLabel">Brak (COM niepodłączony)</strong>
                        </div>
                        <div class="alert alert-info small mb-3" id="voltageEvaluation">
                            <i class="bi bi-info-circle me-1"></i> Kliknij dowolny pin złącza 24-pin ATX, aby przyłożyć sondę multimetru.
                        </div>
                        <div class="p-3 bg-body-tertiary rounded-3 small">
                            <strong class="d-block mb-1">Dopuszczalne normy ATX (±5%):</strong>
                            <div class="d-flex justify-content-between border-bottom py-1"><span>Linia +12V:</span> <span>11.40 V – 12.60 V</span></div>
                            <div class="d-flex justify-content-between border-bottom py-1"><span>Linia +5V:</span> <span>4.75 V – 5.25 V</span></div>
                            <div class="d-flex justify-content-between py-1"><span>Linia +3.3V:</span> <span>3.14 V – 3.47 V</span></div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-body">
                        <h4 class="h5 fw-bold mb-3"><i class="bi bi-plug-fill text-primary me-2"></i>Złącze Zasilacza ATX 24-Pin</h4>
                        <p class="small text-muted mb-3">Sonda czarna (COM) jest podłączona do masy (GND). Klikaj piny, aby zmierzyć napięcia względem masy:</p>
                        
                        <div class="p-3 bg-dark rounded-4 text-center mb-4">
                            <div class="text-secondary small mb-2 fw-bold">ZATRZASK BLOKADY (GÓRA)</div>
                            <div class="d-flex justify-content-center gap-1 mb-1" id="atxRow1"></div>
                            <div class="d-flex justify-content-center gap-1" id="atxRow2"></div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 small">
                            <span class="badge pin-3v3">+3.3V (Pomarańczowy)</span>
                            <span class="badge pin-5v">+5V (Czerwony)</span>
                            <span class="badge pin-12v">+12V (Żółty)</span>
                            <span class="badge pin-gnd">GND (Czarny)</span>
                            <span class="badge pin-sb">+5VSB (Fioletowy)</span>
                            <span class="badge pin-ok">PWR_OK (Szary)</span>
                            <span class="badge pin-on">PS_ON# (Zielony)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: POST CODES & BIOS BEEPS -->
        <div class="tab-pane fade" id="tab-post" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-body text-center">
                        <h4 class="h5 fw-bold mb-3"><i class="bi bi-display text-danger me-2"></i>Karta Diagnostyczna POST (Wyświetlacz Hex)</h4>
                        <div class="my-3">
                            <div class="post-display" id="postHexDisplay">55</div>
                        </div>
                        <h5 class="fw-bold text-primary mt-2" id="postCodeTitle">Błąd pamięci RAM (Memory not installed)</h5>
                        <p class="small text-muted mb-4" id="postCodeDesc">Płyta główna nie wykryła modułów pamięci RAM lub moduły są niepoprawnie osadzone w bankach DIMM.</p>
                        
                        <div class="d-flex justify-content-center gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm btn-post-test" data-hex="00" data-title="Brak startu CPU / Uszkodzony procesor" data-desc="Płyta nie inicjuje procedury POST. Brak zasilania CPU lub uszkodzony układ VRM.">Kod 00</button>
                            <button type="button" class="btn btn-outline-primary btn-sm btn-post-test" data-hex="55" data-title="Błąd pamięci RAM" data-desc="Brak wykrycia pamięci RAM w slotach A2/B2.">Kod 55</button>
                            <button type="button" class="btn btn-outline-primary btn-sm btn-post-test" data-hex="D6" data-title="Brak sygnału GPU / Brak karty graficznej" data-desc="Brak wykrycia karty graficznej PCIe lub zintegrowanego układu iGPU.">Kod D6</button>
                            <button type="button" class="btn btn-outline-primary btn-sm btn-post-test" data-hex="A2" data-title="Inicjalizacja dysków SATA/NVMe" data-desc="Wykrywanie kontrolera dyskowego i urządzeń pamięci masowej.">Kod A2</button>
                            <button type="button" class="btn btn-outline-success btn-sm btn-post-test" data-hex="AA" data-title="System OK (Boot Completed)" data-desc="Procedura POST zakończona pomyślnie. Kontrola przekazana do bootloadera OS.">Kod AA</button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-body">
                        <h4 class="h5 fw-bold mb-3"><i class="bi bi-volume-up-fill text-info me-2"></i>Sygnały Dźwiękowe BIOS (Beep Codes)</h4>
                        <p class="small text-muted mb-3">Kliknij sygnał, aby odsłuchać sekwencję dźwiękową buzzera i poznać diagnozę:</p>
                        
                        <div class="list-group list-group-flush gap-2">
                            <button type="button" class="list-group-item list-group-item-action rounded-3 border btn-beep-play" data-beeps="1-short" data-meaning="1 krótki: Test pamięci RAM OK, start pomyślny.">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><i class="bi bi-play-circle-fill text-success me-2"></i>1 krótki sygnał</strong>
                                    <span class="badge bg-success-subtle text-success">Pomyślny POST</span>
                                </div>
                            </button>
                            <button type="button" class="list-group-item list-group-item-action rounded-3 border btn-beep-play" data-beeps="1-long-2-short" data-meaning="1 długi, 2 krótkie: Błąd karty graficznej (GPU).">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><i class="bi bi-play-circle-fill text-danger me-2"></i>1 długi + 2 krótkie</strong>
                                    <span class="badge bg-danger-subtle text-danger">Błąd Karty Graficznej</span>
                                </div>
                            </button>
                            <button type="button" class="list-group-item list-group-item-action rounded-3 border btn-beep-play" data-beeps="continuous" data-meaning="Ciągły dźwięk: Uszkodzenie zasilacza lub brak zasilania CPU.">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong><i class="bi bi-play-circle-fill text-warning me-2"></i>Ciągły sygnał</strong>
                                    <span class="badge bg-warning-subtle text-warning">Zasilanie / Zasilacz</span>
                                </div>
                            </button>
                        </div>
                        <div class="alert alert-secondary mt-3 small mb-0" id="beepMeaningBox">
                            Kliknij dowolną sekwencję, aby zsyntetyzować dźwięk buzzera płyty.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: COMPATIBILITY CPU & RAM -->
        <div class="tab-pane fade" id="tab-compat" role="tabpanel">
            <div class="card border-0 shadow-sm p-4 rounded-4 bg-body">
                <h4 class="h5 fw-bold mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Weryfikator Zgodności Socket CPU & Modułów RAM</h4>
                
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Socket Płyty Głównej:</label>
                        <select class="form-select" id="selSocket">
                            <option value="LGA1700">Intel LGA1700 (12/13/14 Gen)</option>
                            <option value="AM5">AMD AM5 (Ryzen 7000/8000/9000)</option>
                            <option value="AM4">AMD AM4 (Ryzen 1000-5000)</option>
                            <option value="LGA1200">Intel LGA1200 (10/11 Gen)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Model Procesora:</label>
                        <select class="form-select" id="selCpu">
                            <option value="i5-13600K" data-socket="LGA1700">Intel Core i5-13600K</option>
                            <option value="Ryzen-7800X3D" data-socket="AM5">AMD Ryzen 7 7800X3D</option>
                            <option value="Ryzen-5600X" data-socket="AM4">AMD Ryzen 5 5600X</option>
                            <option value="i7-11700K" data-socket="LGA1200">Intel Core i7-11700K</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">Standard Pamięci RAM:</label>
                        <select class="form-select" id="selRam">
                            <option value="DDR5">DDR5 SDRAM (288-pin, 1.1V, wbudowany PMIC)</option>
                            <option value="DDR4">DDR4 SDRAM (288-pin, 1.2V)</option>
                            <option value="DDR3">DDR3 SDRAM (240-pin, 1.5V)</option>
                        </select>
                    </div>
                </div>

                <div id="compatResultBox" class="alert alert-success p-3 rounded-3 mb-0">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill fs-3 text-success me-3"></i>
                        <div>
                            <strong class="h6 mb-1 d-block">Pełna Zgodność Podzespołów (INF.02 Standard)</strong>
                            <span class="small" id="compatDetails">Procesor Intel LGA1700 jest w 100% kompatybilny z socketem płyty oraz obsługuje pamięci DDR5/DDR4.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
// ATX Pin definitions
const atxPins = [
    // Row 1 (Pins 1 to 12)
    { pin: 1, name: "+3.3V", volt: 3.34, cls: "pin-3v3" },
    { pin: 2, name: "+3.3V", volt: 3.32, cls: "pin-3v3" },
    { pin: 3, name: "GND", volt: 0.00, cls: "pin-gnd" },
    { pin: 4, name: "+5V", volt: 5.04, cls: "pin-5v" },
    { pin: 5, name: "GND", volt: 0.00, cls: "pin-gnd" },
    { pin: 6, name: "+5V", volt: 5.02, cls: "pin-5v" },
    { pin: 7, name: "GND", volt: 0.00, cls: "pin-gnd" },
    { pin: 8, name: "PWR_OK", volt: 5.00, cls: "pin-ok" },
    { pin: 9, name: "+5VSB", volt: 5.08, cls: "pin-sb" },
    { pin: 10, name: "+12V", volt: 12.18, cls: "pin-12v" },
    { pin: 11, name: "+12V", volt: 12.16, cls: "pin-12v" },
    { pin: 12, name: "+3.3V", volt: 3.33, cls: "pin-3v3" },
    // Row 2 (Pins 13 to 24)
    { pin: 13, name: "+3.3V", volt: 3.35, cls: "pin-3v3" },
    { pin: 14, name: "-12V", volt: -11.95, cls: "pin-gnd" },
    { pin: 15, name: "GND", volt: 0.00, cls: "pin-gnd" },
    { pin: 16, name: "PS_ON#", volt: 3.28, cls: "pin-on" },
    { pin: 17, name: "GND", volt: 0.00, cls: "pin-gnd" },
    { pin: 18, name: "GND", volt: 0.00, cls: "pin-gnd" },
    { pin: 19, name: "GND", volt: 0.00, cls: "pin-gnd" },
    { pin: 20, name: "NC", volt: 0.00, cls: "pin-gnd" },
    { pin: 21, name: "+5V", volt: 5.01, cls: "pin-5v" },
    { pin: 22, name: "+5V", volt: 5.03, cls: "pin-5v" },
    { pin: 23, name: "+5V", volt: 5.05, cls: "pin-5v" },
    { pin: 24, name: "GND", volt: 0.00, cls: "pin-gnd" }
];

const r1 = document.getElementById('atxRow1');
const r2 = document.getElementById('atxRow2');

atxPins.slice(0, 12).forEach(p => {
    const el = document.createElement('div');
    el.className = `pin-cell ${p.cls}`;
    el.textContent = p.pin;
    el.title = `Pin ${p.pin}: ${p.name} (Nominalnie ${p.volt}V)`;
    el.addEventListener('click', () => measurePin(p));
    r1.appendChild(el);
});

atxPins.slice(12, 24).forEach(p => {
    const el = document.createElement('div');
    el.className = `pin-cell ${p.cls}`;
    el.textContent = p.pin;
    el.title = `Pin ${p.pin}: ${p.name} (Nominalnie ${p.volt}V)`;
    el.addEventListener('click', () => measurePin(p));
    r2.appendChild(el);
});

function measurePin(pin) {
    const readout = document.getElementById('multimeterReadout');
    const label = document.getElementById('selectedPinLabel');
    const evalBox = document.getElementById('voltageEvaluation');

    readout.textContent = `${pin.volt >= 0 ? '+' : ''}${pin.volt.toFixed(2)} V`;
    label.textContent = `Pin ${pin.pin} (${pin.name})`;

    if (pin.name === 'GND') {
        evalBox.className = 'alert alert-secondary small mb-3';
        evalBox.innerHTML = '<i class="bi bi-dash-circle me-1"></i> Masa (GND) — 0.00 V. Prawidłowy potencjał odniesienia.';
    } else {
        evalBox.className = 'alert alert-success small mb-3';
        evalBox.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Linia <strong>${pin.name}</strong> ma wartość <strong>${pin.volt} V</strong> — napięcie mieści się w dopuszczalnej normie ATX (±5%).`;
    }
}

// POST Codes Clickers
document.querySelectorAll('.btn-post-test').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('postHexDisplay').textContent = btn.dataset.hex;
        document.getElementById('postCodeTitle').textContent = btn.dataset.title;
        document.getElementById('postCodeDesc').textContent = btn.dataset.desc;
    });
});

// BIOS Beep synthesizer using Web Audio API
function playBeep(freq = 880, duration = 0.15) {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'square';
        osc.frequency.setValueAtTime(freq, audioCtx.currentTime);
        gain.gain.setValueAtTime(0.1, audioCtx.currentTime);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start();
        osc.stop(audioCtx.currentTime + duration);
    } catch (_) {}
}

document.querySelectorAll('.btn-beep-play').forEach(btn => {
    btn.addEventListener('click', () => {
        const type = btn.dataset.beeps;
        const meaning = btn.dataset.meaning;
        document.getElementById('beepMeaningBox').textContent = meaning;

        if (type === '1-short') {
            playBeep(880, 0.12);
        } else if (type === '1-long-2-short') {
            playBeep(880, 0.4);
            setTimeout(() => playBeep(880, 0.12), 500);
            setTimeout(() => playBeep(880, 0.12), 700);
        } else if (type === 'continuous') {
            playBeep(600, 1.2);
        }
    });
});

// Compatibility Checker
function checkCompatibility() {
    const socket = document.getElementById('selSocket').value;
    const cpuOpt = document.getElementById('selCpu').selectedOptions[0];
    const cpuSocket = cpuOpt.dataset.socket;
    const ram = document.getElementById('selRam').value;
    const box = document.getElementById('compatResultBox');
    const details = document.getElementById('compatDetails');

    let ok = true;
    let msg = [];

    if (socket !== cpuSocket) {
        ok = false;
        msg.push(`Niezgodność socketu: Płyta ma gniazdo ${socket}, a procesor wymaga ${cpuSocket}!`);
    }

    if (socket === 'AM5' && ram !== 'DDR5') {
        ok = false;
        msg.push(`Platforma AMD AM5 wymaga wyłącznie pamięci DDR5 (nie obsługuje DDR4/DDR3).`);
    } else if (socket === 'AM4' && ram !== 'DDR4') {
        ok = false;
        msg.push(`Platforma AMD AM4 obsługuje wyłącznie pamięci DDR4.`);
    }

    if (ok) {
        box.className = 'alert alert-success p-3 rounded-3 mb-0';
        details.textContent = 'Wszystkie podzespoły są w 100% kompatybilne. Zestaw komputerowy gotowy do uruchomienia.';
    } else {
        box.className = 'alert alert-danger p-3 rounded-3 mb-0';
        details.textContent = msg.join(' ');
    }
}

document.getElementById('selSocket').addEventListener('change', checkCompatibility);
document.getElementById('selCpu').addEventListener('change', checkCompatibility);
document.getElementById('selRam').addEventListener('change', checkCompatibility);
</script>
</body>
</html>
