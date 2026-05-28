/**
 * quiz-engine.js - Nowoczesny silnik testów oparty na AJAX
 */
const QuizEngine = {
    state: {
        isBusy: false,
        timer: null
    },

    init() {
        this.setupEventListeners();
    },

    getCsrfToken(scope = document) {
        const scopedToken = scope.querySelector?.('input[name="csrf_token"]');
        if (scopedToken?.value) return scopedToken.value;

        const quizToken = document.querySelector('#quizForm input[name="csrf_token"]');
        if (quizToken?.value) return quizToken.value;

        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    },

    setupEventListeners() {
        // Intercept form submission
        const quizForm = document.getElementById('quizForm');
        if (quizForm) {
            quizForm.addEventListener('submit', (e) => {
                const submitter = e.submitter;
                if (submitter?.name === 'action' && submitter.value !== 'submit_answer') {
                    return;
                }
                e.preventDefault();
                this.submitAnswer();
            });
        }

        // Global keydown for shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
    },

    async submitAnswer() {
        if (this.state.isBusy) return;
        
        const selectedInput = document.getElementById('selectedAnswer');
        const questionIdInput = document.querySelector('input[name="question_id"]');
        const quizForm = document.getElementById('quizForm');
        const csrfToken = this.getCsrfToken(quizForm || document);

        if (!selectedInput.value) {
            this.notify('Proszę wybrać odpowiedź.', 'warning');
            return;
        }
        if (!csrfToken) {
            this.notify('Błąd bezpieczeństwa: odśwież stronę i spróbuj ponownie.', 'danger');
            return;
        }

        this.setBusy(true);
        
        const formData = new FormData();
        formData.append('action', 'submit_answer');
        formData.append('question_id', questionIdInput.value);
        formData.append('answer', selectedInput.value);
        formData.append('csrf_token', csrfToken);

        try {
            const response = await fetch('ajax/quiz_action.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                if (data.finished) {
                    window.location.href = data.redirect;
                } else if (data.next_question) {
                    this.renderQuestion(data.question, data.current, data.total);
                } else if (data.phase === 'review') {
                    this.renderReview(data.result);
                }
            } else {
                this.notify('Błąd: ' + (data.error || 'Nieznany błąd'), 'danger');
                if (data.error === 'No active test' || data.error === 'Invalid CSRF token') {
                    window.location.reload();
                }
            }
        } catch (error) {
            console.error('Quiz Error:', error);
            this.notify('Wystąpił błąd połączenia.', 'danger');
        } finally {
            this.setBusy(false);
        }
    },

    async nextQuestion() {
        if (this.state.isBusy) return;
        this.setBusy(true);

        const formData = new FormData();
        formData.append('action', 'next_question');
        formData.append('csrf_token', this.getCsrfToken(document.getElementById('quizForm') || document));

        try {
            const response = await fetch('ajax/quiz_action.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();

            if (data.success) {
                if (data.finished) {
                    window.location.href = data.redirect;
                } else {
                    this.renderQuestion(data.question, data.current, data.total);
                }
            }
        } catch (error) {
            console.error('Quiz Error:', error);
        } finally {
            this.setBusy(false);
        }
    },

    renderQuestion(q, current, total) {
        // 1. Update progress bar and text
        const progressBar = document.getElementById('progressBar');
        const progressHeader = document.querySelector('strong.h5');
        
        if (progressBar) progressBar.style.width = ((current / total) * 100) + '%';
        if (progressHeader) progressHeader.textContent = `Pytanie ${current + 1} z ${total}`;

        // 2. Update question content with fade effect
        const cardBody = document.querySelector('.question-card .card-body');
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
            qText.innerHTML = this.escapeHtml(q.question_text).replace(/\n/g, '<br>');

            // Reset form
            const form = document.getElementById('quizForm');
            form.querySelector('input[name="question_id"]').value = q.id;
            document.getElementById('selectedAnswer').value = '';
            document.getElementById('submitBtn').disabled = true;
            const prevButton = document.querySelector('[data-question-nav="previous"]');
            if (prevButton) prevButton.disabled = current <= 0;

            // Re-render options
            const container = document.getElementById('answersContainer');
            container.innerHTML = '';
            for (const [key, text] of Object.entries(q.options)) {
                if (!text || text.trim() === '') continue;
                const opt = document.createElement('div');
                opt.className = 'answer-option quiz-option';
                opt.dataset.answer = key;
                opt.innerHTML = `<div class="answer-letter">${key}</div><div class="answer-text">${this.escapeHtml(text)}</div>`;
                opt.onclick = () => this.selectOption(opt);
                container.appendChild(opt);
            }

            // Update Category badge
            const badge = document.querySelector('.badge.bg-primary');
            if (badge) badge.textContent = q.category;

            // Remove review box if exists
            const reviewBox = document.querySelector('.review-box');
            if (reviewBox) reviewBox.remove();
            
            // Remove next button container if exists
            const nextContainer = document.querySelector('.d-flex.gap-2.mt-4.flex-wrap');
            if (nextContainer) {
                // Show original submit button container if hidden
                document.querySelector('#quizForm .d-flex.justify-content-between').style.display = 'flex';
                nextContainer.remove();
            }

            cardBody.classList.remove('fade-out');
            cardBody.classList.add('fade-in');
        }, 300);
    },

    renderReview(result) {
        // Update options classes
        const options = document.querySelectorAll('.quiz-option');
        options.forEach(opt => {
            const letter = opt.dataset.answer;
            opt.classList.remove('selected');
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
        const submitContainer = document.querySelector('#quizForm .d-flex.justify-content-between');
        if (submitContainer) submitContainer.style.display = 'none';

        // Add review feedback and next button
        const cardBody = document.querySelector('.question-card .card-body');
        
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
            </div>
            <div class="d-flex gap-2 mt-4 flex-wrap animate-in">
                <button type="button" class="btn ${result.is_last ? 'btn-success' : 'btn-primary'} btn-lg" onclick="QuizEngine.nextQuestion()">
                    ${result.is_last ? 'Zakończ test' : 'Następne pytanie'} <i class="bi bi-arrow-right ms-2"></i>
                </button>
            </div>
        `;
        
        cardBody.insertAdjacentHTML('beforeend', reviewHtml);
    },

    selectOption(el) {
        if (el.classList.contains('disabled')) return;
        document.querySelectorAll('.quiz-option').forEach(o => o.classList.remove('selected'));
        el.classList.add('selected');
        document.getElementById('selectedAnswer').value = el.dataset.answer;
        document.getElementById('submitBtn').disabled = false;
    },

    handleKeyboard(e) {
        if (['INPUT','TEXTAREA'].includes(e.target.tagName)) return;
        const map = {'1':'A','2':'B','3':'C','4':'D'};
        if (map[e.key]) {
            const opt = document.querySelector(`.quiz-option[data-answer="${map[e.key]}"]`);
            if (opt) opt.click();
        }
        if (e.key === 'Enter') {
            const btn = document.getElementById('submitBtn');
            const nextBtn = document.querySelector('.d-flex.gap-2.mt-4 button');
            if (nextBtn && nextBtn.offsetParent !== null) {
                nextBtn.click();
            } else if (btn && !btn.disabled) {
                this.submitAnswer();
            }
        }
    },

    setBusy(busy) {
        this.state.isBusy = busy;
        const btn = document.getElementById('submitBtn');
        if (btn) {
            if (busy) {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Przetwarzanie...';
                btn.disabled = true;
            } else {
                btn.innerHTML = '<i class="bi bi-check2-circle me-2"></i>Zatwierdź odpowiedź';
            }
        }
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

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Initialize on load
document.addEventListener('DOMContentLoaded', () => QuizEngine.init());
