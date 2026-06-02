<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();

// Load dictionary data
$dictionaryFile = __DIR__ . '/data/dictionary.json';
$dictionaryData = [];
if (file_exists($dictionaryFile)) {
    $json = file_get_contents($dictionaryFile);
    $dictionaryData = json_decode($json, true) ?? [];
}

// Get unique qualifications
$qualifications = [];
foreach ($dictionaryData as $group) {
    if (!in_array($group['qualification'], $qualifications)) {
        $qualifications[] = $group['qualification'];
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Słownik pojęć – System Edukacyjny INF.02</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl('assets/css/dashboard-new.css')); ?>">
    <script src="<?php echo htmlspecialchars(assetUrl('assets/js/theme-handler.js')); ?>"></script>
    <style>
        /* CSS Variables for backward compatibility */
        :root {
            --bg-color-hover: #f3f4f6;
            --danger-color: #ef4444;
        }
        
        body.dark-mode {
            --bg-color-hover: #334155;
        }
        
        .dict-term-card {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border-left: 4px solid var(--primary-color);
            border-radius: 12px;
            position: relative;
            min-height: 100%;
            overflow: hidden;
        }
        .dict-term-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
            border-left-width: 6px;
        }
        .term-title {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 0.5rem;
            padding-right: 3rem; /* Space for badge */
            overflow-wrap: anywhere;
        }
        .qual-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.35em 0.65em;
            border-radius: 50rem;
            background-color: var(--bg-color-hover);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }
        
        /* Sticky Header */
        .sticky-filters {
            position: sticky;
            top: calc(70px + 0.5rem);
            z-index: 20;
            background: color-mix(in srgb, var(--bg-color) 92%, transparent);
            backdrop-filter: blur(14px);
            padding: 0.75rem 0;
            margin-bottom: 1.75rem;
            border-bottom: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        .sticky-filters > * {
            pointer-events: auto;
        }
        
        /* When scrolled, we can add a very subtle floating effect to the children, not the whole block */
        .sticky-filters.scrolled .search-row-container {
            padding: 0.5rem;
            background: var(--panel-bg);
            backdrop-filter: blur(10px);
            border-radius: 1.2rem;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        /* Search Bar */
        .search-wrapper {
            position: relative;
        }
        .search-wrapper input {
            border-radius: 1rem;
            padding-left: 3.2rem;
            padding-right: 3rem;
            height: 52px;
            font-size: 1.05rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05) !important;
            background-color: var(--panel-bg) !important;
            color: var(--text-main) !important;
            border-color: var(--border-color) !important;
        }
        .search-icon-left {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            z-index: 5;
        }
        .clear-search {
            position: absolute;
            right: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 0;
            z-index: 5;
            cursor: pointer;
            display: none;
            transition: color 0.2s;
        }
        .clear-search:hover { color: var(--danger-color); }

        /* Filter Toggle Button */
        .filter-toggle-btn {
            border-radius: 1rem;
            font-weight: 600;
            height: 52px;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05) !important;
        }

        /* Filter Panel */
        .filter-panel-card {
            background-color: var(--panel-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        /* Modern Filter Buttons */
        .filter-btn {
            border-radius: 50rem;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.4rem 1rem;
            transition: all 0.2s;
            border: none;
            background: var(--bg-color-hover);
            color: var(--text-main);
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .filter-btn:hover {
            transform: translateY(-1px);
            background: var(--border-color);
        }
        .filter-btn.active {
            background-color: #0d6efd !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3) !important;
        }

        /* Modern Letter Buttons */
        .letter-btn {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            background: var(--bg-color-hover);
            color: var(--text-muted);
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .letter-btn:hover {
            color: #0d6efd;
            background: var(--border-color);
            transform: translateY(-2px);
        }
        .letter-btn.active {
            background-color: #0d6efd !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3) !important;
            transform: translateY(-2px);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-js {
            animation: fadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        
        .qualification-header {
            color: var(--text-main);
            font-weight: 800;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
        }
        .dict-term-card p,
        .dict-term-card .text-muted,
        .dict-term-card span {
            overflow-wrap: anywhere;
        }
        .dict-quality-note {
            border-radius: 18px;
            padding: 1rem;
            background: linear-gradient(135deg, rgba(59,130,246,.10), rgba(14,165,233,.06));
            border: 1px solid rgba(59,130,246,.16);
        }
    </style>
</head>
<body>

    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include 'includes/topbar.php'; ?>

            <main role="main" class="content-body position-relative">
                <div class="container-fluid px-0">
                    
                    <div class="d-flex justify-content-between align-items-center mb-2 animate-in">
                        <div>
                            <h2 class="h3 fw-bold mb-1 text-main"><i class="bi bi-book text-primary me-2"></i>Słownik Pojęć</h2>
                            <p class="text-muted mb-0">Zrozum najważniejsze terminy techniczne i znajdź materiały wideo.</p>
                        </div>
                    </div>
                    <div class="dict-quality-note mb-3 animate-in">
                        <div class="fw-bold"><i class="bi bi-lightbulb text-primary me-2"></i>Jak korzystać ze słownika</div>
                        <div class="small text-muted">Szukaj po konkretnym haśle, filtruj kwalifikację i sprawdzaj przykłady. Karty pilnują długich definicji, żeby tekst nie wychodził poza układ.</div>
                        <a href="flashcards.php" class="btn btn-sm btn-primary rounded-pill mt-3"><i class="bi bi-card-text me-1"></i>Tryb fiszek</a>
                    </div>

                    <!-- Sticky Filters Section -->
                    <div class="sticky-filters animate-in" style="animation-delay: 0.1s;">
                        
                        <div class="search-row-container">
                            <!-- Search Row -->
                            <div class="d-flex gap-2 align-items-center">
                                <div class="search-wrapper flex-grow-1">
                                    <i class="bi bi-search search-icon-left fs-5"></i>
                                    <input type="text" id="searchInput" class="form-control bg-white border-0" placeholder="Zacznij pisać, aby przefiltrować pojęcia...">
                                    <button type="button" class="clear-search" id="clearSearchBtn" title="Wyczyść"><i class="bi bi-x-lg fs-5"></i></button>
                                </div>
                                <button class="btn btn-primary filter-toggle-btn px-3 px-md-4 d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#filterPanel" aria-expanded="false" aria-controls="filterPanel">
                                    <i class="bi bi-sliders"></i> <span class="d-none d-md-inline">Filtry</span>
                                </button>
                            </div>
                        </div>

                        <!-- Collapsible Filter Panel -->
                        <div class="collapse" id="filterPanel">
                            <div class="card card-body filter-panel-card mt-3">
                                <div class="row g-4">
                                    <div class="col-12 col-xl-auto flex-xl-grow-1">
                                        <h6 class="text-muted fw-bold small mb-3 text-uppercase"><i class="bi bi-tags me-1"></i> Kwalifikacje</h6>
                                        <div class="d-flex flex-wrap gap-2" id="qualFilters">
                                            <button class="filter-btn active" data-qual="All">Wszystkie</button>
                                            <?php foreach ($qualifications as $qual): ?>
                                                <button class="filter-btn" data-qual="<?= htmlspecialchars($qual) ?>"><?= htmlspecialchars($qual) ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="col-12 col-xl-auto">
                                        <h6 class="text-muted fw-bold small mb-3 text-uppercase"><i class="bi bi-sort-alpha-down me-1"></i> Alfabet</h6>
                                        <div class="d-flex flex-wrap gap-1" id="azFilters" style="max-width: 600px;">
                                            <button class="letter-btn active" data-letter="All" style="width: auto; padding: 0 12px;">A-Z</button>
                                            <?php 
                                            $alphabet = range('A', 'Z');
                                            foreach ($alphabet as $letter): 
                                            ?>
                                                <button class="letter-btn" data-letter="<?= $letter ?>"><?= $letter ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Results Container -->
                    <div id="noResults" class="text-center py-5 d-none">
                        <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-muted">Brak pojęć spełniających podane kryteria</h5>
                        <p class="text-muted small">Spróbuj zmienić słowo kluczowe lub zresetuj wybrane filtry.</p>
                        <button class="btn btn-primary rounded-pill mt-2 px-4" id="resetFiltersBtnEmpty"><i class="bi bi-arrow-counterclockwise me-2"></i>Resetuj filtry</button>
                    </div>

                    <div id="dictResults" class="pb-5">
                        <!-- Categories and Cards will be injected here via JavaScript -->
                    </div>
                    <div class="text-center mb-5 d-none" id="dictLoadMoreWrap">
                        <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-bold" id="dictLoadMore">
                            <i class="bi bi-plus-circle me-2"></i>Załaduj więcej pojęć
                        </button>
                    </div>

                </div>
            </main>
            <?php include 'includes/footer.php'; ?>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Data Preparation
    const rawData = <?= json_encode($dictionaryData) ?>;
    let allTerms = [];
    
    rawData.forEach(group => {
        group.terms.forEach(term => {
            allTerms.push({
                qualification: group.qualification,
                term: term.term,
                definition: term.definition,
                example: term.example || '',
                link: term.link || ''
            });
        });
    });

    allTerms.sort((a, b) => a.term.localeCompare(b.term, 'pl'));

    // 2. State Variables
    let currentSearch = '';
    let currentQual = 'All';
    let currentLetter = 'All';
    let visibleLimit = 60;
    const pageSize = 60;

    // 3. DOM Elements
    const searchInput = document.getElementById('searchInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const qualBtns = document.querySelectorAll('#qualFilters .filter-btn');
    const letterBtns = document.querySelectorAll('#azFilters .letter-btn');
    const resultsContainer = document.getElementById('dictResults');
    const noResultsMsg = document.getElementById('noResults');
    const resetBtnEmpty = document.getElementById('resetFiltersBtnEmpty');
    const stickyMenu = document.querySelector('.sticky-filters');
    const loadMoreWrap = document.getElementById('dictLoadMoreWrap');
    const loadMoreBtn = document.getElementById('dictLoadMore');
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[ch]));

    // Handle Sticky Menu Animation on Scroll
    window.addEventListener('scroll', function() {
        if (window.scrollY > 20) {
            stickyMenu.classList.add('scrolled');
        } else {
            stickyMenu.classList.remove('scrolled');
        }
    });

    // 4. Render Function
    function render() {
        if (currentSearch.length > 0) {
            clearSearchBtn.style.display = 'block';
        } else {
            clearSearchBtn.style.display = 'none';
        }

        const filteredTerms = allTerms.filter(item => {
            // Fix: Search only in term title to prevent random definition matches
            const searchMatch = currentSearch === '' || 
                                item.term.toLowerCase().includes(currentSearch);
            
            const qualMatch = currentQual === 'All' || item.qualification === currentQual;

            let letterMatch = true;
            if (currentLetter !== 'All') {
                const firstLetter = item.term.charAt(0).toUpperCase();
                letterMatch = (firstLetter === currentLetter);
            }

            return searchMatch && qualMatch && letterMatch;
        });

        resultsContainer.innerHTML = '';

        if (filteredTerms.length === 0) {
            noResultsMsg.classList.remove('d-none');
            loadMoreWrap.classList.add('d-none');
        } else {
            noResultsMsg.classList.add('d-none');
            loadMoreWrap.classList.toggle('d-none', filteredTerms.length <= visibleLimit);
            
            const grouped = {};
            filteredTerms.slice(0, visibleLimit).forEach(item => {
                if (!grouped[item.qualification]) {
                    grouped[item.qualification] = [];
                }
                grouped[item.qualification].push(item);
            });

            let globalIndex = 0; 
            const sortedQuals = Object.keys(grouped).sort();

            sortedQuals.forEach(qual => {
                const headerHtml = `
                <div class="qualification-header fade-in-js" style="animation-delay: ${Math.min(globalIndex * 0.02, 0.3)}s;">
                    <i class="bi bi-tags-fill me-3 text-primary"></i> ${qual}
                </div>
                <div class="row g-4 mb-5 qual-group-container"></div>
                `;
                resultsContainer.insertAdjacentHTML('beforeend', headerHtml);
                
                const currentGroupContainer = resultsContainer.querySelectorAll('.qual-group-container');
                const targetContainer = currentGroupContainer[currentGroupContainer.length - 1];

                grouped[qual].forEach(item => {
                    globalIndex++;
                    const ytQuery = encodeURIComponent(item.term + ' informatyka');
                    const ytLink = `https://www.youtube.com/results?search_query=${ytQuery}`;

                    let exampleHtml = '';
                    if (item.example !== '') {
                        exampleHtml = `
                        <div class="bg-light p-2 rounded small mb-3 border border-secondary border-opacity-25" style="background-color: var(--bg-color-hover) !important;">
                            <strong class="text-primary"><i class="bi bi-lightbulb-fill me-1"></i> Przykład:</strong> <br>
                            <span class="text-muted">${escapeHtml(item.example)}</span>
                        </div>`;
                    }

                    let linkHtml = '';
                    if (item.link !== '') {
                        linkHtml = `
                        <a href="${escapeHtml(item.link)}" target="_blank" class="btn btn-sm btn-outline-primary flex-grow-1" rel="noopener">
                            <i class="bi bi-wikipedia"></i> Wikipedia
                        </a>`;
                    }

                    const cardHtml = `
                    <div class="col-md-6 col-xl-4 fade-in-js" style="animation-delay: ${Math.min(globalIndex * 0.02, 0.4)}s;">
                        <div class="card h-100 border-0 shadow-sm dict-term-card p-3 d-flex flex-column" style="background-color: var(--bg-color);">
                            <span class="qual-badge">${escapeHtml(item.qualification)}</span>
                            <div class="card-body p-0 d-flex flex-column h-100">
                                <h5 class="term-title">${escapeHtml(item.term)}</h5>
                                <p class="text-muted small mb-3 flex-grow-1">
                                    ${escapeHtml(item.definition)}
                                </p>
                                
                                ${exampleHtml}
                                
                                <div class="d-flex gap-2 mt-auto">
                                    ${linkHtml}
                                    <a href="${ytLink}" target="_blank" class="btn btn-sm btn-outline-danger flex-grow-1" title="Szukaj poradnika na YouTube">
                                        <i class="bi bi-youtube"></i> YouTube
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    `;
                    targetContainer.insertAdjacentHTML('beforeend', cardHtml);
                });
            });
        }
    }

    // 5. Event Listeners
    searchInput.addEventListener('input', function(e) {
        currentSearch = e.target.value.toLowerCase().trim();
        visibleLimit = pageSize;
        render();
    });

    clearSearchBtn.addEventListener('click', function() {
        searchInput.value = '';
        currentSearch = '';
        visibleLimit = pageSize;
        render();
        searchInput.focus();
    });

    // Toggle behavior for Qualification filters
    qualBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const clickedQual = this.getAttribute('data-qual');
            
            // If clicking already active button (and it's not 'All'), deselect it
            if (this.classList.contains('active') && clickedQual !== 'All') {
                this.classList.remove('active');
                document.querySelector('#qualFilters .filter-btn[data-qual="All"]').classList.add('active');
                currentQual = 'All';
            } else {
                qualBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentQual = clickedQual;
            }
            visibleLimit = pageSize;
            render();
        });
    });

    // Toggle behavior for A-Z filters
    letterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const clickedLetter = this.getAttribute('data-letter');
            
            // If clicking already active button (and it's not 'All'), deselect it
            if (this.classList.contains('active') && clickedLetter !== 'All') {
                this.classList.remove('active');
                document.querySelector('#azFilters .letter-btn[data-letter="All"]').classList.add('active');
                currentLetter = 'All';
            } else {
                letterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentLetter = clickedLetter;
            }
            visibleLimit = pageSize;
            render();
        });
    });

    function resetAllFilters() {
        currentSearch = '';
        currentQual = 'All';
        currentLetter = 'All';
        visibleLimit = pageSize;
        
        searchInput.value = '';
        
        qualBtns.forEach(b => b.classList.remove('active'));
        document.querySelector('#qualFilters .filter-btn[data-qual="All"]').classList.add('active');
        
        letterBtns.forEach(b => b.classList.remove('active'));
        document.querySelector('#azFilters .letter-btn[data-letter="All"]').classList.add('active');
        
        render();
    }

    resetBtnEmpty.addEventListener('click', resetAllFilters);
    loadMoreBtn.addEventListener('click', function() {
        visibleLimit += pageSize;
        render();
    });

    // Initial render
    render();
});
</script>
</body>
</html>
