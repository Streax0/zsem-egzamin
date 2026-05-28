/**
 * quiz.js - Główny skrypt JavaScript dla stron quizów / testów
 * Obsługuje: timer, selekcję odpowiedzi, skróty klawiaturowe
 * Wersja: 2.0
 * Data: 2026-05-02
 */

(function () {
    'use strict';

    // =========================================================================
    // KONFIGURACJA
    // =========================================================================

    const CONFIG = {
        WARNING_THRESHOLD_SECONDS: 300, // 5 minutes
        FADE_DURATION_MS: 300,
        DEBUG: false
    };

    // =========================================================================
    // ELEMENTY DOM
    // =========================================================================

    const elements = {
        timer: document.getElementById('timer'),
        progressBar: document.getElementById('progressBar'),
        progressText: document.getElementById('progressText'),
        answersContainer: document.getElementById('answersContainer'),
        quizForm: document.getElementById('quizForm'),
        selectedAnswerInput: document.getElementById('selectedAnswer'),
        navDots: document.querySelectorAll('.nav-dot'),
        answerOptions: document.querySelectorAll('.answer-option')
    };

    // =========================================================================
    // STAN APLIKACJI
    // =========================================================================

    let state = {
        timeRemaining: 3600,
        timerInterval: null,
        totalQuestions: 0,
        currentQuestion: 0,
        isExamMode: true
    };

    // =========================================================================
    // INICJALIZACJA
    // =========================================================================

    function init() {
        // Pobierz tryb z atrybutu body
        const bodyMode = document.body.getAttribute('data-mode');
        state.isExamMode = bodyMode !== 'practice';

        // Liczba pytań z PHP
        const currentQEl = document.querySelector('[data-current-question]');
        if (currentQEl) {
            state.currentQuestion = parseInt(currentQEl.dataset.currentQuestion, 10) || 1;
        }

        // Total questions z progress bar
        const progressTextEl = document.getElementById('progressText');
        if (progressTextEl) {
            const match = progressTextEl.textContent.match(/z\s+(\d+)/);
            state.totalQuestions = match ? parseInt(match[1], 10) : 0;
        }

        setupAnswerSelection();
        setupKeyboardShortcuts();
        
        if (state.isExamMode) {
            startTimer();
        }
    }

    // =========================================================================
    // TIMER
    // =========================================================================

    function formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    function updateTimerDisplay() {
        if (!elements.timer) return;
        
        elements.timer.textContent = formatTime(state.timeRemaining);

        // Ostrzeżenie gdy < 5 minut
        if (state.timeRemaining <= CONFIG.WARNING_THRESHOLD_SECONDS) {
            elements.timer.classList.add('timer-warning');
        } else {
            elements.timer.classList.remove('timer-warning');
        }
    }

    function startTimer() {
        state.timeRemaining = 3600;
        updateTimerDisplay();

        state.timerInterval = setInterval(() => {
            state.timeRemaining--;
            
            if (state.timeRemaining < 0) {
                clearInterval(state.timerInterval);
                handleTimeOut();
                return;
            }
            
            updateTimerDisplay();
        }, 1000);

        if (CONFIG.DEBUG) console.log('Timer started');
    }

    function handleTimeOut() {
        if (CONFIG.DEBUG) console.log('Time is up!');
        if (typeof window.appNotice === 'function') {
            window.appNotice('Czas upłynął. Test zostanie automatycznie zakończony.', 'warning');
        }
        window.location.href = 'result.php';
    }

    // =========================================================================
    // SELEKCJA ODPOWIEDZI
    // =========================================================================

    function setupAnswerSelection() {
        document.querySelectorAll('.answer-option').forEach((option, index) => {
            option.addEventListener('click', function() {
                // Usuń zaznaczenie ze wszystkich
                document.querySelectorAll('.answer-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Zaznacz klikniętą
                this.classList.add('selected');
                
                // Ustaw wartość w ukrytym polu
                const answerValue = ['A', 'B', 'C', 'D'][index];
                if (elements.selectedAnswerInput) {
                    elements.selectedAnswerInput.value = answerValue;
                }
            });
        });
    }

    // =========================================================================
    // SKRÓTY KLAWIATUROWE
    // =========================================================================

    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ignoruj w inputach
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }

            if (['1', '2', '3', '4'].includes(e.key)) {
                const index = parseInt(e.key, 10) - 1;
                const option = document.querySelector(`.answer-option:nth-child(${index + 1})`);
                if (option) option.click();
            }

            if (e.key === 'Enter') {
                e.preventDefault();
                const form = document.getElementById('quizForm');
                if (form && document.querySelector('.answer-option.selected')) {
                    form.submit();
                } else if (form) {
                    if (typeof window.appNotice === 'function') {
                        window.appNotice('Najpierw wybierz odpowiedź.', 'warning');
                    }
                }
            }
        });
    }

    // =========================================================================
    // START
    // =========================================================================

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
