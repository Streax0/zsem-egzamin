/**
 * exam-engine.js - Silnik egzaminacyjny oparty na AJAX
 */
const ExamEngine = {
    state: {
        isBusy: false,
        startTime: Date.now(),
        questionStartTime: Date.now(),
        lastViolationReports: Object.create(null),
        pendingViolationReports: Object.create(null)
    },

    init() {
        this.setupEventListeners();
        this.setupNetworkWatchdog();
    },

    setupNetworkWatchdog() {
        const updateBanner = (online) => {
            const banner = document.getElementById('networkStatusBanner');
            if (banner) {
                if (online) {
                    banner.className = 'alert alert-success py-2 px-3 mb-3 d-flex align-items-center gap-2';
                    banner.innerHTML = '<i class="bi bi-wifi fs-5"></i><span class="small fw-medium">Połączenie z siecią zostało przywrócone.</span>';
                    setTimeout(() => { banner.classList.add('d-none'); }, 3000);
                } else {
                    banner.className = 'alert alert-warning py-2 px-3 mb-3 d-flex align-items-center gap-2';
                    banner.innerHTML = '<i class="bi bi-wifi-off fs-5"></i><span class="small fw-medium">Utracono połączenie z siecią. Odpowiedź zostanie ponowiona po przywróceniu łączności.</span>';
                    banner.classList.remove('d-none');
                }
            }
        };
        window.addEventListener('online', () => updateBanner(true));
        window.addEventListener('offline', () => updateBanner(false));
    },

    getCsrfToken(scope = document) {
        const scopedToken = scope.querySelector?.('input[name="csrf_token"]');
        if (scopedToken?.value) return scopedToken.value;

        const answerToken = document.querySelector('#answerForm input[name="csrf_token"]');
        if (answerToken?.value) return answerToken.value;

        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    },

    async postExamAction(formData) {
        if (window.AppApi?.postForm) {
            return window.AppApi.postForm('../ajax/exam_action.php', formData);
        }
        const response = await fetch('../ajax/exam_action.php', {
            method: 'POST',
            body: formData
        });
        return response.json();
    },

    setupEventListeners() {
        const form = document.getElementById('answerForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.submitAnswer();
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
    },

    async submitAnswer(retryCount = 0) {
        if (this.state.isBusy && retryCount === 0) return;

        const selectedInput = document.getElementById('selectedAnswer');
        const questionIdInput = document.querySelector('input[name="question_id"]');
        const questionOrderInput = document.querySelector('input[name="question_order"]');
        const answerForm = document.getElementById('answerForm');
        const csrfToken = this.getCsrfToken(answerForm || document);
        const sessionIdInput = new URLSearchParams(window.location.search).get('session') || '';

        if (!selectedInput?.value) {
            this.notify('Wybierz odpowiedź.', 'warning');
            return;
        }

        this.setBusy(true);
        const timeSpent = Math.max(0, Math.round((Date.now() - this.state.questionStartTime) / 1000));

        const formData = new FormData();
        formData.append('action', 'submit_answer');
        formData.append('session_id', sessionIdInput);
        formData.append('question_id', questionIdInput?.value || '');
        formData.append('question_order', questionOrderInput?.value || '');
        formData.append('answer', selectedInput.value);
        formData.append('time_spent', timeSpent);
        formData.append('csrf_token', csrfToken);

        try {
            const data = await this.postExamAction(formData);

            if (data.success) {
                if (data.finished) {
                    window.location.href = data.redirect;
                } else {
                    // Smooth reload next question or update DOM
                    // For live exams, we reload to ensure we have the next question from DB order
                    window.location.reload(); 
                }
            } else {
                this.notify('Błąd: ' + (data.error || 'Nieznany błąd'), 'danger');
                this.setBusy(false);
            }
        } catch (error) {
            console.error('Exam Error:', error);
            if (retryCount < 2) {
                this.notify(`Chwilowy brak odpowiedzi serwera. Ponawianie próby (${retryCount + 1}/2)...`, 'warning');
                setTimeout(() => this.submitAnswer(retryCount + 1), 1500);
            } else {
                this.notify('Błąd połączenia. Sprawdź internet i spróbuj ponownie.', 'danger');
                this.setBusy(false);
            }
        }
    },

    reportViolation(type, sessionId, participantId, questionId) {
        // Prevent double hits (debounce)
        const now = Date.now();
        const lastReport = this.state.lastViolationReports[type] || 0;
        if (now - lastReport < 2000) {
            console.warn('Violation report debounced');
            return;
        }
        if (this.state.pendingViolationReports[type]) {
            console.warn('Violation report already pending');
            return;
        }
        this.state.pendingViolationReports[type] = true;

        const formData = new FormData();
        formData.append('action', 'report_violation');
        formData.append('session_id', sessionId);
        formData.append('violation_type', type);
        formData.append('question_id', questionId);
        formData.append('csrf_token', this.getCsrfToken(document.getElementById('answerForm') || document));

        this.postExamAction(formData).then(data => {
            if (data.success) {
                this.state.lastViolationReports[type] = Date.now();
                // Update UI counter for student
                const counter = document.getElementById('violationCount');
                if (counter) {
                    let val = parseInt(counter.textContent) || 0;
                    counter.textContent = val + 1;
                }
                const warn = document.createElement('div');
                warn.className = 'alert alert-danger position-fixed top-0 start-50 translate-middle-x mt-3 shadow-lg';
                warn.style.zIndex = '10000';
                warn.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Ostrzeżenie!</strong> Naruszenie zasad zostało odnotowane.';
                document.body.appendChild(warn);
                setTimeout(() => warn.remove(), 4000);
            } else {
                console.warn('Violation report rejected:', data.error || 'unknown error');
            }
        }).catch(e => console.error('Violation Report Error:', e)).finally(() => {
            this.state.pendingViolationReports[type] = false;
        });
    },

    selectOption(el, letter) {
        if (!el) return;
        document.querySelectorAll('.answer-option').forEach(o => {
            o.classList.remove('selected');
            o.setAttribute('aria-selected', 'false');
        });
        el.classList.add('selected');
        el.setAttribute('aria-selected', 'true');
        const selectedInput = document.getElementById('selectedAnswer');
        if (selectedInput) selectedInput.value = letter;
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) submitBtn.disabled = false;
    },

    handleKeyboard(e) {
        if (['INPUT','TEXTAREA'].includes(e.target.tagName)) return;
        const key = (e.key || '').toUpperCase();
        const map = {
            '1': 'A', 'A': 'A',
            '2': 'B', 'B': 'B',
            '3': 'C', 'C': 'C',
            '4': 'D', 'D': 'D'
        };
        if (map[key]) {
            const opt = document.querySelector(`.answer-option[data-shortcut="${key}"]`) 
                     || document.querySelector(`.answer-option[data-answer="${map[key]}"]`);
            if (opt) opt.click();
        }
        if (e.key === 'Enter') {
            const btn = document.getElementById('submitBtn');
            if (btn && !btn.disabled) this.submitAnswer();
        }
    },

    setBusy(busy) {
        this.state.isBusy = busy;
        const btn = document.getElementById('submitBtn');
        if (btn) {
            btn.disabled = busy;
            btn.innerHTML = busy ? '<span class="spinner-border spinner-border-sm me-2"></span>Wysyłanie...' : '<i class="bi bi-check2-circle me-2"></i>Zatwierdź';
        }
    },

    notify(message, type = 'info') {
        if (typeof window.appNotice === 'function') {
            window.appNotice(message, type);
            return;
        }
        console.warn(message);
    }
};

document.addEventListener('DOMContentLoaded', () => ExamEngine.init());
