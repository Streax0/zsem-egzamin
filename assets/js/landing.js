/**
 * Landing Page Interactive Script - ZSEM Tech (2026)
 */
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // 1. Hero Stage Interactive Quiz Switcher & Real-time Explanations
    const quizQuestions = {
        'inf03': {
            badge: 'INF.03 / Bazy danych',
            meter: '75%',
            question: 'Jaką rolę pełni klucz obcy (FOREIGN KEY) w relacyjnej bazie danych?',
            options: [
                { letter: 'A', text: 'Szyfruje wybrane kolumny w tabeli', correct: false, explanation: 'Klucze obce nie służą do kryptografii ani szyfrowania tabel.' },
                { letter: 'B', text: 'Łączy tabele i wymusza integralność referencyjną', correct: true, explanation: 'Świetnie! Klucz obcy definiuje relację pomiędzy tabelami i zapobiega powstawaniu osieroconych rekordów.' },
                { letter: 'C', text: 'Automatycznie usuwa zduplikowane rekordy', correct: false, explanation: 'Unikalność wierszy gwarantuje PRIMARY KEY lub klauzula UNIQUE / DISTINCT.' },
                { letter: 'D', text: 'Zmienia dynamicznie typ danych w zapytaniu', correct: false, explanation: 'Konwersja typów danych realizowana jest za pomocą funkcji CAST() lub CONVERT().' }
            ]
        },
        'inf02': {
            badge: 'INF.02 / Sieci komputerowe',
            meter: '85%',
            question: 'Który protokół sieciowy odpowiada za automatyczną konfigurację adresacji IP w sieci LAN?',
            options: [
                { letter: 'A', text: 'DNS (Domain Name System)', correct: false, explanation: 'DNS odpowiada za tłumaczenie nazw domenowych na adresy IP (np. zsem.edu.pl -> IP).' },
                { letter: 'B', text: 'DHCP (Dynamic Host Configuration Protocol)', correct: true, explanation: 'Poprawna odpowiedź! DHCP przydziela hostom adres IP, maskę podsieci, bramę domyślną i serwery DNS.' },
                { letter: 'C', text: 'FTP (File Transfer Protocol)', correct: false, explanation: 'FTP służy do transferu plików pomiędzy klientem a serwerem.' },
                { letter: 'D', text: 'SNMP (Simple Network Management Protocol)', correct: false, explanation: 'SNMP służy do monitorowania i zarządzania urządzeniami sieciowymi.' }
            ]
        },
        'inf04': {
            badge: 'INF.04 / Programowanie aplikacji',
            meter: '65%',
            question: 'Który paradygmat programowania obiektowego pozwala na ukrycie wewnętrznej implementacji klasy przed światem zewnętrznym?',
            options: [
                { letter: 'A', text: 'Polimorfizm (Polymorphism)', correct: false, explanation: 'Polimorfizm to zdolność obiektów różnych klas do reagowania na ten sam komunikat na właściwy sobie sposób.' },
                { letter: 'B', text: 'Enkapsulacja / Hermetyzacja (Encapsulation)', correct: true, explanation: 'Dokładnie! Hermetyzacja ukrywa pola klasy za modyfikatorami dostępu (private/protected) i udostępnia gettery/settery.' },
                { letter: 'C', text: 'Dziedziczenie (Inheritance)', correct: false, explanation: 'Dziedziczenie pozwala tworzyć nowe klasy na bazie klas nadrzędnych przejmując ich cechy i zachowania.' },
                { letter: 'D', text: 'Serializacja (Serialization)', correct: false, explanation: 'Serializacja to przekształcenie obiektu w strumień bajtów w celu zapisu lub transmisji.' }
            ]
        },
        'ee09': {
            badge: 'EE.09 / Tworzenie stron',
            meter: '60%',
            question: 'W języku JavaScript, która metoda dodaje jeden lub więcej elementów na końcu tablicy?',
            options: [
                { letter: 'A', text: 'push()', correct: true, explanation: 'Brawo! Metoda push() dodaje elementy na koniec tablicy i zwraca jej nową długość.' },
                { letter: 'B', text: 'pop()', correct: false, explanation: 'Metoda pop() usuwa ostatni element z tablicy i go zwraca.' },
                { letter: 'C', text: 'shift()', correct: false, explanation: 'Metoda shift() usuwa pierwszy element z tablicy (indeks 0).' },
                { letter: 'D', text: 'unshift()', correct: false, explanation: 'Metoda unshift() dodaje nowe elementy na początku tablicy.' }
            ]
        }
    };

    const stageContainer = document.querySelector('.landing-stage');
    if (stageContainer) {
        const badgeEl = stageContainer.querySelector('.stage-category-label');
        const meterEl = stageContainer.querySelector('.stage-meter span');
        const questionEl = stageContainer.querySelector('.stage-q-title');
        const optionsEl = stageContainer.querySelector('.stage-options');
        const tabs = stageContainer.querySelectorAll('.stage-tab-btn');

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

        function renderQuestion(key) {
            const data = quizQuestions[key];
            if (!data) return;

            if (badgeEl) badgeEl.textContent = data.badge;
            if (meterEl) meterEl.style.width = data.meter;
            if (questionEl) questionEl.textContent = data.question;

            if (feedbackBox) {
                feedbackBox.className = 'stage-feedback';
                feedbackBox.textContent = '';
            }

            if (optionsEl) {
                optionsEl.innerHTML = '';
                data.options.forEach((opt, idx) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'stage-option-btn' + (opt.correct ? ' is-target-correct' : '');
                    btn.setAttribute('data-opt', idx);
                    btn.innerHTML = `<span class="opt-letter">${opt.letter}</span> <span class="opt-text">${opt.text}</span>`;
                    
                    btn.addEventListener('click', function() {
                        // Reset previous choice styling
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

        // Attach click listeners to tabs
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');
                const cat = this.getAttribute('data-cat');
                renderQuestion(cat);
            });
        });

        // Initialize first tab
        renderQuestion('inf03');
    }

    // 2. Format Access Code PIN (uppercase & alphanumeric filter)
    const pinInput = document.getElementById('landingAccessCode');
    if (pinInput) {
        pinInput.addEventListener('input', function() {
            const clean = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (this.value !== clean) {
                this.value = clean;
            }
        });
    }

    // 3. FAQ Accordion exclusive toggle (smooth closing of siblings)
    const faqItems = document.querySelectorAll('.faq-accordion details');
    faqItems.forEach(item => {
        item.addEventListener('toggle', function() {
            if (this.open) {
                faqItems.forEach(other => {
                    if (other !== this && other.open) {
                        other.removeAttribute('open');
                    }
                });
            }
        });
    });

    // 4. Mobile Navigation Toggle & Backdrop Interaction
    const navToggle = document.getElementById('landingNavToggle');
    const mobileMenu = document.getElementById('landingMobileMenu');
    if (navToggle && mobileMenu) {
        function openMobileMenu() {
            navToggle.classList.add('is-active');
            navToggle.setAttribute('aria-expanded', 'true');
            navToggle.setAttribute('aria-label', 'Zamknij menu nawigacji');
            mobileMenu.classList.add('is-open');
            mobileMenu.setAttribute('aria-hidden', 'false');
        }

        function closeMobileMenu() {
            navToggle.classList.remove('is-active');
            navToggle.setAttribute('aria-expanded', 'false');
            navToggle.setAttribute('aria-label', 'Otwórz menu nawigacji');
            mobileMenu.classList.remove('is-open');
            mobileMenu.setAttribute('aria-hidden', 'true');
        }

        navToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = mobileMenu.classList.contains('is-open');
            if (isOpen) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        });

        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (mobileMenu.classList.contains('is-open') && !mobileMenu.contains(e.target) && !navToggle.contains(e.target)) {
                closeMobileMenu();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && mobileMenu.classList.contains('is-open')) {
                closeMobileMenu();
                navToggle.focus();
            }
        });

        // Close menu when a link inside is clicked
        const mobileLinks = mobileMenu.querySelectorAll('a');
        mobileLinks.forEach(link => {
            link.addEventListener('click', function() {
                closeMobileMenu();
            });
        });

        // Auto close on window resize to desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 992 && mobileMenu.classList.contains('is-open')) {
                closeMobileMenu();
            }
        });
    }

    // 5. Intersection Observer for Scroll Reveal Animations
    const animatedElements = document.querySelectorAll('.reveal-on-scroll');
    if ('IntersectionObserver' in window && animatedElements.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        animatedElements.forEach(el => observer.observe(el));
    } else {
        // Fallback for older browsers
        animatedElements.forEach(el => el.classList.add('is-visible'));
    }
});

