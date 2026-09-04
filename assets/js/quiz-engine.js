/**
 * quiz-engine.js - Nowoczesny silnik testów oparty na AJAX
 */
const QuizEngine = {
    state: {
        isBusy: false,
        timer: null
    },

    init() {
        window.selectQuizOption = (el) => this.selectOption(el);
        this.setupEventListeners();
        this.setupOfflineListeners();
    },

    setupOfflineListeners() {
        const updateNetworkStatus = () => {
            const offlineNotice = document.getElementById('offlineNotice');
            const isOnline = navigator.onLine;
            
            if (offlineNotice) {
                if (isOnline) {
                    offlineNotice.classList.add('d-none');
                } else {
                    offlineNotice.classList.remove('d-none');
                }
            }

            const submitBtn = document.getElementById('submitBtn');
            const checkBtn = document.getElementById('checkAnswerBtn');
            const simBtn = document.getElementById('simSubmitBtn');
            
            if (!isOnline) {
                if (submitBtn) submitBtn.disabled = true;
                if (checkBtn) checkBtn.disabled = true;
                if (simBtn) simBtn.disabled = true;
                this.lockOptions(true);
            } else {
                const selectedInput = document.getElementById('selectedAnswer');
                if (submitBtn && selectedInput) {
                    submitBtn.disabled = !selectedInput.value;
                }
                if (simBtn) {
                    const simSelected = document.getElementById('simSelectedAnswer');
                    simBtn.disabled = !simSelected?.value;
                }
                this.lockOptions(false);
                this.syncAnswerCheckControls();
            }
        };

        window.addEventListener('online', updateNetworkStatus);
        window.addEventListener('offline', updateNetworkStatus);
        updateNetworkStatus();
    },

    getCsrfToken(scope = document) {
        const scopedToken = scope.querySelector?.('input[name="csrf_token"]');
        if (scopedToken?.value) return scopedToken.value;

        const quizToken = document.querySelector('#quizForm input[name="csrf_token"]');
        if (quizToken?.value) return quizToken.value;

        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    },

    async postQuizAction(formData) {
        if (window.AppApi?.postForm) {
            return window.AppApi.postForm('ajax/quiz_action.php', formData);
        }
        const response = await fetch('ajax/quiz_action.php', {
            method: 'POST',
            body: formData
        });
        return response.json();
    },

    setupEventListeners() {
        // Intercept form submission
        const quizForm = document.getElementById('quizForm');
        if (quizForm) {
            quizForm.addEventListener('submit', (e) => this.handleFormSubmit(e));
            quizForm.addEventListener('click', (e) => {
                const checkBtn = e.target.closest('#checkAnswerBtn');
                if (checkBtn && !checkBtn.disabled && !this.state.isBusy) {
                    e.preventDefault();
                    this.checkAnswer();
                }
            });
        }

        // Delegate click for .quiz-option on answersContainer
        const answersContainer = document.getElementById('answersContainer');
        if (answersContainer) {
            answersContainer.addEventListener('click', (e) => {
                const opt = e.target.closest('.quiz-option');
                if (opt && answersContainer.contains(opt)) {
                    this.selectOption(opt);
                }
            });
        }

        // Flag question button
        const flagBtn = document.getElementById('btnFlagQuestion');
        if (flagBtn) {
            flagBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleFlagQuestion(flagBtn);
            });
        }

        // Global keydown for shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
    },

    handleFormSubmit(e) {
        if (e.defaultPrevented || this.state.isBusy) {
            e.preventDefault();
            return false;
        }
        const submitter = e.submitter;
        if (submitter?.name === 'action' && submitter.value === 'check_answer') {
            e.preventDefault();
            this.checkAnswer();
            return false;
        }
        if (submitter?.name === 'action' && submitter.value !== 'submit_answer') {
            window.allowQuizNavigation?.();
            return true;
        }
        e.preventDefault();
        this.submitAnswer();
        return false;
    },

    async submitAnswer(options = {}) {
        const force = options.force === true;
        if (this.state.isBusy) return;
        
        const selectedInput = document.getElementById('selectedAnswer');
        const questionIdInput = document.querySelector('input[name="question_id"]');
        const quizForm = document.getElementById('quizForm');
        const csrfToken = this.getCsrfToken(quizForm || document);

        if (!selectedInput.value && !force) {
            this.notify('Proszę wybrać odpowiedź.', 'warning');
            return;
        }
        if (!csrfToken) {
            this.notify('Błąd bezpieczeństwa: odśwież stronę i spróbuj ponownie.', 'danger');
            return;
        }

        this.setBusy(true);
        this.lockOptions(true);

        if (force && options.reason === 'timeout') {
            this.notify('Czas na pytanie minął.', 'warning');
        }
        
        const formData = new FormData();
        formData.append('action', 'submit_answer');
        formData.append('question_id', questionIdInput.value);
        formData.append('answer', selectedInput.value);
        formData.append('csrf_token', csrfToken);

        let keepOptionsLocked = false;
        try {
            const data = await this.postQuizAction(formData);

            if (data.success) {
                if (data.finished) {
                    window.allowQuizNavigation?.();
                    window.location.href = data.redirect;
                } else if (data.next_question) {
                    this.updateProgressPanel(data);
                    this.renderQuestion(data.question, data.current, data.total, data.saved_answer, data);
                    if (typeof window.resetQuestionTimer === 'function') {
                        window.resetQuestionTimer(data.question_time_limit || data.question_time_left);
                    }
                } else if (data.phase === 'review') {
                    this.updateProgressPanel(data);
                    this.renderReview(data.result);
                    keepOptionsLocked = true;
                }
            } else {
                this.notify('Błąd: ' + (data.error || 'Nieznany błąd'), 'danger');
                this.updateProgressPanel(data);
                if (data.error === 'No active test' || data.error === 'Invalid CSRF token') {
                    window.allowQuizNavigation?.();
                    window.location.reload();
                }
            }
        } catch (error) {
            console.error('Quiz Error:', error);
            this.notify('Wystąpił błąd połączenia.', 'danger');
        } finally {
            this.setBusy(false);
            if (!keepOptionsLocked) this.lockOptions(false);
        }
    },

    async checkAnswer() {
        if (this.state.isBusy) return;
        const checkBtn = document.getElementById('checkAnswerBtn');
        if (checkBtn && checkBtn.disabled) return;

        const selectedInput = document.getElementById('selectedAnswer');
        const questionIdInput = document.querySelector('input[name="question_id"]');
        const quizForm = document.getElementById('quizForm');
        const csrfToken = this.getCsrfToken(quizForm || document);

        if (!csrfToken) {
            this.notify('Błąd bezpieczeństwa: odśwież stronę i spróbuj ponownie.', 'danger');
            return;
        }

        this.setBusy(true, 'check');
        this.lockOptions(true);

        const formData = new FormData();
        formData.append('action', 'check_answer');
        formData.append('question_id', questionIdInput ? questionIdInput.value : '');
        formData.append('answer', selectedInput ? selectedInput.value : '');
        formData.append('csrf_token', csrfToken);

        let keepOptionsLocked = false;
        try {
            const data = await this.postQuizAction(formData);

            if (data.success && data.phase === 'review') {
                this.updateProgressPanel(data);
                this.renderReview(data.result);
                keepOptionsLocked = true;
            } else {
                this.notify('Błąd: ' + (data.error || 'Nie można sprawdzić odpowiedzi.'), 'danger');
                this.updateProgressPanel(data);
            }
        } catch (error) {
            console.error('Quiz Error:', error);
            this.notify('Wystąpił błąd połączenia.', 'danger');
        } finally {
            this.setBusy(false);
            if (!keepOptionsLocked) this.lockOptions(false);
        }
    },

    async nextQuestion() {
        if (this.state.isBusy) return;
        this.setBusy(true);

        const formData = new FormData();
        formData.append('action', 'next_question');
        formData.append('csrf_token', this.getCsrfToken(document.getElementById('quizForm') || document));

        try {
            const data = await this.postQuizAction(formData);

            if (data.success) {
                if (data.finished) {
                    window.allowQuizNavigation?.();
                    window.location.href = data.redirect;
                } else if (data.phase === 'review' && data.result) {
                    this.updateProgressPanel(data);
                    this.renderReview(data.result);
                } else if (data.question) {
                    this.updateProgressPanel(data);
                    this.renderQuestion(data.question, data.current, data.total, data.saved_answer, data);
                    if (typeof window.resetQuestionTimer === 'function') {
                        window.resetQuestionTimer(data.question_time_limit || data.question_time_left);
                    }
                }
            } else {
                this.notify('Błąd: ' + (data.error || 'Nie można przejść do następnego pytania.'), 'danger');
                this.updateProgressPanel(data);
            }
        } catch (error) {
            console.error('Quiz Error:', error);
            this.notify('Wystąpił błąd połączenia.', 'danger');
        } finally {
            this.setBusy(false);
        }
    },

    syncAnswerCheckControls(data = {}) {
        const counter = document.getElementById('answerCheckCounter');
        const checkBtn = document.getElementById('checkAnswerBtn');
        const hint = document.getElementById('answerCheckHint');
        if (!counter && !checkBtn && !hint) return;

        const usedNode = counter?.querySelector('[data-answer-check-used]');
        const limitNode = counter?.querySelector('[data-answer-check-limit]');
        const currentUsed = Number(usedNode?.textContent || 0);
        const currentLimit = Number(limitNode?.textContent || 0);
        const used = data.answer_check_used !== undefined ? Number(data.answer_check_used) : currentUsed;
        const limit = data.answer_check_limit !== undefined ? Number(data.answer_check_limit) : currentLimit;
        const remaining = data.answer_check_remaining !== undefined
            ? Number(data.answer_check_remaining)
            : Math.max(0, limit - used);
        const modeAllowed = data.answer_check_mode_allowed !== undefined ? !!data.answer_check_mode_allowed : !!checkBtn;
        const available = data.answer_check_available !== undefined ? !!data.answer_check_available : (modeAllowed && remaining > 0);
        const disabledReason = String(data.answer_check_disabled_reason || '');

        if (usedNode) usedNode.textContent = String(Math.max(0, used));
        if (limitNode) limitNode.textContent = String(Math.max(0, limit));
        if (counter && used > currentUsed) {
            counter.classList.remove('is-updated');
            void counter.offsetWidth;
            counter.classList.add('is-updated');
            window.setTimeout(() => counter.classList.remove('is-updated'), 360);
        }
        if (hint) {
            hint.textContent = !modeAllowed || disabledReason === 'mode'
                ? 'Sprawdzanie jest wyłączone w tym trybie.'
                : remaining > 0
                ? `Pozostało ${remaining} z ${limit} sprawdzeń.`
                : 'Limit sprawdzeń został wykorzystany.';
        }
        if (checkBtn) {
            checkBtn.disabled = !available;
            checkBtn.classList.toggle('is-exhausted', !available);
            checkBtn.dataset.answerCheckRemaining = String(Math.max(0, remaining));
            if (!document.querySelector('.review-box')) {
                checkBtn.classList.remove('d-none');
            }
        }
    },

    updateProgressPanel(data = {}) {
        const total = Number(data.total) || 0;
        const current = Number(data.current);
        const answeredCount = data.answered_count !== undefined
            ? Number(data.answered_count)
            : (Number.isFinite(current) ? current : 0);

        const questionEl = document.getElementById('testProgressQuestion');
        const answeredEl = document.getElementById('testProgressAnswered');
        const progressBar = document.getElementById('progressBar');

        this.syncAnswerCheckControls(data);

        if (total <= 0) return;

        if (questionEl && Number.isFinite(current)) {
            questionEl.textContent = `Pytanie ${current + 1} z ${total}`;
        }
        if (answeredEl) {
            answeredEl.textContent = `${answeredCount} / ${total} udzielonych`;
        }
        if (progressBar) {
            progressBar.style.width = `${Math.min(100, Math.round((answeredCount / total) * 100))}%`;
        }
    },

    renderQuestion(q, current, total, savedAnswer = '', data = {}) {
        const cardBody = document.querySelector('.question-card .card-body');
        if (!cardBody || !q) return;
        cardBody.classList.remove('fade-in');
        cardBody.classList.add('fade-out');

        setTimeout(() => {
            // Update image
            let img = cardBody.querySelector('img');
            if (q.image_url) {
                if (!img) {
                    img = document.createElement('img');
                    img.className = 'img-fluid rounded mb-3 shadow-sm';
                    cardBody.prepend(img);
                }
                img.src = q.image_url;
                img.style.display = 'block';
            } else if (img) {
                img.style.display = 'none';
            }

            // Update text
            const qText = cardBody.querySelector('.h4');
            if (qText) {
                qText.innerHTML = this.escapeHtml(q.question_text || '').replace(/\n/g, '<br>');
            }

            // Reset form
            const form = document.getElementById('quizForm');
            if (form) {
                const qIdInput = form.querySelector('input[name="question_id"]');
                if (qIdInput) qIdInput.value = q.id;
            }
            const selectedInput = document.getElementById('selectedAnswer');
            if (selectedInput) {
                selectedInput.value = savedAnswer || '';
            }

            // Restore submit button to answering state
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.onclick = null;
                submitBtn.innerHTML = '<span>Zatwierdź odpowiedź</span><span class="btn-icon-circle"><i class="bi bi-check2"></i></span>';
                submitBtn.disabled = !savedAnswer;
            }

            // Restore check answer button visibility
            const checkBtn = document.getElementById('checkAnswerBtn');
            if (checkBtn) {
                checkBtn.classList.remove('d-none');
            }

            const prevButton = document.querySelector('[data-question-nav="previous"]');
            if (prevButton) prevButton.disabled = current <= 0;

            // Re-render options
            const container = document.getElementById('answersContainer');
            if (container) {
                container.innerHTML = '';
                let idx = 0;
                for (const [key, text] of Object.entries(q.options || {})) {
                    if (!text || text.trim() === '') continue;
                    const opt = document.createElement('div');
                    opt.className = 'answer-option quiz-option' + (savedAnswer === key ? ' selected' : '');
                    opt.dataset.answer = key;
                    opt.innerHTML = `<div class="answer-letter">${key}<span class="key-indicator" title="Skrót klawiszowy">${idx + 1}</span></div><div class="answer-text">${this.escapeHtml(text)}</div>`;
                    opt.onclick = () => this.selectOption(opt);
                    container.appendChild(opt);
                    idx++;
                }
            }

            // Update Category badge
            const badge = document.querySelector('.badge.bg-primary');
            if (badge) badge.textContent = q.category || 'Ogólne';

            // Update AI Tutor button question ID
            const tutorBtn = document.getElementById('btnAiTutor');
            if (tutorBtn) {
                tutorBtn.setAttribute('data-question-id', q.id);
                tutorBtn.dataset.questionId = q.id;
            }

            // Update Flag question button state and question ID
            const flagBtn = document.getElementById('btnFlagQuestion');
            if (flagBtn) {
                flagBtn.setAttribute('data-question-id', q.id);
                flagBtn.dataset.questionId = q.id;
                this.updateFlagButton(Boolean(q.is_bookmarked || q.is_flagged));
            }

            cardBody.querySelectorAll('.review-box, .review-next-actions').forEach(node => node.remove());
            const submitActions = document.querySelector('#quizForm .quiz-action-bar');
            if (submitActions) submitActions.style.display = 'flex';

            this.lockOptions(false);
            cardBody.classList.remove('fade-out');
            cardBody.classList.add('fade-in');
            this.syncAnswerCheckControls(data);
            this.scrollToQuestionTop();
        }, 300);
    },

    scrollToQuestionTop() {
        const target = document.querySelector('.test-progress-panel') || document.querySelector('.question-card');
        if (!target) return;
        const top = Math.max(0, target.getBoundingClientRect().top + window.scrollY - 72);
        window.scrollTo({ top, behavior: 'smooth' });
    },

    renderReview(result) {
        if (typeof window.pauseQuestionTimer === 'function') {
            window.pauseQuestionTimer();
        }

        // Update options classes
        const options = document.querySelectorAll('.quiz-option');
        options.forEach(opt => {
            const letter = opt.dataset.answer;
            opt.classList.remove('selected', 'correct', 'incorrect', 'opacity-75', 'disabled');
            if (letter === result.correct_answer && letter === result.user_answer) {
                opt.classList.add('correct');
            } else if (letter === result.user_answer) {
                opt.classList.add('incorrect');
            } else if (letter === result.correct_answer) {
                opt.classList.add('correct', 'opacity-75');
            } else {
                opt.classList.add('disabled');
            }
            opt.onclick = null; // Disable clicking
        });

        // Hide submit button container
        const submitContainer = document.querySelector('#quizForm .quiz-action-bar');
        if (submitContainer) submitContainer.style.display = 'none';

        // Add review feedback and next button
        const cardBody = document.querySelector('.question-card .card-body');
        if (!cardBody) return;
        cardBody.querySelectorAll('.review-box, .review-next-actions').forEach(node => node.remove());
        const userAnswerText = String(result.user_answer_text || '').trim();
        const correctAnswerText = String(result.correct_answer_text || '').trim();
        const userLabel = userAnswerText
            ? `${result.user_answer || '-'} („${userAnswerText}”)`
            : (result.user_answer || '-');
        const correctLabel = correctAnswerText
            ? `${result.correct_answer} („${correctAnswerText}”)`
            : result.correct_answer;
        const explanationFull = String(result.explanation || '').trim()
            || [
                `Poprawna odpowiedź to ${correctLabel}.`,
                result.is_correct
                    ? 'Wybrano poprawną odpowiedź.'
                    : `Wybrano ${userLabel}.`,
                correctAnswerText ? `Najważniejsze do zapamiętania: ${correctAnswerText}` : ''
            ].filter(Boolean).join('\n');

        let explanationMain = explanationFull;
        let explanationDistractors = '';
        const whyMarker = 'Dlaczego nie reszta?';
        const whyPos = explanationFull.indexOf(whyMarker);

        if (whyPos !== -1) {
            explanationMain = explanationFull.substring(0, whyPos).trim();
            explanationDistractors = explanationFull.substring(whyPos + whyMarker.length).trim();
        }

        const reviewNote = String(result.review_note || '').trim();
        
        const reviewHtml = `
            <div class="review-box mt-3 animate-in">
                ${result.is_correct ? 
                    `<div class="d-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-check-circle-fill text-success fs-4"></i>
                        <strong class="text-success fs-5">Poprawna odpowiedź!</strong>
                    </div>` : 
                    `<div class="d-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-x-circle-fill text-danger fs-4"></i>
                        <strong class="text-danger fs-5">Błędna odpowiedź.</strong>
                    </div>
                    <p class="mb-0 text-muted">Poprawna odpowiedź: <strong class="text-success">${result.correct_answer}</strong></p>`
                }
                ${reviewNote ? `<div class="answer-check-review-note mt-3">
                    <i class="bi bi-patch-question-fill"></i>
                    <span>${this.escapeHtml(reviewNote)}</span>
                </div>` : ''}
                <div class="review-next-actions d-flex gap-2 mt-3 mb-3 flex-wrap animate-in">
                    <button type="button" class="btn ${result.is_last ? 'btn-success' : 'btn-primary'} btn-lg" onclick="QuizEngine.nextQuestion()">
                        ${result.is_last ? 'Zakończ test' : 'Następne pytanie'} <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </div>
                <div class="answer-explanation mt-3">
                    <div class="answer-explanation-label">
                        <i class="bi bi-info-circle-fill"></i>
                        Wyjaśnienie
                    </div>
                    <div>${this.escapeHtml(explanationMain).replace(/\n/g, '<br>')}</div>
                    ${explanationDistractors ? `
                        <button type="button" class="answer-card-view-btn mt-2" data-distractors-toggle aria-expanded="false" onclick="event.stopPropagation(); window.QuizEngine.toggleAnswerDistractors(this)">
                            <i class="bi bi-list-check"></i> Dlaczego nie reszta?
                        </button>
                        <div class="answer-distractors d-none" data-distractors-panel>
                            ${this.escapeHtml(explanationDistractors).replace(/\n/g, '<br>')}
                        </div>
                    ` : ''}
                </div>
            </div>`;
        cardBody.insertAdjacentHTML('beforeend', reviewHtml);

        const checkBtn = document.getElementById('checkAnswerBtn');
        if (checkBtn) {
            checkBtn.disabled = true;
            checkBtn.classList.add('d-none');
        }
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.innerHTML = `<span>${result.is_last ? 'Zakończ test' : 'Następne pytanie'}</span> <span class="btn-icon-circle"><i class="bi bi-arrow-right"></i></span>`;
            submitBtn.disabled = false;
            submitBtn.onclick = (e) => {
                e.preventDefault();
                QuizEngine.nextQuestion();
            };
        }
    },

    selectOption(el) {
        if (!el || el.classList.contains('disabled') || el.classList.contains('correct') || el.classList.contains('incorrect') || document.querySelector('.review-box')) return;
        document.querySelectorAll('.quiz-option').forEach(o => o.classList.remove('selected'));
        el.classList.add('selected');
        const selectedInput = document.getElementById('selectedAnswer');
        if (selectedInput) {
            selectedInput.value = el.dataset.answer || '';
        }
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.disabled = false;
        }
    },

    handleKeyboard(e) {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable || document.querySelector('.modal.show')) return;
        if (document.querySelector('.review-box')) {
            if (e.key === 'Enter') {
                const nextBtn = document.querySelector('.review-next-actions button, .review-next-actions form button');
                if (nextBtn && nextBtn.offsetParent !== null) {
                    nextBtn.click();
                }
            }
            if (e.key === 'f' || e.key === 'F') {
                e.preventDefault();
                this.toggleFlagQuestion();
            }
            return;
        }

        const map = {'1':'A','2':'B','3':'C','4':'D'};
        if (map[e.key]) {
            const opt = document.querySelector(`.quiz-option[data-answer="${map[e.key]}"]`) || document.querySelector(`.sim-answer-option[data-answer="${map[e.key]}"]`);
            if (opt) opt.click();
        }
        if (e.key === 'f' || e.key === 'F') {
            e.preventDefault();
            this.toggleFlagQuestion();
            return;
        }
        if (e.key === 'Enter') {
            const simBtn = document.getElementById('simSubmitBtn');
            if (simBtn && !simBtn.disabled) {
                simBtn.click();
                return;
            }
            const btn = document.getElementById('submitBtn');
            const nextBtn = document.querySelector('.review-next-actions button');
            if (nextBtn && nextBtn.offsetParent !== null) {
                nextBtn.click();
            } else if (btn && !btn.disabled) {
                this.submitAnswer();
            }
        }
    },

    setBusy(busy, action = 'submit') {
        this.state.isBusy = busy;
        const btn = document.getElementById('submitBtn');
        const checkBtn = document.getElementById('checkAnswerBtn');
        if (btn) {
            if (busy) {
                btn.innerHTML = '<span>Przetwarzanie...</span><span class="btn-icon-circle"><span class="spinner-border spinner-border-sm"></span></span>';
                btn.disabled = true;
            } else {
                btn.innerHTML = '<span>Zatwierdź odpowiedź</span><span class="btn-icon-circle"><i class="bi bi-check2"></i></span>';
                const selectedInput = document.getElementById('selectedAnswer');
                btn.disabled = !selectedInput?.value;
            }
        }
        if (checkBtn) {
            if (busy) {
                checkBtn.innerHTML = action === 'check'
                    ? '<span class="spinner-border spinner-border-sm me-2"></span>Sprawdzanie...'
                    : '<i class="bi bi-patch-question me-2"></i>Sprawdź odpowiedź';
                checkBtn.disabled = true;
            } else {
                checkBtn.innerHTML = '<i class="bi bi-patch-question me-2"></i>Sprawdź odpowiedź';
                this.syncAnswerCheckControls();
            }
        }
    },

    lockOptions(locked) {
        document.querySelectorAll('.quiz-option').forEach(opt => {
            opt.classList.toggle('disabled', locked);
        });
    },

    notify(message, type = 'warning') {
        let box = document.getElementById('quizNotice');
        if (!box) {
            box = document.createElement('div');
            box.id = 'quizNotice';
            box.className = 'position-fixed top-0 start-50 translate-middle-x p-3';
            box.style.zIndex = '1080';
            document.body.appendChild(box);
        }
        box.innerHTML = `<div class="alert alert-${type} shadow mb-0" role="alert">${this.escapeHtml(message)}</div>`;
        window.setTimeout(() => {
            if (box) box.innerHTML = '';
        }, 3600);
    },

    toggleAnswerDistractors(button) {
        const panel = button.closest('.answer-explanation')?.querySelector('[data-distractors-panel]');
        if (!panel) return;
        const willShow = panel.classList.contains('d-none');
        panel.classList.toggle('d-none', !willShow);
        button.setAttribute('aria-expanded', willShow ? 'true' : 'false');
    },

    async toggleFlagQuestion(btn = null) {
        const flagBtn = btn || document.getElementById('btnFlagQuestion');
        const form = document.getElementById('quizForm');
        const questionId = flagBtn?.getAttribute('data-question-id')
            || flagBtn?.dataset?.questionId
            || form?.querySelector('input[name="question_id"]')?.value;
        if (!questionId) return;

        const csrfToken = this.getCsrfToken(form || document);
        const formData = new FormData();
        formData.append('question_id', questionId);
        formData.append('action', 'toggle');
        formData.append('csrf_token', csrfToken);

        try {
            let res;
            if (window.AppApi?.postForm) {
                res = await window.AppApi.postForm('actions/bookmark_question.php', formData);
            } else {
                const response = await fetch('actions/bookmark_question.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-CSRF-Token': csrfToken }
                });
                res = await response.json();
            }

            if (res && res.success) {
                this.updateFlagButton(res.is_bookmarked);
                this.notify(res.message || (res.is_bookmarked ? 'Pytanie dodano do zapisanych.' : 'Pytanie usunięto z zapisanych.'), 'info');
            } else {
                this.notify(res?.error || 'Błąd zapisu zakładki.', 'warning');
            }
        } catch (err) {
            console.error('Bookmark error:', err);
            this.notify('Wystąpił błąd połączenia.', 'danger');
        }
    },

    updateFlagButton(isFlagged) {
        const flagBtn = document.getElementById('btnFlagQuestion');
        if (!flagBtn) return;
        const icon = flagBtn.querySelector('#flagIcon') || flagBtn.querySelector('i');
        const text = flagBtn.querySelector('#flagText') || flagBtn.querySelector('.flag-text');

        if (isFlagged) {
            flagBtn.classList.remove('btn-outline-warning');
            flagBtn.classList.add('btn-warning');
            if (icon) {
                icon.className = 'bi bi-flag-fill me-1';
            }
            if (text) text.textContent = 'Oznaczono';
            flagBtn.setAttribute('title', 'Usuń oznaczenie flagą (Skrót [F])');
        } else {
            flagBtn.classList.remove('btn-warning');
            flagBtn.classList.add('btn-outline-warning');
            if (icon) {
                icon.className = 'bi bi-flag me-1';
            }
            if (text) text.textContent = 'Oznacz';
            flagBtn.setAttribute('title', 'Oznacz pytanie flagą do weryfikacji (Skrót [F])');
        }
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

window.QuizEngine = QuizEngine;
window.selectQuizOption = (el) => QuizEngine.selectOption(el);

// Initialize on load
document.addEventListener('DOMContentLoaded', () => QuizEngine.init());
