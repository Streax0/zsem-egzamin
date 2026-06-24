(function () {
    const config = window.zsemFlashcards || {};
    const dictCards = Array.isArray(config.cards) ? config.cards : [];
    const progressKey = 'zsem.flashcards.progress.v2';
    const wrongKey = 'zsem.flashcards.wrong.v2';

    const byId = (id) => document.getElementById(id);
    const cardBox = byId('flashcardCard');
    if (!cardBox) return;

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
        qualification: String(card && card.qualification ? card.qualification : ''),
        front: String(card && card.front ? card.front : ''),
        back: String(card && card.back ? card.back : ''),
        source: String(card && card.source ? card.source : 'Baza'),
        difficulty: ['easy', 'medium', 'hard'].includes(String(card && card.difficulty ? card.difficulty : ''))
            ? String(card.difficulty)
            : 'medium',
        wiki: String(card && card.wiki ? card.wiki : ''),
        youtube: String(card && card.youtube ? card.youtube : '')
    });

    let progress = safeJson(localStorage.getItem(progressKey), {});
    let wrong = safeJson(localStorage.getItem(wrongKey), { expires: 0, ids: [] });
    let pool = [];
    let index = 0;
    let flipped = false;
    let visibleListCount = 12;
    let difficultyFilter = 'all';
    let studyMode = 'all';

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[ch]));

    const htmlLines = (value) => esc(value).replace(/\n/g, '<br>');

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

    function qualificationProgress() {
        const totals = {};
        const done = {};
        dictCards.map(normalizeCard).filter((card) => card.front && card.back).forEach((card) => {
            const qual = card.qualification || 'Inne';
            totals[qual] = (totals[qual] || 0) + 1;
            const state = progress[cardId(card)];
            if (state && state.level && Number(state.due || 0) > Date.now()) {
                done[qual] = (done[qual] || 0) + 1;
            }
        });

        return Object.keys(totals).sort((a, b) => a.localeCompare(b, 'pl')).slice(0, 5).map((qual) => {
            const count = totals[qual] || 0;
            const mastered = done[qual] || 0;
            const pct = count > 0 ? Math.round((mastered / count) * 100) : 0;
            return `<div class="flashcard-progress-row"><span>${esc(qual)}</span><div class="flashcard-progress-track"><div class="flashcard-progress-fill" style="width:${pct}%"></div></div><strong>${mastered}/${count}</strong></div>`;
        }).join('');
    }

    window.qualificationProgress = qualificationProgress;

    function updateProgress() {
        const target = document.querySelector('[data-flashcard-progress]');
        if (!target) return;
        target.innerHTML = qualificationProgress() || '<div class="small text-muted">Brak postępu dla kwalifikacji.</div>';
    }

    function syncQualificationCards() {
        const selected = byId('flashcardQual')?.value || 'all';
        document.querySelectorAll('[data-flashcard-qual-card]').forEach((button) => {
            button.classList.toggle('active', selected !== 'all' && button.dataset.flashcardQualCard === selected);
        });
    }

    function renderList() {
        const list = byId('flashcardList');
        const loadMore = document.querySelector('[data-flashcard-load-more]');
        const count = byId('flashcardListCount');
        if (!list || !loadMore) return;

        const visible = pool.slice(0, visibleListCount);
        list.innerHTML = visible.length
            ? visible.map((card, idx) => `<button type="button" data-flashcard-list-index="${idx}"><strong>${esc(card.front)}</strong><span>${esc(card.qualification || 'Inne')} | ${esc(card.source)} | ${esc(card.difficulty)}</span></button>`).join('')
            : '<div class="small text-muted">Brak fiszek dla wybranych filtrów.</div>';
        loadMore.hidden = visibleListCount >= pool.length;
        if (count) count.textContent = `${Math.min(visibleListCount, pool.length)}/${pool.length}`;
    }

    let autoplayInterval = null;
    let autoplayActive = false;

    function stopAutoplay() {
        if (!autoplayActive) return;
        autoplayActive = false;
        const playBtn = byId('flashcardPlay');
        if (playBtn) {
            playBtn.innerHTML = '<i class="bi bi-play-fill"></i>';
            playBtn.classList.remove('active');
        }
        if (autoplayInterval) {
            clearInterval(autoplayInterval);
            autoplayInterval = null;
        }
    }

    function startAutoplay() {
        if (autoplayActive) return;
        autoplayActive = true;
        const playBtn = byId('flashcardPlay');
        if (playBtn) {
            playBtn.innerHTML = '<i class="bi bi-pause-fill"></i>';
            playBtn.classList.add('active');
        }
        autoplayInterval = setInterval(() => {
            if (pool.length === 0) return;
            if (!flipped) {
                flipped = true;
                render();
            } else {
                index = (index + 1) % pool.length;
                flipped = false;
                render();
            }
        }, 3000);
    }

    function speakText(text) {
        if (!window.speechSynthesis) return;
        window.speechSynthesis.cancel();
        const cleanText = text.replace(/<[^>]*>/g, '');
        const utterance = new SpeechSynthesisUtterance(cleanText);
        utterance.lang = 'pl-PL';
        window.speechSynthesis.speak(utterance);
    }

    function render() {
        const card = pool[index];
        const frontText = byId('flashcardFrontText');
        const backText = byId('flashcardBackText');
        const cardInner = cardBox.querySelector('.flashcard-card-inner');

        if (!card) {
            cardBox.classList.remove('is-flipped');
            if (frontText) frontText.innerHTML = 'Brak fiszek';
            if (backText) backText.innerHTML = 'Zmień filtry lub wróć po zatwierdzeniu nowych kart.';
            if (cardInner) cardInner.style.display = 'none';

            const counter = byId('flashcardCounter');
            if (counter) counter.textContent = '0 / 0';
            const bar = byId('flashcardProgressBar');
            if (bar) bar.style.width = '0%';
            return;
        }

        if (cardInner) cardInner.style.display = '';

        cardBox.classList.toggle('is-flipped', flipped);
        cardBox.classList.remove('is-leaving-left', 'is-leaving-right', 'is-swipe-left', 'is-swipe-right');
        cardBox.classList.add('is-entering');
        setTimeout(() => cardBox.classList.remove('is-entering'), 250);

        if (frontText) frontText.innerHTML = esc(card.front);
        if (backText) backText.innerHTML = htmlLines(card.back);

        const meta = byId('flashcardMeta');
        if (meta) {
            const until = new Date(wrong.expires).toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' });
            meta.textContent = `${index + 1}/${pool.length} | ${card.source} | ${card.qualification || 'Inne'} | błędne ważne do: ${until}`;
        }

        // Update control panel stats
        const counter = byId('flashcardCounter');
        if (counter) counter.textContent = `${index + 1} / ${pool.length}`;

        const bar = byId('flashcardProgressBar');
        if (bar) {
            const pct = pool.length > 0 ? ((index + 1) / pool.length) * 100 : 0;
            bar.style.width = `${pct}%`;
        }

        const wiki = byId('flashcardWiki');
        const youtube = byId('flashcardYoutube');
        if (wiki) {
            wiki.href = safeHttpUrl(card.wiki);
            wiki.style.display = card.wiki ? '' : 'none';
        }
        if (youtube) {
            youtube.href = safeHttpUrl(card.youtube);
            youtube.style.display = card.youtube ? '' : 'none';
        }
    }

    function rebuild(resetList = true) {
        const set = byId('flashcardSet')?.value || 'all';
        const qual = byId('flashcardQual')?.value || 'all';
        const search = (byId('flashcardSearch')?.value || '').trim().toLowerCase();
        syncWrong();

        const all = dictCards.map(normalizeCard).filter((card) => card.front && card.back);
        pool = all.filter((card) => {
            const id = cardId(card);
            const setOk = set === 'all'
                || (set === 'questions' && card.source === 'Baza pytan')
                || (set === 'questions' && card.source === 'Baza pytań')
                || (set === 'dictionary' && card.source === 'Slownik')
                || (set === 'dictionary' && card.source === 'Słownik')
                || (set === 'wrong' && wrong.ids.includes(id))
                || (set === 'due' && due(card));

            return setOk
                && (qual === 'all' || card.qualification === qual)
                && (difficultyFilter === 'all' || card.difficulty === difficultyFilter)
                && (!search || `${card.front} ${card.back}`.toLowerCase().includes(search));
        });

        if (studyMode === 'mixed') {
            pool = pool.slice().sort(() => Math.random() - 0.5);
        }

        index = 0;
        flipped = false;
        if (resetList) visibleListCount = 12;
        updateProgress();
        syncQualificationCards();
        renderList();
        render();
    }

    function rate(level, direction = '') {
        stopAutoplay();
        const card = pool[index];
        if (!card) return;
        syncWrong();

        const id = cardId(card);
        let state = progress[id] || { reps: 0, efactor: 2.5, interval: 0, due: 0 };

        // Backward compatibility: migrate old states that only had 'level' and 'due'
        if (state.reps === undefined) {
            state.reps = 0;
            state.efactor = 2.5;
            state.interval = 0;
        }

        let quality = 3; // medium
        if (level === 'hard') quality = 1;
        if (level === 'easy') quality = 5;

        if (quality >= 3) {
            if (state.reps === 0) {
                state.interval = 1;
            } else if (state.reps === 1) {
                state.interval = 6;
            } else {
                state.interval = Math.round(state.interval * state.efactor);
            }
            state.reps += 1;
        } else {
            state.reps = 0;
            state.interval = 1;
        }

        state.efactor = state.efactor + (0.1 - (5 - quality) * (0.08 + (5 - quality) * 0.02));
        if (state.efactor < 1.3) state.efactor = 1.3;

        state.due = Date.now() + state.interval * 86400000;
        state.level = level;

        progress[id] = state;
        localStorage.setItem(progressKey, JSON.stringify(progress));

        if (level === 'hard') {
            if (!wrong.ids.includes(id)) wrong.ids.push(id);
        } else {
            wrong.ids = wrong.ids.filter((item) => item !== id);
        }
        wrong.expires = Date.now() + 3 * 3600000;
        localStorage.setItem(wrongKey, JSON.stringify(wrong));

        updateProgress();
        renderList();
        cardBox.classList.add(direction === 'left' || level === 'hard' ? 'is-leaving-left' : 'is-leaving-right');
        setTimeout(() => {
            index = (index + 1) % Math.max(1, pool.length);
            flipped = false;
            render();
            cardBox.style.transition = '';
            cardBox.style.transform = '';
        }, 250);
    }

    // Event Listeners
    cardBox.addEventListener('click', (event) => {
        if (event.target.closest('.btn-tts')) return;
        stopAutoplay();
        flipped = !flipped;
        render();
    });

    cardBox.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            stopAutoplay();
            flipped = !flipped;
            render();
        }
    });

    // Control buttons listeners
    byId('flashcardPrev')?.addEventListener('click', () => {
        stopAutoplay();
        if (pool.length === 0) return;
        index = (index - 1 + pool.length) % pool.length;
        flipped = false;
        render();
    });

    byId('flashcardNext')?.addEventListener('click', () => {
        stopAutoplay();
        if (pool.length === 0) return;
        index = (index + 1) % pool.length;
        flipped = false;
        render();
    });

    byId('flashcardPlay')?.addEventListener('click', () => {
        if (autoplayActive) {
            stopAutoplay();
        } else {
            startAutoplay();
        }
    });

    // Fullscreen API implementation
    const studyShell = byId('flashcardStudyShell');
    byId('flashcardFullscreen')?.addEventListener('click', () => {
        if (!document.fullscreenElement) {
            studyShell?.requestFullscreen().catch((err) => {
                console.error('Error entering fullscreen:', err);
            });
        } else {
            document.exitFullscreen();
        }
    });

    document.addEventListener('fullscreenchange', () => {
        const fsBtn = byId('flashcardFullscreen');
        if (!fsBtn) return;
        if (document.fullscreenElement) {
            fsBtn.innerHTML = '<i class="bi bi-fullscreen-exit"></i>';
            fsBtn.classList.add('active');
        } else {
            fsBtn.innerHTML = '<i class="bi bi-arrows-fullscreen"></i>';
            fsBtn.classList.remove('active');
        }
    });

    // Audio TTS listener triggers
    byId('flashcardTtsFront')?.addEventListener('click', (event) => {
        event.stopPropagation();
        const card = pool[index];
        if (card) speakText(card.front);
    });

    byId('flashcardTtsBack')?.addEventListener('click', (event) => {
        event.stopPropagation();
        const card = pool[index];
        if (card) speakText(card.back);
    });

    document.addEventListener('keydown', (event) => {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName)) return;
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            rate('hard', 'left');
        }
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            rate('easy', 'right');
        }
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            rate('medium', 'right');
        }
        if (event.key === 'ArrowUp' || event.key === ' ') {
            event.preventDefault();
            stopAutoplay();
            flipped = !flipped;
            render();
        }
    });

    document.querySelectorAll('[data-rate]').forEach((button) => {
        button.addEventListener('click', () => rate(button.dataset.rate || 'medium'));
    });

    byId('flashcardSet')?.addEventListener('change', () => rebuild());
    byId('flashcardQual')?.addEventListener('change', () => rebuild());
    byId('flashcardSearch')?.addEventListener('input', () => rebuild());

    document.querySelectorAll('[data-flashcard-qual-card]').forEach((button) => {
        button.addEventListener('click', () => {
            const qual = byId('flashcardQual');
            if (qual) qual.value = button.dataset.flashcardQualCard || 'all';
            rebuild();
        });
    });

    document.querySelectorAll('[data-flashcard-difficulty]').forEach((button) => {
        button.addEventListener('click', () => {
            difficultyFilter = button.dataset.flashcardDifficulty || 'all';
            document.querySelectorAll('[data-flashcard-difficulty]').forEach((item) => item.classList.toggle('active', item === button));
            rebuild();
        });
    });

    document.querySelectorAll('[data-flashcard-study]').forEach((button) => {
        button.addEventListener('click', () => {
            studyMode = button.dataset.flashcardStudy || 'all';
            document.querySelectorAll('[data-flashcard-study]').forEach((item) => item.classList.toggle('active', item === button));
            const set = byId('flashcardSet');
            if (set) set.value = studyMode === 'wrong' ? 'wrong' : 'all';
            rebuild();
        });
    });

    byId('flashcardList')?.addEventListener('click', (event) => {
        const item = event.target.closest('[data-flashcard-list-index]');
        if (!item) return;
        stopAutoplay();
        index = Number(item.dataset.flashcardListIndex) || 0;
        flipped = false;
        render();
    });

    document.querySelector('[data-flashcard-load-more]')?.addEventListener('click', () => {
        visibleListCount += 12;
        renderList();
    });

    let startX = 0;
    let active = false;
    cardBox.addEventListener('pointerdown', (event) => {
        if (event.target.closest('.btn-tts')) return;
        active = true;
        startX = event.clientX;
        cardBox.setPointerCapture(event.pointerId);
        cardBox.style.transition = 'none';
    });
    cardBox.addEventListener('pointermove', (event) => {
        if (!active) return;
        const dx = event.clientX - startX;
        const rotate = dx * 0.05;
        cardBox.style.transform = `translateX(${dx}px) rotate(${rotate}deg)`;
        cardBox.classList.toggle('is-swipe-right', dx > 45);
        cardBox.classList.toggle('is-swipe-left', dx < -45);
    });
    cardBox.addEventListener('pointerup', (event) => {
        if (!active) return;
        active = false;
        const dx = event.clientX - startX;
        cardBox.classList.remove('is-swipe-left', 'is-swipe-right');
        if (dx > 90) {
            rate('easy', 'right');
        } else if (dx < -90) {
            rate('hard', 'left');
        } else {
            cardBox.style.transition = 'transform 0.3s ease';
            cardBox.style.transform = '';
        }
    });
    cardBox.addEventListener('pointercancel', () => {
        active = false;
        cardBox.classList.remove('is-swipe-left', 'is-swipe-right');
        cardBox.style.transition = 'transform 0.3s ease';
        cardBox.style.transform = '';
    });

    rebuild();
}());
