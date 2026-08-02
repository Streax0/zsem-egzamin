/**
 * Landing Page Interactive Script - ZSEM Tech
 */
document.addEventListener('DOMContentLoaded', function() {
    // 1. Hero Stage Interactive Quiz Switcher & Solver
    const quizQuestions = {
        'inf03': {
            badge: 'INF.03 / Programowanie i Bazy Danych',
            meter: '72%',
            question: 'Jaką rolę pełni klucz obcy (FOREIGN KEY) w relacyjnej bazie danych?',
            options: [
                { letter: 'A', text: 'Szyfruje pola w tabeli', correct: false, explanation: 'Klucze obce nie odpowiadają za szyfrowanie danych.' },
                { letter: 'B', text: 'Łączy tabele i wymusza integralność', correct: true, explanation: 'Dokładnie! Klucz obcy definiuje relację i dba o spójność referencyjną.' },
                { letter: 'C', text: 'Usuwa zduplikowane rekordy', correct: false, explanation: 'Integralność i duplikaty są obsługiwane przez UNIQUE / PRIMARY KEY.' },
                { letter: 'D', text: 'Zmienia typ danych kolumny', correct: false, explanation: 'Typy kolumn definiowane są w strukturze tabeli (ALTER / CREATE TABLE).' }
            ]
        },
        'inf02': {
            badge: 'INF.02 / Administracja Sieciami',
            meter: '85%',
            question: 'Który protokół służy do automatycznego przydzielania adresów IP w sieci?',
            options: [
                { letter: 'A', text: 'DNS', correct: false, explanation: 'DNS tłumaczy nazwy domenowe na adresy IP.' },
                { letter: 'B', text: 'DHCP', correct: true, explanation: 'Poprawnie! DHCP automatycznie konfiguruje adresację IP w sieci.' },
                { letter: 'C', text: 'FTP', correct: false, explanation: 'FTP służy do przesyłania plików.' },
                { letter: 'D', text: 'SNMP', correct: false, explanation: 'SNMP służy do monitorowania urządzeń sieciowych.' }
            ]
        },
        'ee09': {
            badge: 'EE.09 / Tworzenie Aplikacji',
            meter: '60%',
            question: 'W języku JavaScript, która metoda dodaje element na końcu tablicy?',
            options: [
                { letter: 'A', text: 'push()', correct: true, explanation: 'Świetnie! Metoda push() dodaje jeden lub więcej elementów na koniec tablicy.' },
                { letter: 'B', text: 'pop()', correct: false, explanation: 'pop() usuwa ostatni element z tablicy.' },
                { letter: 'C', text: 'shift()', correct: false, explanation: 'shift() usuwa pierwszy element z tablicy.' },
                { letter: 'D', text: 'unshift()', correct: false, explanation: 'unshift() dodaje elementy na początku tablicy.' }
            ]
        }
    };

    const stageContainer = document.querySelector('.landing-stage');
    if (stageContainer) {
        const badgeEl = stageContainer.querySelector('.stage-header strong');
        const meterEl = stageContainer.querySelector('.stage-meter span');
        const questionEl = stageContainer.querySelector('.stage-question h2');
        const optionsEl = stageContainer.querySelector('.stage-options');
        const tabs = stageContainer.querySelectorAll('.stage-tab-btn');

        function renderQuestion(key) {
            const data = quizQuestions[key];
            if (!data) return;

            if (badgeEl) badgeEl.textContent = data.badge;
            if (meterEl) meterEl.style.width = data.meter;
            if (questionEl) questionEl.textContent = data.question;

            if (optionsEl) {
                optionsEl.innerHTML = '';
                data.options.forEach(opt => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'stage-option-btn' + (opt.correct ? ' is-target-correct' : '');
                    btn.innerHTML = `<span class="opt-letter">${opt.letter}.</span> <span class="opt-text">${opt.text}</span>`;
                    
                    btn.addEventListener('click', function() {
                        // Reset choices
                        optionsEl.querySelectorAll('.stage-option-btn').forEach(b => {
                            b.classList.remove('is-selected-correct', 'is-selected-wrong');
                        });
                        
                        if (opt.correct) {
                            btn.classList.add('is-selected-correct');
                            showFeedback('✓ ' + opt.explanation, 'success');
                        } else {
                            btn.classList.add('is-selected-wrong');
                            showFeedback('✕ ' + opt.explanation, 'error');
                        }
                    });
                    optionsEl.appendChild(btn);
                });
            }
        }

        let feedbackBox = stageContainer.querySelector('.stage-feedback');
        if (!feedbackBox) {
            feedbackBox = document.createElement('div');
            feedbackBox.className = 'stage-feedback';
            stageContainer.querySelector('.stage-question').appendChild(feedbackBox);
        }

        function showFeedback(text, type) {
            feedbackBox.textContent = text;
            feedbackBox.className = 'stage-feedback visible ' + type;
        }

        // Attach click listeners to tabs if present
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                const cat = this.getAttribute('data-cat');
                if (feedbackBox) feedbackBox.className = 'stage-feedback';
                renderQuestion(cat);
            });
        });

        // Initialize first tab
        renderQuestion('inf03');
    }

    // 2. Intersection Observer for Scroll Reveal Animations
    const animatedElements = document.querySelectorAll('.reveal-on-scroll');
    if ('IntersectionObserver' in window && animatedElements.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        animatedElements.forEach(el => observer.observe(el));
    }
});
