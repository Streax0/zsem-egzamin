<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin(true);

$dictionaryFile = __DIR__ . '/data/dictionary.json';
$dictionaryData = [];
if (is_file($dictionaryFile)) {
    $dictionaryData = json_decode((string)file_get_contents($dictionaryFile), true) ?: [];
}

$cards = [];
foreach ($dictionaryData as $group) {
    foreach (($group['terms'] ?? []) as $term) {
        $front = (string)($term['term'] ?? '');
        if ($front === '') continue;
        $cards[] = [
            'qualification' => (string)($group['qualification'] ?? ''),
            'front' => $front,
            'back' => trim((string)($term['definition'] ?? '') . "\n\n" . (string)($term['example'] ?? '')),
            'wiki' => (string)($term['link'] ?? ''),
            'youtube' => 'https://www.youtube.com/results?search_query=' . rawurlencode($front . ' informatyka')
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiszki - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/dashboard-new.css')); ?>">
    <style>
        .flashcard-shell { max-width: 1120px; }
        .flashcard-stage { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 1rem; }
        .flashcard-card { min-height: 360px; border: 1px solid rgba(148,163,184,.24); border-radius: 8px; background: #fff; box-shadow: 0 18px 40px rgba(15,23,42,.08); display: grid; align-content: center; padding: clamp(1.25rem, 4vw, 2.4rem); cursor: pointer; }
        .flashcard-card strong { display: block; font-size: clamp(2rem, 5vw, 3.4rem); line-height: 1.05; color: #0f172a; }
        .flashcard-card p { margin: 1rem 0 0; color: #334155; white-space: pre-line; font-size: 1.02rem; }
        .flashcard-tools, .flashcard-side { border: 1px solid rgba(148,163,184,.24); border-radius: 8px; background: #fff; padding: 1rem; box-shadow: 0 12px 30px rgba(15,23,42,.06); }
        .flashcard-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: .65rem; }
        .flashcard-actions button { min-height: 48px; font-weight: 800; }
        .custom-card-row { display: grid; grid-template-columns: 1fr; gap: .55rem; }
        body.dark-mode .flashcard-card, body.dark-mode .flashcard-tools, body.dark-mode .flashcard-side { background: #1e293b; border-color: rgba(148,163,184,.24); }
        body.dark-mode .flashcard-card strong { color: #f8fafc; }
        body.dark-mode .flashcard-card p { color: #cbd5e1; }
        @media (max-width: 991.98px) { .flashcard-stage { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include 'includes/topbar.php'; ?>
        <main role="main" class="content-body">
            <div class="container-fluid p-0 flashcard-shell">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <h2 class="fw-bold mb-1"><i class="bi bi-card-text text-primary me-2"></i>Fiszki</h2>
                        <p class="text-muted mb-0">Słownik pojęć jako fiszki, powtórki spaced repetition i własne karty.</p>
                    </div>
                    <a class="btn btn-outline-primary rounded-pill px-4" href="dictionary.php"><i class="bi bi-book me-1"></i>Słownik</a>
                </div>

                <section class="flashcard-stage">
                    <div>
                        <div class="flashcard-tools mb-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Zestaw</label>
                                    <select id="flashcardSet" class="form-select">
                                        <option value="all">Wszystkie ze słownika</option>
                                        <option value="due">Do powtórki teraz</option>
                                        <option value="custom">Moje fiszki</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Szukaj</label>
                                    <input id="flashcardSearch" class="form-control" placeholder="Adres IP, DNS, BIOS...">
                                </div>
                            </div>
                        </div>
                        <article id="flashcardCard" class="flashcard-card" tabindex="0" role="button" aria-live="polite"></article>
                        <div class="flashcard-actions mt-3">
                            <button class="btn btn-outline-danger" data-rate="hard"><i class="bi bi-arrow-repeat me-1"></i>Trudne</button>
                            <button class="btn btn-outline-primary" data-rate="medium"><i class="bi bi-clock-history me-1"></i>Średnie</button>
                            <button class="btn btn-outline-success" data-rate="easy"><i class="bi bi-check2-circle me-1"></i>Łatwe</button>
                        </div>
                    </div>
                    <aside class="flashcard-side">
                        <h5 class="fw-bold mb-3">Własna fiszka</h5>
                        <div class="custom-card-row">
                            <input id="customFront" class="form-control" maxlength="120" placeholder="Przód fiszki">
                            <textarea id="customBack" class="form-control" rows="5" maxlength="1000" placeholder="Tył fiszki"></textarea>
                            <button id="addCustomCard" type="button" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Dodaj</button>
                        </div>
                        <hr>
                        <div class="small text-muted" id="flashcardMeta"></div>
                        <div class="d-flex gap-2 mt-3">
                            <a id="flashcardWiki" class="btn btn-sm btn-outline-primary flex-fill" target="_blank" rel="noopener"><i class="bi bi-wikipedia"></i></a>
                            <a id="flashcardYoutube" class="btn btn-sm btn-outline-danger flex-fill" target="_blank" rel="noopener"><i class="bi bi-youtube"></i></a>
                        </div>
                    </aside>
                </section>
            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dictCards = <?php echo json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const progressKey = 'zsem.flashcards.progress.v1';
    const customKey = 'zsem.flashcards.custom.v1';
    let progress = JSON.parse(localStorage.getItem(progressKey) || '{}');
    let custom = JSON.parse(localStorage.getItem(customKey) || '[]');
    let pool = [];
    let index = 0;
    let flipped = false;
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
    const cardId = (card) => `${card.qualification}:${card.front}`;
    const due = (card) => (progress[cardId(card)]?.due || 0) <= Date.now();
    function rebuild() {
        const set = document.getElementById('flashcardSet').value;
        const search = document.getElementById('flashcardSearch').value.trim().toLowerCase();
        const all = set === 'custom' ? custom : dictCards.concat(custom);
        pool = all.filter(card => (set !== 'due' || due(card)) && (!search || card.front.toLowerCase().includes(search)));
        index = 0;
        flipped = false;
        render();
    }
    function render() {
        const card = pool[index];
        const box = document.getElementById('flashcardCard');
        if (!card) {
            box.innerHTML = '<strong>Brak fiszek</strong><p>Zmień filtr albo dodaj własną kartę.</p>';
            return;
        }
        box.innerHTML = flipped ? `<strong>${esc(card.front)}</strong><p>${esc(card.back)}</p>` : `<strong>${esc(card.front)}</strong><p>Kliknij kartę, aby zobaczyć definicję i przykład.</p>`;
        document.getElementById('flashcardMeta').textContent = `${index + 1}/${pool.length} | ${card.qualification || 'Moje'} | następna powtórka: ${progress[cardId(card)]?.due ? new Date(progress[cardId(card)].due).toLocaleString('pl-PL') : 'teraz'}`;
        document.getElementById('flashcardWiki').href = card.wiki || '#';
        document.getElementById('flashcardYoutube').href = card.youtube || '#';
    }
    function rate(level) {
        const card = pool[index];
        if (!card) return;
        const days = level === 'easy' ? 5 : (level === 'medium' ? 2 : 0.25);
        progress[cardId(card)] = { level, due: Date.now() + days * 86400000 };
        localStorage.setItem(progressKey, JSON.stringify(progress));
        index = (index + 1) % Math.max(1, pool.length);
        flipped = false;
        render();
    }
    document.getElementById('flashcardCard').addEventListener('click', () => { flipped = !flipped; render(); });
    document.getElementById('flashcardCard').addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); flipped = !flipped; render(); } });
    document.querySelectorAll('[data-rate]').forEach(btn => btn.addEventListener('click', () => rate(btn.dataset.rate)));
    document.getElementById('flashcardSet').addEventListener('change', rebuild);
    document.getElementById('flashcardSearch').addEventListener('input', rebuild);
    document.getElementById('addCustomCard').addEventListener('click', () => {
        const front = document.getElementById('customFront').value.trim();
        const back = document.getElementById('customBack').value.trim();
        if (!front || !back) return;
        custom.push({ qualification: 'Moje', front, back, wiki: '', youtube: 'https://www.youtube.com/results?search_query=' + encodeURIComponent(front) });
        localStorage.setItem(customKey, JSON.stringify(custom));
        document.getElementById('customFront').value = '';
        document.getElementById('customBack').value = '';
        rebuild();
    });
    rebuild();
});
</script>
</body>
</html>
