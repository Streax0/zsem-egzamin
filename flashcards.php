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
            'source' => 'Słownik',
            'wiki' => (string)($term['link'] ?? ''),
            'youtube' => 'https://www.youtube.com/results?search_query=' . rawurlencode($front . ' informatyka')
        ];
    }
}

foreach (loadQuestions($pdo) as $question) {
    $front = trim((string)($question['question_text'] ?? ''));
    $correct = strtoupper(trim((string)($question['correct_answer'] ?? '')));
    if ($front === '' || $correct === '') continue;
    $correctText = answerOptionText($question, $correct);
    $cards[] = [
        'qualification' => (string)($question['category'] ?? 'Testy'),
        'front' => $front,
        'back' => "Poprawna odpowiedź: {$correct}" . ($correctText !== '' ? " - {$correctText}" : '') . "\n\n" . buildQuestionExplanation($question),
        'source' => 'Baza pytań',
        'wiki' => '',
        'youtube' => 'https://www.youtube.com/results?search_query=' . rawurlencode($front . ' egzamin zawodowy informatyka')
    ];
}

$qualifications = array_values(array_unique(array_filter(array_map(static fn($card) => (string)($card['qualification'] ?? ''), $cards))));
sort($qualifications, SORT_NATURAL | SORT_FLAG_CASE);
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
        .flashcard-deck { perspective: 1200px; }
        .flashcard-card { min-height: 390px; border: 1px solid rgba(148,163,184,.24); border-radius: 8px; background: #fff; box-shadow: 0 18px 40px rgba(15,23,42,.08); display: grid; align-content: center; padding: clamp(1.25rem, 4vw, 2.4rem); cursor: grab; touch-action: pan-y; transition: transform .18s ease, box-shadow .18s ease, opacity .18s ease; }
        .flashcard-card:active { cursor: grabbing; }
        .flashcard-card.is-swipe-right { transform: translateX(90px) rotate(5deg); border-color: rgba(34,197,94,.45); }
        .flashcard-card.is-swipe-left { transform: translateX(-90px) rotate(-5deg); border-color: rgba(239,68,68,.45); }
        .flashcard-card.is-entering { animation: flashcardIn .22s ease-out; }
        .flashcard-card.is-leaving-right { animation: flashcardRight .24s ease-in forwards; }
        .flashcard-card.is-leaving-left { animation: flashcardLeft .24s ease-in forwards; }
        @keyframes flashcardIn { from { opacity: 0; transform: translateY(18px) scale(.98); } to { opacity: 1; transform: none; } }
        @keyframes flashcardRight { to { opacity: 0; transform: translateX(220px) rotate(9deg); } }
        @keyframes flashcardLeft { to { opacity: 0; transform: translateX(-220px) rotate(-9deg); } }
        .flashcard-card strong { display: block; font-size: clamp(2rem, 5vw, 3.4rem); line-height: 1.05; color: #0f172a; }
        .flashcard-card p { margin: 1rem 0 0; color: #334155; white-space: pre-line; font-size: 1.02rem; }
        .flashcard-tools, .flashcard-side { border: 1px solid rgba(148,163,184,.24); border-radius: 8px; background: #fff; padding: 1rem; box-shadow: 0 12px 30px rgba(15,23,42,.06); }
        .flashcard-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: .65rem; }
        .flashcard-actions button { min-height: 48px; font-weight: 800; }
        .flashcard-hint-row { display: flex; justify-content: space-between; gap: .75rem; color: #64748b; font-size: .82rem; font-weight: 800; margin-top: .8rem; }
        .flashcard-hint-row span:first-child { color: #dc2626; }
        .flashcard-hint-row span:last-child { color: #16a34a; }
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
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Zestaw</label>
                                    <select id="flashcardSet" class="form-select">
                                        <option value="all">Wszystkie</option>
                                        <option value="questions">Baza pytań</option>
                                        <option value="dictionary">Słownik</option>
                                        <option value="wrong">Tylko błędne z 3h</option>
                                        <option value="due">Do powtórki teraz</option>
                                        <option value="custom">Moje fiszki</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Kwalifikacja</label>
                                    <select id="flashcardQual" class="form-select">
                                        <option value="all">Wszystkie</option>
                                        <?php foreach ($qualifications as $qual): ?>
                                            <option value="<?php echo htmlspecialchars($qual); ?>"><?php echo htmlspecialchars($qual); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Szukaj</label>
                                    <input id="flashcardSearch" class="form-control" placeholder="Adres IP, DNS, BIOS...">
                                </div>
                            </div>
                        </div>
                        <div class="flashcard-deck">
                            <article id="flashcardCard" class="flashcard-card" tabindex="0" role="button" aria-live="polite"></article>
                        </div>
                        <div class="flashcard-hint-row" aria-hidden="true">
                            <span><i class="bi bi-arrow-left"></i> przesuń w lewo = źle</span>
                            <span>dobrze = przesuń w prawo <i class="bi bi-arrow-right"></i></span>
                        </div>
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
    const dictCards = <?php echo json_encode($cards, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const progressKey = 'zsem.flashcards.progress.v1';
    const customKey = 'zsem.flashcards.custom.v1';
    const wrongKey = 'zsem.flashcards.wrong.v1';
    const safeJson = (value, fallback) => {
        try {
            const parsed = JSON.parse(value || '');
            if (Array.isArray(fallback)) return Array.isArray(parsed) ? parsed : fallback;
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : fallback;
        } catch (_) {
            return fallback;
        }
    };
    const normalizeCard = (card) => ({
        qualification: String(card?.qualification || ''),
        front: String(card?.front || ''),
        back: String(card?.back || ''),
        source: String(card?.source || 'Moje'),
        wiki: String(card?.wiki || ''),
        youtube: String(card?.youtube || '')
    });
    let progress = safeJson(localStorage.getItem(progressKey), {});
    let custom = safeJson(localStorage.getItem(customKey), []).map(normalizeCard).filter(card => card.front && card.back);
    let wrong = safeJson(localStorage.getItem(wrongKey), { expires: 0, ids: [] });
    let pool = [];
    let index = 0;
    let flipped = false;
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]));
    const safeHttpUrl = (value) => {
        try {
            const url = new URL(String(value || ''), window.location.href);
            return ['http:', 'https:'].includes(url.protocol) ? url.href : '#';
        } catch (_) {
            return '#';
        }
    };
    const cardId = (card) => `${card.qualification}:${card.front}`;
    const due = (card) => (progress[cardId(card)]?.due || 0) <= Date.now();
    const syncWrong = () => {
        if (!wrong || !Array.isArray(wrong.ids) || Number(wrong.expires || 0) <= Date.now()) {
            wrong = { expires: Date.now() + 3 * 3600000, ids: [] };
            localStorage.setItem(wrongKey, JSON.stringify(wrong));
        }
    };
    function rebuild() {
        const set = document.getElementById('flashcardSet').value;
        const qual = document.getElementById('flashcardQual').value;
        const search = document.getElementById('flashcardSearch').value.trim().toLowerCase();
        syncWrong();
        const all = (set === 'custom' ? custom : dictCards.concat(custom)).map(normalizeCard).filter(card => card.front && card.back);
        pool = all.filter(card => {
            const id = cardId(card);
            const setOk =
                set === 'all' ||
                (set === 'questions' && card.source === 'Baza pytań') ||
                (set === 'dictionary' && card.source === 'Słownik') ||
                (set === 'wrong' && wrong.ids.includes(id)) ||
                (set === 'due' && due(card)) ||
                (set === 'custom' && card.source === 'Moje');
            return setOk
                && (qual === 'all' || card.qualification === qual)
                && (!search || `${card.front} ${card.back}`.toLowerCase().includes(search));
        });
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
        box.classList.remove('is-leaving-left', 'is-leaving-right', 'is-swipe-left', 'is-swipe-right');
        box.classList.add('is-entering');
        setTimeout(() => box.classList.remove('is-entering'), 240);
        box.innerHTML = flipped ? `<strong>${esc(card.front)}</strong><p>${esc(card.back)}</p>` : `<strong>${esc(card.front)}</strong><p>Kliknij kartę, aby zobaczyć definicję i przykład.</p>`;
        document.getElementById('flashcardMeta').textContent = `${index + 1}/${pool.length} | ${card.source} | ${card.qualification || 'Moje'} | błędne ważne do: ${new Date(wrong.expires).toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' })}`;
        document.getElementById('flashcardWiki').href = safeHttpUrl(card.wiki);
        document.getElementById('flashcardYoutube').href = safeHttpUrl(card.youtube);
    }
    function rate(level, direction = '') {
        const card = pool[index];
        if (!card) return;
        syncWrong();
        const id = cardId(card);
        const days = level === 'easy' ? 5 : (level === 'medium' ? 2 : 0.25);
        progress[id] = { level, due: Date.now() + days * 86400000 };
        localStorage.setItem(progressKey, JSON.stringify(progress));
        if (level === 'hard') {
            if (!wrong.ids.includes(id)) wrong.ids.push(id);
        } else {
            wrong.ids = wrong.ids.filter(item => item !== id);
        }
        wrong.expires = Date.now() + 3 * 3600000;
        localStorage.setItem(wrongKey, JSON.stringify(wrong));
        const box = document.getElementById('flashcardCard');
        box.classList.add(direction === 'left' || level === 'hard' ? 'is-leaving-left' : 'is-leaving-right');
        setTimeout(() => {
            index = (index + 1) % Math.max(1, pool.length);
            flipped = false;
            render();
        }, 210);
    }
    document.getElementById('flashcardCard').addEventListener('click', () => { flipped = !flipped; render(); });
    document.getElementById('flashcardCard').addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); flipped = !flipped; render(); } });
    document.querySelectorAll('[data-rate]').forEach(btn => btn.addEventListener('click', () => rate(btn.dataset.rate)));
    document.getElementById('flashcardSet').addEventListener('change', rebuild);
    document.getElementById('flashcardQual').addEventListener('change', rebuild);
    document.getElementById('flashcardSearch').addEventListener('input', rebuild);
    document.getElementById('addCustomCard').addEventListener('click', () => {
        const front = document.getElementById('customFront').value.trim();
        const back = document.getElementById('customBack').value.trim();
        if (!front || !back) return;
        custom.push({ qualification: 'Moje', front, back, source: 'Moje', wiki: '', youtube: 'https://www.youtube.com/results?search_query=' + encodeURIComponent(front) });
        localStorage.setItem(customKey, JSON.stringify(custom));
        document.getElementById('customFront').value = '';
        document.getElementById('customBack').value = '';
        rebuild();
    });
    (() => {
        const box = document.getElementById('flashcardCard');
        let startX = 0;
        let active = false;
        box.addEventListener('pointerdown', (event) => {
            active = true;
            startX = event.clientX;
            box.setPointerCapture(event.pointerId);
        });
        box.addEventListener('pointermove', (event) => {
            if (!active) return;
            const dx = event.clientX - startX;
            box.classList.toggle('is-swipe-right', dx > 45);
            box.classList.toggle('is-swipe-left', dx < -45);
        });
        const finish = (event) => {
            if (!active) return;
            active = false;
            const dx = event.clientX - startX;
            box.classList.remove('is-swipe-left', 'is-swipe-right');
            if (dx > 90) rate('easy', 'right');
            if (dx < -90) rate('hard', 'left');
        };
        box.addEventListener('pointerup', finish);
        box.addEventListener('pointercancel', () => {
            active = false;
            box.classList.remove('is-swipe-left', 'is-swipe-right');
        });
    })();
    rebuild();
});
</script>
</body>
</html>
