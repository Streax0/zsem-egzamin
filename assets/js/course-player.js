(() => {
    'use strict';
    const player = document.querySelector('[data-course-player]');
    if (!player) return;
    const notice = (message, type = 'danger') => typeof window.appNotice === 'function' ? window.appNotice(message, type) : window.alert(message);
    const request = async (formData) => {
        const response = await fetch('ajax/course_progress.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        let payload;
        try { payload = await response.json(); } catch (_) { throw new Error('Nieprawidłowa odpowiedź serwera.'); }
        if (!response.ok || !payload.success) throw new Error(payload.message || 'Nie udało się zapisać postępu.');
        return payload;
    };
    const syncProgress = (percent) => document.querySelectorAll('[data-course-progress]').forEach((element) => {
        element.style.width = `${percent}%`;
        element.setAttribute('aria-valuenow', percent);
        element.textContent = element.dataset.progressLabel === 'true' ? `${percent}%` : element.textContent;
    });
    document.getElementById('completeLessonForm')?.addEventListener('submit', async (event) => {
        event.preventDefault(); const button = event.currentTarget.querySelector('button[type="submit"]'); if (button) button.disabled = true;
        try { const payload = await request(new FormData(event.currentTarget)); syncProgress(payload.progress_percent); notice('Lekcja została oznaczona jako ukończona.', 'success'); window.setTimeout(() => window.location.reload(), 450); }
        catch (error) { notice(error.message); if (button) button.disabled = false; }
    });
    document.getElementById('courseQuizForm')?.addEventListener('submit', async (event) => {
        event.preventDefault(); const form = event.currentTarget; const questions = [...form.querySelectorAll('[data-question-id]')];
        if (questions.some((question) => !question.querySelector('input:checked'))) { notice('Odpowiedz na wszystkie pytania przed wysłaniem quizu.', 'warning'); return; }
        const data = new FormData(form); data.append('action', 'submit_quiz'); data.append('csrf_token', player.dataset.csrfToken); const button = form.querySelector('button[type="submit"]'); if (button) button.disabled = true;
        try { const payload = await request(data); syncProgress(payload.progress_percent); const result = document.getElementById('courseQuizResult'); if (result) { result.hidden = false; result.className = `alert ${payload.passed ? 'alert-success' : 'alert-warning'} mt-3`; result.textContent = `Wynik: ${payload.score_percent}% (${payload.correct_count}/${payload.total_count}). ${payload.passed ? 'Quiz zaliczony.' : `Wymagane jest ${payload.passing_score}%.`}`; } if (payload.passed) window.setTimeout(() => window.location.reload(), 1000); else if (button) button.disabled = false; }
        catch (error) { notice(error.message); if (button) button.disabled = false; }
    });
})();
