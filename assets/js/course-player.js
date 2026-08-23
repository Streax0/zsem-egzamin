(() => {
    'use strict';
    const player = document.querySelector('[data-course-player]');
    if (!player) return;

    const notice = (message, type = 'info') => {
        if (typeof window.appNotice === 'function') {
            window.appNotice(message, type);
        } else {
            console.log(`[${type}] ${message}`);
        }
    };

    const request = async (url, formData) => {
        const response = await fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' });
        let payload;
        try { payload = await response.json(); } catch (_) { throw new Error('Nieprawidłowa odpowiedź serwera.'); }
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Wystąpił błąd podczas przetwarzania.');
        return payload;
    };

    const syncProgress = (percent) => document.querySelectorAll('[data-course-progress]').forEach((element) => {
        element.style.width = `${percent}%`;
        element.setAttribute('aria-valuenow', percent);
        element.textContent = element.dataset.progressLabel === 'true' ? `${percent}%` : element.textContent;
    });

    // Complete lesson handler
    document.getElementById('completeLessonForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const button = event.currentTarget.querySelector('button[type="submit"]');
        if (button) button.disabled = true;
        try {
            const payload = await request('ajax/course_progress.php', new FormData(event.currentTarget));
            syncProgress(payload.progress_percent);
            notice('Lekcja została oznaczona jako ukończona.', 'success');
            window.setTimeout(() => window.location.reload(), 450);
        } catch (error) {
            notice(error.message, 'danger');
            if (button) button.disabled = false;
        }
    });

    // Quiz submission handler
    document.getElementById('courseQuizForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const questions = [...form.querySelectorAll('[data-question-id]')];
        if (questions.some((question) => !question.querySelector('input:checked'))) {
            notice('Odpowiedz na wszystkie pytania przed wysłaniem quizu.', 'warning');
            return;
        }
        const data = new FormData(form);
        data.append('action', 'submit_quiz');
        data.append('csrf_token', player.dataset.csrfToken || '');
        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;
        try {
            const payload = await request('ajax/course_progress.php', data);
            syncProgress(payload.progress_percent);
            const result = document.getElementById('courseQuizResult');
            if (result) {
                result.hidden = false;
                result.className = `alert ${payload.passed ? 'alert-success' : 'alert-warning'} mt-3`;
                result.textContent = `Wynik: ${payload.score_percent}% (${payload.correct_count}/${payload.total_count}). ${payload.passed ? 'Quiz zaliczony.' : `Wymagane jest ${payload.passing_score}%.`}`;
            }
            if (payload.passed) {
                window.setTimeout(() => window.location.reload(), 1000);
            } else if (button) {
                button.disabled = false;
            }
        } catch (error) {
            notice(error.message, 'danger');
            if (button) button.disabled = false;
        }
    });

    // Interactive In-Lesson CLI Task Handlers
    document.querySelectorAll('.btn-check-cli-task').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const taskId = btn.dataset.taskId;
            const input = document.getElementById(`term_in_${taskId}`);
            const output = document.getElementById(`term_out_${taskId}`);
            if (!input || !output) return;

            const command = input.value.trim();
            if (!command) {
                output.textContent = 'Błąd: Wpisz polecenie przed sprawdzeniem.';
                output.className = 'terminal-output small text-warning mb-2';
                return;
            }

            const itemId = player.dataset.activeItemId || '0';
            const formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('task_id', taskId);
            formData.append('command', command);
            formData.append('csrf_token', player.dataset.csrfToken || '');

            btn.disabled = true;
            try {
                const res = await request('ajax/course_cli_validate.php', formData);
                output.textContent = `${res.output}\n\n[SUKCES] ${res.message}`;
                output.className = 'terminal-output small text-success mb-2';
                notice(res.message, 'success');
            } catch (err) {
                output.textContent = `Błąd: ${err.message}`;
                output.className = 'terminal-output small text-danger mb-2';
                notice(err.message, 'warning');
            } finally {
                btn.disabled = false;
            }
        });
    });

    // In-Lesson CLI Enter key runner
    document.querySelectorAll('.course-cli-input').forEach((input) => {
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const card = input.closest('.course-cli-task-block');
                const btn = card?.querySelector('.btn-check-cli-task');
                btn?.click();
            }
        });
    });

    // In-Lesson Hint Boxes
    document.querySelectorAll('.btn-course-hint').forEach((btn) => {
        btn.addEventListener('click', () => {
            const card = btn.closest('.course-cli-task-block');
            const hintBox = card?.querySelector('.course-hint-box');
            if (hintBox) {
                hintBox.classList.toggle('d-none');
            }
        });
    });

    // In-Lesson Interactive Micro-Quiz
    document.querySelectorAll('.course-interactive-quiz').forEach((quiz) => {
        const correctIdx = parseInt(quiz.dataset.correctIdx || '0', 10);
        const expl = quiz.querySelector('.course-quiz-expl');
        quiz.querySelectorAll('.course-quiz-opt').forEach((btn) => {
            btn.addEventListener('click', () => {
                const optIdx = parseInt(btn.dataset.optIdx || '0', 10);
                if (optIdx === correctIdx) {
                    btn.className = 'btn btn-sm btn-success text-start course-quiz-opt';
                    notice('Poprawna odpowiedź!', 'success');
                } else {
                    btn.className = 'btn btn-sm btn-danger text-start course-quiz-opt';
                    notice('Niepoprawna odpowiedź. Sprawdź wyjaśnienie.', 'warning');
                }
                if (expl) expl.classList.remove('d-none');
            });
        });
    });

    // Keyboard navigation (J for Prev, K for Next)
    window.addEventListener('keydown', (event) => {
        if (['input', 'textarea', 'select'].includes(document.activeElement?.tagName?.toLowerCase())) return;
        if (event.key === 'j' || event.key === 'J') {
            const prev = document.querySelector('a.btn-outline-secondary[href*="course_learn.php"]');
            if (prev) prev.click();
        } else if (event.key === 'k' || event.key === 'K') {
            const next = document.querySelector('a.btn-primary[href*="course_learn.php"]');
            if (next) next.click();
        }
    });

    // Student Private Notes Auto-Save
    const noteArea = document.getElementById('studentCourseNoteArea');
    if (noteArea) {
        const itemId = player.dataset.activeItemId || '0';
        const courseId = player.dataset.courseId || '0';

        // Load existing note
        (async () => {
            try {
                const formData = new FormData();
                formData.append('action', 'load');
                formData.append('item_id', itemId);
                formData.append('course_id', courseId);
                const res = await request('ajax/save_course_note.php', formData);
                if (res.note) {
                    noteArea.value = res.note;
                }
            } catch (_) {}
        })();

        // Auto-save on debounce
        let debounceTimer;
        noteArea.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            const statusIndicator = document.getElementById('noteSaveStatus');
            if (statusIndicator) statusIndicator.textContent = 'Zapisywanie...';
            debounceTimer = setTimeout(async () => {
                try {
                    const formData = new FormData();
                    formData.append('item_id', itemId);
                    formData.append('course_id', courseId);
                    formData.append('note', noteArea.value);
                    const res = await request('ajax/save_course_note.php', formData);
                    if (statusIndicator) statusIndicator.textContent = 'Zapisano: ' + (res.updated_at || 'teraz');
                } catch (_) {
                    if (statusIndicator) statusIndicator.textContent = 'Błąd zapisu';
                }
            }, 800);
        });
    }
})();
