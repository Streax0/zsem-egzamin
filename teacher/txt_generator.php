<?php
require_once '../config/db.php';
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

startSecureSession();
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['teacher', 'admin', 'dyrektor'])) {
    die("Unauthorized");
}
?>
<?php
$pageTitle = 'Generator Bazy Pytań – ZSEM Tech';
$extraCss = ['assets/css/dashboard-new.css'];
$extraHead = <<<HTML
<style>
        .question-entry { border: 1px solid var(--border-color); border-radius: 1rem; padding: 1.5rem; margin-bottom: 1.5rem; background: var(--panel-bg); }
        .sticky-bottom-bar { position: sticky; bottom: 0; background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); padding: 1rem; border-top: 1px solid var(--border-color); z-index: 100; margin: 0 -1.5rem; }
    </style>
HTML;
include '../includes/header.php';
?>
    <div class="dashboard-layout">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-container">
            <?php include '../includes/topbar.php'; ?>
            <main role="main" class="content-body">
                <div class="container-fluid">
                    <div class="row mb-4 align-items-center">
                        <div class="col-md-6">
                            <h2 class="fw-bold mb-0">Generator pliku TXT</h2>
                            <p class="text-muted mb-0">Wygodne narzędzie do tworzenia masowej bazy pytań.</p>
                        </div>
                    </div>

                    <div id="questionsContainer">
                        <!-- Questions will be added here -->
                    </div>

                    <div class="sticky-bottom-bar d-flex justify-content-between align-items-center">
                        <button class="btn btn-outline-primary rounded-pill px-4" onclick="addQuestionEntry()">
                            <i class="bi bi-plus-lg me-2"></i>Dodaj kolejne pytanie
                        </button>
                        <button class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm" onclick="downloadTxt()">
                            <i class="bi bi-download me-2"></i>POBIERZ PLIK .TXT
                        </button>
                    </div>
                </div>
            </main>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        let qCount = 0;

        function addQuestionEntry() {
            qCount++;
            const html = `
                <div class="question-entry animate-in" id="q_${qCount}">
                    <div class="d-flex justify-content-between mb-3">
                        <h5 class="fw-bold text-primary mb-0">Pytanie #${qCount}</h5>
                        <button class="btn btn-sm btn-outline-danger border-0" onclick="removeEntry(${qCount})"><i class="bi bi-trash"></i></button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small text-muted text-uppercase fw-bold">Kategoria</label>
                            <input type="text" class="form-control q-category" placeholder="np. Systemy Operacyjne">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small text-muted text-uppercase fw-bold">Treść pytania</label>
                            <input type="text" class="form-control q-text" placeholder="Wpisz treść pytania...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted text-uppercase fw-bold">Opcja A</label>
                            <input type="text" class="form-control q-a" placeholder="Odpowiedź A">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted text-uppercase fw-bold">Opcja B</label>
                            <input type="text" class="form-control q-b" placeholder="Odpowiedź B">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted text-uppercase fw-bold">Opcja C</label>
                            <input type="text" class="form-control q-c" placeholder="Odpowiedź C">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted text-uppercase fw-bold">Opcja D</label>
                            <input type="text" class="form-control q-d" placeholder="Odpowiedź D">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small text-muted text-uppercase fw-bold">Poprawna (A/B/C/D)</label>
                            <select class="form-select q-correct">
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted text-uppercase fw-bold">URL zdjęcia (opcjonalnie)</label>
                            <input type="text" class="form-control q-image" placeholder="https://...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted text-uppercase fw-bold">Wyjaśnienie (opcjonalnie)</label>
                            <input type="text" class="form-control q-explanation" placeholder="Dlaczego ta odpowiedź jest poprawna?">
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('questionsContainer').insertAdjacentHTML('beforeend', html);
        }

        function removeEntry(id) {
            document.getElementById(`q_${id}`).remove();
        }

        function downloadTxt() {
            const entries = document.querySelectorAll('.question-entry');
            let content = "# Generator pliku TXT - ZSEM Tech\n# Format: kategoria;pytanie;A;B;C;D;poprawna;obrazek;wyjasnienie\n\n";
            
            entries.forEach(e => {
                const cat = e.querySelector('.q-category').value.trim();
                const text = e.querySelector('.q-text').value.trim();
                const a = e.querySelector('.q-a').value.trim();
                const b = e.querySelector('.q-b').value.trim();
                const c = e.querySelector('.q-c').value.trim();
                const d = e.querySelector('.q-d').value.trim();
                const correct = e.querySelector('.q-correct').value;
                const img = e.querySelector('.q-image').value.trim();
                const exp = e.querySelector('.q-explanation').value.trim();

                if (text && a && b && c && d) {
                    content += `${cat};${text};${a};${b};${c};${d};${correct};${img};${exp}\n`;
                }
            });

            const blob = new Blob([content], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `baza_pytan_${new Date().toISOString().slice(0,10)}.txt`;
            a.click();
        }

        // Add first entry on load
        addQuestionEntry();
    </script>
</body>
</html>
