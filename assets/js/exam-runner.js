/**
 * ZSEM Tech - Unified Exam & Test Runner Module
 * Handles exam setup, unranked limits, category selectors, timers,
 * navigation confirmations, and AI Tutor interactions.
 */
(function (window, document) {
    'use strict';

    function modalInstance(id) {
        const el = document.getElementById(id);
        return el && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null;
    }

    function setCookie(name, value, days) {
        let expires = '';
        if (typeof days === 'number') {
            const date = new Date();
            date.setTime(date.getTime() + days * 24 * 60 * 60 * 1000);
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = `${name}=${encodeURIComponent(value)}${expires}; path=/; SameSite=Lax`;
    }

    function markSaved(button, html) {
        if (!button) return;
        button.textContent = 'Zapisano';
        setTimeout(() => { button.innerHTML = html; }, 1600);
    }

    // --- 1. Simulator Answer Selector & Global Confirm Fallback ---
    window.confirmEndTest = function () {
        if (typeof window.confirmFinish === 'function') {
            window.confirmFinish(null);
            return;
        }
        const modal = modalInstance('testConfirmModal');
        if (modal) {
            modal.show();
            return;
        }
        window.submitFinishEarlyForm?.(null);
    };

    window.selectSimAnswer = function (option) {
        document.querySelectorAll('.sim-answer-option').forEach(el => el.classList.remove('selected'));
        if (!option) return;
        option.classList.add('selected');
        const input = document.getElementById('simSelectedAnswer');
        const submit = document.getElementById('simSubmitBtn');
        if (input) input.value = option.dataset.answer || '';
        if (submit) submit.disabled = false;
    };

    // --- 2. Setup Page Controllers ---
    function initSetupPage() {
        const simSetupForm = document.getElementById('examSimulatorSetupForm');
        const standardSetupForm = document.getElementById('premiumSetupForm');
        const countInput = document.getElementById('questionCountInput');

        if (!simSetupForm && !standardSetupForm && !countInput) {
            return;
        }

        // Exam Simulator Category Setup Form
        if (simSetupForm) {
            const simCards = simSetupForm.querySelectorAll('.category-card');
            const simInput = simSetupForm.querySelector('#categoryInput');

            function updateSimCategoryInput() {
                const selected = [];
                simCards.forEach(card => {
                    if (card.classList.contains('selected')) {
                        selected.push(card.dataset.category);
                    }
                });
                if (simInput) {
                    simInput.value = selected.join(',');
                }
            }

            simCards.forEach(card => {
                card.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    card.classList.toggle('selected');
                    updateSimCategoryInput();
                });
            });

            document.getElementById('simSelectAllCats')?.addEventListener('click', () => {
                simCards.forEach(card => card.classList.add('selected'));
                updateSimCategoryInput();
            });

            document.getElementById('simDeselectAllCats')?.addEventListener('click', () => {
                simCards.forEach(card => card.classList.remove('selected'));
                updateSimCategoryInput();
            });

            simSetupForm.addEventListener('submit', function (e) {
                updateSimCategoryInput();
                if (!simInput || !simInput.value.trim()) {
                    e.preventDefault();
                    if (window.appNotice) {
                        window.appNotice('Wybierz co najmniej jedną kwalifikację / kategorię egzaminu.', 'warning');
                    }
                }
            });
        }

        // Unranked usage check
        const unrankedInfo = document.getElementById('unrankedInfo');
        const unrankedSw = document.getElementById('unrankedSwitch');
        if (unrankedInfo) {
            fetch('ajax/check_unranked.php')
                .then(r => r.json())
                .then(data => {
                    const remaining = 2 - (data.used || 0);
                    if (remaining <= 0) {
                        unrankedInfo.textContent = 'Wykorzystano limit (2/2 dzisiaj)';
                        if (unrankedSw) unrankedSw.disabled = true;
                    } else {
                        unrankedInfo.textContent = `Pozostało ${remaining} z 2 użyć`;
                    }
                    const syncRankingInfo = () => {
                        if (!countInput || !unrankedSw) return;
                        if (remaining <= 0) {
                            unrankedInfo.textContent = 'Wykorzystano limit (2/2 dzisiaj)';
                            unrankedSw.checked = false;
                            unrankedSw.disabled = true;
                        } else {
                            unrankedInfo.textContent = `Pozostało ${remaining} z 2 użyć`;
                            unrankedSw.disabled = false;
                        }
                    };
                    countInput?.addEventListener('input', syncRankingInfo);
                    syncRankingInfo();
                })
                .catch(() => {
                    if (unrankedInfo) unrankedInfo.textContent = 'Dostępne 2 razy dziennie';
                });
        }

        // Standard Setup Multi-Category Selector (ONLY inside #premiumSetupForm)
        if (standardSetupForm) {
            const categoryCards = standardSetupForm.querySelectorAll('.category-card');
            const categoryInput = standardSetupForm.querySelector('#categoryInput');

            if (categoryCards.length && categoryInput) {
                function updateCategoryInput() {
                    const selected = [];
                    categoryCards.forEach(card => {
                        if (card.classList.contains('selected')) {
                            selected.push(card.dataset.category);
                        }
                    });
                    categoryInput.value = selected.join(',');
                }

                categoryCards.forEach(card => {
                    card.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        card.classList.toggle('selected');
                        updateCategoryInput();
                    });
                });

                document.getElementById('selectAllCats')?.addEventListener('click', () => {
                    categoryCards.forEach(card => card.classList.add('selected'));
                    updateCategoryInput();
                });

                document.getElementById('deselectAllCats')?.addEventListener('click', () => {
                    categoryCards.forEach(card => card.classList.remove('selected'));
                    updateCategoryInput();
                });

                document.getElementById('saveDefaultCategoryBtn')?.addEventListener('click', () => {
                    const selected = [];
                    categoryCards.forEach(card => {
                        if (card.classList.contains('selected')) {
                            selected.push(card.dataset.category);
                        }
                    });
                    if (!selected.length) {
                        if (window.appNotice) window.appNotice('Wybierz przynajmniej jedną kategorię, aby zapisać domyślną.', 'warning');
                        return;
                    }
                    setCookie('default_test_categories', selected.join(','), 365);
                    const btn = document.getElementById('saveDefaultCategoryBtn');
                    if (btn) {
                        btn.textContent = 'Zapisano';
                        setTimeout(() => { btn.innerHTML = '<i class="bi bi-bookmark-star me-1"></i>Zapisz jako domyślną'; }, 1600);
                    }
                });
            }
        }

        document.getElementById('saveDefaultCountBtn')?.addEventListener('click', () => {
            if (!countInput) return;
            const countValue = Number(countInput.value || 0);
            if (countValue < 1) {
                if (window.appNotice) window.appNotice('Wprowadź poprawną liczbę pytań, aby zapisać domyślną wartość.', 'warning');
                return;
            }
            setCookie('default_test_count', countValue, 365);
            const btn = document.getElementById('saveDefaultCountBtn');
            if (btn) {
                btn.textContent = 'Zapisano';
                setTimeout(() => { btn.innerHTML = '<i class="bi bi-bookmark-star me-1"></i>Domyślna liczba'; }, 1600);
            }
        });

        // Difficulty Segmented Control
        const difficultyBtns = document.querySelectorAll('.difficulty-btn');
        const difficultyInput = document.getElementById('difficultyInput');
        difficultyBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                difficultyBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                if (difficultyInput) difficultyInput.value = btn.dataset.value;
            });
        });
        document.getElementById('saveDefaultDifficultyBtn')?.addEventListener('click', () => {
            setCookie('default_test_difficulty', difficultyInput?.value || 'all', 365);
            markSaved(document.getElementById('saveDefaultDifficultyBtn'), '<i class="bi bi-bookmark-star me-1"></i>Domyślna trudność');
        });

        // Order Segmented Control
        const orderBtns = document.querySelectorAll('.order-btn');
        const orderInput = document.getElementById('orderInput');
        orderBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                orderBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                if (orderInput) orderInput.value = btn.dataset.value;
            });
        });

        // Scope Cards
        const scopeCards = document.querySelectorAll('.scope-card');
        const scopeInput = document.getElementById('scopeInput');
        scopeCards.forEach(card => {
            card.addEventListener('click', () => {
                scopeCards.forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                if (scopeInput) scopeInput.value = card.dataset.value;
            });
        });
        document.getElementById('saveDefaultScopeBtn')?.addEventListener('click', () => {
            setCookie('default_test_scope', scopeInput?.value || 'all', 365);
            markSaved(document.getElementById('saveDefaultScopeBtn'), '<i class="bi bi-bookmark-star me-1"></i>Domyślny zakres');
        });

        // Count Badges
        const countBadges = document.querySelectorAll('.count-badge-btn');
        countBadges.forEach(badge => {
            badge.addEventListener('click', () => {
                countBadges.forEach(b => b.classList.remove('active'));
                badge.classList.add('active');
                if (countInput) {
                    countInput.value = badge.dataset.value;
                    countInput.dispatchEvent(new Event('input'));
                }
            });
        });
        countInput?.addEventListener('input', () => {
            const val = Number(countInput.value || 0);
            countBadges.forEach(b => {
                if (Number(b.dataset.value) === val) {
                    b.classList.add('active');
                } else {
                    b.classList.remove('active');
                }
            });
        });

        // Presets & Timing Controls
        const presetBtns = document.querySelectorAll('.preset-mode-btn');
        const presetInput = document.getElementById('presetInput');
        const timeLimitInput = document.getElementById('timeLimitInput');
        const timeLimitValue = document.getElementById('timeLimitValue');
        const timeOptionBtns = document.querySelectorAll('.time-option-btn');
        const timeOptionInput = document.getElementById('timeOptionInput');
        const timeSliderPanel = document.querySelector('.time-slider-panel:not(#timePerQuestionPanel)');
        const timePerQuestionPanel = document.getElementById('timePerQuestionPanel');
        const timePerQuestionInput = document.getElementById('timePerQuestionInput');
        const timePerQuestionValue = document.getElementById('timePerQuestionValue');
        const perQuestionPresetPanel = document.getElementById('perQuestionPresetPanel');
        const perQuestionPresetInfo = document.getElementById('perQuestionPresetInfo');

        function updatePerQuestionPresetInfo() {
            const opt = timeOptionInput?.value || '';
            const count = Math.max(1, Number(countInput?.value || 0));
            if (!perQuestionPresetPanel || !perQuestionPresetInfo) return;
            if (opt === '30s' || opt === '60s') {
                const sec = opt === '30s' ? 30 : 60;
                const totalSec = count * sec;
                const mins = Math.ceil(totalSec / 60);
                perQuestionPresetInfo.textContent = `${count} pytań × ${sec}s = ok. ${mins} min łącznie`;
                perQuestionPresetPanel.classList.add('open');
            } else {
                perQuestionPresetPanel.classList.remove('open');
            }
        }

        presetBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                presetBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                if (presetInput) presetInput.value = btn.dataset.preset || '';
                if (countInput) {
                    countInput.value = btn.dataset.count || countInput.value;
                    countInput.dispatchEvent(new Event('input'));
                }
                if (timeLimitInput) {
                    timeLimitInput.value = btn.dataset.time || timeLimitInput.value;
                    timeLimitInput.dispatchEvent(new Event('input'));
                }
                const customBtn = document.querySelector('.time-option-btn[data-value="custom"]');
                customBtn?.click();
            });
        });

        timeOptionBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                timeOptionBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                if (timeOptionInput) timeOptionInput.value = btn.dataset.value;
                if (btn.dataset.value === 'custom') {
                    timeSliderPanel?.classList.add('open');
                    timePerQuestionPanel?.classList.remove('open');
                } else if (btn.dataset.value === 'per_question_custom') {
                    timeSliderPanel?.classList.remove('open');
                    timePerQuestionPanel?.classList.add('open');
                } else {
                    timeSliderPanel?.classList.remove('open');
                    timePerQuestionPanel?.classList.remove('open');
                }
                updatePerQuestionPresetInfo();
            });
        });

        timeLimitInput?.addEventListener('input', () => {
            if (timeLimitValue) timeLimitValue.textContent = timeLimitInput.value;
        });
        timePerQuestionInput?.addEventListener('input', () => {
            if (timePerQuestionValue) timePerQuestionValue.textContent = timePerQuestionInput.value;
        });

        document.getElementById('saveDefaultTimeBtn')?.addEventListener('click', () => {
            setCookie('default_test_time_option', timeOptionInput?.value || 'custom', 365);
            setCookie('default_test_time', timeLimitInput?.value || '60', 365);
            setCookie('default_test_time_per_question', timePerQuestionInput?.value || '60', 365);
            markSaved(document.getElementById('saveDefaultTimeBtn'), '<i class="bi bi-bookmark-star me-1"></i>Domyślny czas');
        });

        countInput?.addEventListener('input', updatePerQuestionPresetInfo);
        updatePerQuestionPresetInfo();
    }

    // --- 3. Active Test / Answering Phase Runner ---
    function initActiveTest() {
        const config = window.__EXAM_CONFIG__ || {};
        const isAnsweringOrReview = (config.phase === 'answering' || config.phase === 'review' || config.phase === 'reviewing');
        window.shouldConfirmNavigation = isAnsweringOrReview;

        let pendingFinishForm = null;
        let confirmModal = null;
        let timeExpiredModal = null;

        window.allowQuizNavigation = function () {
            window.shouldConfirmNavigation = false;
        };

        window.submitFinishEarlyForm = function (form) {
            window.shouldConfirmNavigation = false;
            if (form) {
                form.submit();
                return;
            }
            const f = document.createElement('form');
            f.method = 'POST';
            const csrf = config.csrfToken || '';

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrf;
            f.appendChild(csrfInput);

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'finish_early';
            f.appendChild(actionInput);

            document.body.appendChild(f);
            f.submit();
        };

        window.confirmFinish = function (form) {
            pendingFinishForm = form || null;
            confirmModal = confirmModal || modalInstance('testConfirmModal');
            if (confirmModal) {
                confirmModal.show();
                return false;
            }
            window.submitFinishEarlyForm(pendingFinishForm);
            return false;
        };

        window.confirmEndTest = function () {
            pendingFinishForm = null;
            window.confirmFinish(null);
        };

        document.getElementById('testEndBtn')?.addEventListener('click', function (e) {
            e.preventDefault();
            window.confirmEndTest();
        });

        document.getElementById('testConfirmSubmit')?.addEventListener('click', function () {
            if (confirmModal) {
                try { confirmModal.hide(); } catch (e) {}
            }
            window.submitFinishEarlyForm(pendingFinishForm);
        });

        document.querySelectorAll('form, button[name="submit_answer"], button[name="jump_to"], .exam-task-btn, .nav-question-btn').forEach(el => {
            el.addEventListener('click', () => { window.shouldConfirmNavigation = false; });
            if (el.tagName === 'FORM') {
                el.addEventListener('submit', () => { window.shouldConfirmNavigation = false; });
            }
        });

        window.addEventListener('beforeunload', function (e) {
            if (window.shouldConfirmNavigation) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Total Exam Timer
        if (config.timeLimit !== null && config.timeLimit !== undefined) {
            let timeLeft = Number(config.timeLimit);
            let timerExpired = false;
            let totalTimerInterval = null;

            function updateTimer() {
                if (timerExpired) return;
                const timerNodes = document.querySelectorAll('#timer, .sim-timer');
                if (!timerNodes.length) return;
                const m = String(Math.floor(timeLeft / 60)).padStart(2, '0');
                const s = String(timeLeft % 60).padStart(2, '0');
                timerNodes.forEach(el => { el.textContent = `${m}:${s}`; });
                const totalChip = document.getElementById('totalTimerChip');
                if (timeLeft <= 300) {
                    totalChip?.classList.add('timer-warning');
                } else {
                    totalChip?.classList.remove('timer-warning');
                }
                if (timeLeft <= 0) {
                    timerExpired = true;
                    if (totalTimerInterval) clearInterval(totalTimerInterval);
                    window.shouldConfirmNavigation = false;
                    timeExpiredModal = timeExpiredModal || modalInstance('testTimeExpiredModal');
                    if (timeExpiredModal) timeExpiredModal.show();
                    setTimeout(() => window.submitFinishEarlyForm(null), 900);
                    return;
                }
                timeLeft--;
            }
            updateTimer();
            totalTimerInterval = setInterval(updateTimer, 1000);
        }

        // Per-Question Timer
        if (config.perQuestionLimit && Number(config.perQuestionLimit) > 0) {
            const questionTimeLimit = Number(config.perQuestionLimit);
            let questionTimeLeft = Number(config.questionTimeLeft || questionTimeLimit);
            const questionTimerEl = document.getElementById('questionTimer');
            const questionTimerChip = document.getElementById('questionTimerChip');
            let questionTimerExpired = false;
            let questionTimerInterval = null;

            function formatTimer(seconds) {
                const m = String(Math.floor(seconds / 60)).padStart(2, '0');
                const s = String(seconds % 60).padStart(2, '0');
                return `${m}:${s}`;
            }

            function updateQuestionTimer() {
                if (questionTimerExpired || !questionTimerEl) return;
                questionTimerEl.textContent = formatTimer(questionTimeLeft);
                if (questionTimeLeft <= 10) {
                    questionTimerChip?.classList.add('timer-warning');
                } else {
                    questionTimerChip?.classList.remove('timer-warning');
                }
                if (questionTimeLeft <= 0) {
                    questionTimerExpired = true;
                    if (questionTimerInterval) clearInterval(questionTimerInterval);
                    if (window.QuizEngine && typeof window.QuizEngine.submitAnswer === 'function') {
                        window.QuizEngine.submitAnswer({ force: true, reason: 'timeout' });
                    }
                    return;
                }
                questionTimeLeft--;
            }

            window.resetQuestionTimer = function (seconds) {
                questionTimeLeft = Number(seconds) > 0 ? Number(seconds) : questionTimeLimit;
                questionTimerExpired = false;
                if (questionTimerEl) {
                    questionTimerEl.textContent = formatTimer(questionTimeLeft);
                }
                questionTimerChip?.classList.remove('timer-warning');
                if (questionTimerInterval) clearInterval(questionTimerInterval);
                questionTimerInterval = setInterval(updateQuestionTimer, 1000);
            };

            window.pauseQuestionTimer = function () {
                if (questionTimerInterval) {
                    clearInterval(questionTimerInterval);
                    questionTimerInterval = null;
                }
            };

            updateQuestionTimer();
            questionTimerInterval = setInterval(updateQuestionTimer, 1000);
        }

        // Socratic Hint Handler (AI Tutor)
        document.querySelectorAll('.btn-ai-tutor').forEach(btn => {
            btn.addEventListener('click', async () => {
                const qId = btn.dataset.questionId || '0';
                const modalEl = document.getElementById('aiTutorModal');
                const modalBody = document.getElementById('aiTutorModalBody');
                const tutorModal = modalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
                if (tutorModal) tutorModal.show();

                if (modalBody) {
                    modalBody.innerHTML = `
                        <div class="text-center py-4">
                            <div class="spinner-border text-warning" role="status"></div>
                            <p class="text-muted mt-2">Generowanie sokratejskiej wskazówki myślowej...</p>
                        </div>
                    `;
                }

                try {
                    const formData = new FormData();
                    formData.append('question_id', qId);
                    const res = await fetch('ajax/ai_tutor_explain.php', { method: 'POST', body: formData, credentials: 'same-origin' });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Nie udało się pobrać wskazówki.');

                    if (modalBody) {
                        const safeTopic = (window.escapeHtml ? window.escapeHtml(data.topic || data.category || '') : (data.topic || data.category || ''));
                        const safeGuiding = (window.escapeHtml ? window.escapeHtml(data.guiding_question || '') : (data.guiding_question || ''));
                        const safeConcept = (window.escapeHtml ? window.escapeHtml(data.concept_refresher || '') : (data.concept_refresher || ''));
                        const safeTrap = (window.escapeHtml ? window.escapeHtml(data.trap_to_avoid || '') : (data.trap_to_avoid || ''));

                        modalBody.innerHTML = `
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 fs-6"><i class="bi bi-tag-fill me-1"></i>Temat: ${safeTopic}</span>
                            </div>
                            <div class="card border-primary-subtle bg-primary bg-opacity-10 p-3 mb-3 rounded-3">
                                <div class="fw-bold text-primary mb-1"><i class="bi bi-compass me-2"></i>Pytanie naprowadzające:</div>
                                <p class="mb-0 text-dark">${safeGuiding}</p>
                            </div>
                            <div class="card border-info-subtle bg-body-tertiary p-3 mb-3 rounded-3">
                                <div class="fw-bold text-info-emphasis mb-1"><i class="bi bi-book-half me-2"></i>Przypomnienie teorii:</div>
                                <p class="mb-0 small text-muted">${safeConcept}</p>
                            </div>
                            <div class="alert alert-warning border-0 small mb-0">
                                <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i><strong>Pułapka egzaminacyjna:</strong> ${safeTrap}
                            </div>
                        `;
                    }
                } catch (err) {
                    if (modalBody) {
                        const errMsg = (window.escapeHtml ? window.escapeHtml(err.message) : err.message);
                        modalBody.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Błąd: ${errMsg}</div>`;
                    }
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initSetupPage();
            initActiveTest();
        });
    } else {
        initSetupPage();
        initActiveTest();
    }

})(window, document);
