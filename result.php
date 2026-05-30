<?php
// Include required files
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Start secure session and require login
startSecureSession();
requireLogin(true);

// Get result_id from GET or session
$result_id = 0;
$guestResultId = '';
if (isGuestMode()) {
    $guestResultId = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_GET['guest'] ?? ($_SESSION['last_guest_result_id'] ?? ''))));
} elseif (isset($_GET['id'])) {
    $result_id = (int)$_GET['id'];
} elseif (isset($_GET['result_id'])) {
    $result_id = (int)$_GET['result_id'];
} elseif (isset($_SESSION['last_result_id'])) {
    $result_id = (int)$_SESSION['last_result_id'];
}

if (!$guestResultId && $result_id <= 0) {
    header('Location: index.php');
    exit;
}

if ($guestResultId) {
    $guestResult = $_SESSION['guest_test_results'][$guestResultId] ?? null;
    if (!$guestResult) {
        header('Location: test.php?setup=1&new=1');
        exit;
    }
    $row = $guestResult['row'];
    $answers = $guestResult['answers'];
} else {
    // Fetch test result for this user using PDO
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT * FROM test_results WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => $result_id, 'user_id' => $user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$row) {
    header('Location: index.php');
    exit;
}

$correctAnswers = (int)($row['correct_answers'] ?? 0);
$total_questions = (int)($row['total_questions'] ?? 40);
$score_percent = (float)($row['score_percent'] ?? 0);
$time_spent = (int)($row['time_spent'] ?? 0);
$mode = $row['mode'] ?? 'exam';
$test_date = $row['test_date'] ?? '';
$wrongAnswers = max(0, $total_questions - $correctAnswers);
$avgTime = $total_questions > 0 ? round($time_spent / $total_questions) : 0;
$performanceLabel = $score_percent >= 90 ? 'Bardzo mocny wynik' : ($score_percent >= 70 ? 'Dobry wynik' : ($score_percent >= 50 ? 'Zaliczone' : 'Do poprawy'));

if (!$guestResultId) {
    // Fetch all answers for this result with question details
    $answers_stmt = $pdo->prepare("
        SELECT ta.question_id, ta.user_answer, ta.correct_answer, ta.is_correct,
               q.question_text, q.category AS question_category
        FROM test_answers ta
        LEFT JOIN questions q ON ta.question_id = q.id
        WHERE ta.result_id = :result_id
        ORDER BY ta.id
    ");
    $answers_stmt->execute(['result_id' => $result_id]);
    $answers = $answers_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fallback: Load questions for full text (handles both DB and JSON seamlessly)
$allQuestions = loadQuestions($pdo);
$questions_map = [];
foreach ($allQuestions as $q) {
    $questions_map[$q['id']] = $q;
}

$answerQualifications = [];
foreach ($answers as &$answer) {
    $questionId = (int)($answer['question_id'] ?? 0);
    $qualification = trim((string)($answer['question_category'] ?? ''));
    if ($qualification === '' && isset($questions_map[$questionId]['category'])) {
        $qualification = trim((string)$questions_map[$questionId]['category']);
    }
    if ($qualification === 'EE.08') $qualification = 'INF.02';
    if ($qualification === 'EE.09') $qualification = 'INF.03';
    $answer['qualification_label'] = $qualification;
    if ($qualification !== '') {
        $answerQualifications[$qualification] = true;
    }
}
unset($answer);
$showAnswerQualifications = count($answerQualifications) > 1;

// Mode labels
$modeLabels = [
    'exam' => ['name' => 'Egzaminacyjny', 'color' => 'primary', 'icon' => 'bi-journal-check'],
    'practice' => ['name' => 'Ćwiczenia', 'color' => 'success', 'icon' => 'bi-pencil'],
    'single' => ['name' => 'Pojedyncze', 'color' => 'info', 'icon' => 'bi-question-circle']
];
$modeInfo = $modeLabels[$mode] ?? ['name' => ucfirst($mode), 'color' => 'secondary', 'icon' => 'bi-file-text'];

// Determine pass/fail
$passed = $score_percent >= 50;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wynik testu - System Testów</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <style>
        .result-hero {
            background: <?php echo $passed ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)' : 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)'; ?>;
            color: white;
            border-radius: 24px;
            padding: 3rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        .result-insights {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }
        .result-insight-card {
            border-radius: 20px;
            padding: 1.25rem;
            background: #fff;
            border: 1px solid rgba(148, 163, 184, .22);
            box-shadow: 0 12px 32px rgba(15, 23, 42, .06);
        }
        .result-insight-card i {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(59, 130, 246, .1);
            color: var(--primary-color-dark);
            margin-bottom: .85rem;
        }
        .answer-filter-bar {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
        }
        .answer-filter-bar .btn {
            white-space: nowrap;
        }
        .answer-filter-bar .btn.active {
            background: var(--primary-color, var(--primary-color));
            color: #fff;
        }
        .detailed-answers-panel table {
            min-width: 680px;
        }
        body.dark-mode .result-insight-card {
            background: #1e293b;
            border-color: #334155;
        }
        @media (max-width: 991.98px) {
            .result-hero { padding: 1.5rem; }
            .result-insights { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 575.98px) {
            .score-circle { width: 140px; height: 140px; }
            .score-value { font-size: 2.5rem; }
            .result-insights { grid-template-columns: 1fr; }
            .detailed-answers-panel {
                padding: 1rem !important;
            }
            .detailed-answers-panel .panel-header > .d-flex {
                flex-direction: column;
                align-items: stretch !important;
                gap: .75rem;
            }
            .detailed-answers-panel .panel-header .d-flex.align-items-center.gap-2 {
                min-width: 0;
            }
            .detailed-answers-panel .panel-title {
                font-size: .95rem;
                line-height: 1.2;
            }
            .answer-filter-bar {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                width: 100%;
                gap: .4rem;
            }
            .answer-filter-bar .btn {
                min-width: 0;
                padding: .38rem .35rem;
                font-size: .7rem;
                line-height: 1.1;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .detailed-answers-panel .table-responsive {
                margin: 0 -1rem -1rem;
                padding: 0 1rem 1rem;
            }
        }
        .result-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            transform: rotate(30deg);
        }
        .score-circle {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 4px solid rgba(255, 255, 255, 0.3);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .score-circle:hover {
            transform: scale(1.05);
        }
        .score-value {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1;
        }
        .score-label {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
            font-weight: 600;
        }
        .stat-pill {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 0.75rem 1.25rem;
            border-radius: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            backdrop-filter: blur(5px);
        }
        .stat-pill i {
            font-size: 1.5rem;
            opacity: 0.9;
        }
        .answer-row {
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }
        .answer-row:hover {
            background-color: rgba(var(--primary-rgb), 0.02) !important;
            border-left-color: var(--primary-color);
        }
        .answer-status {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .answer-correct {
            background: #dcfce7;
            color: #15803d;
        }
        .answer-wrong {
            background: #fee2e2;
            color: #b91c1c;
        }
        .animate-in {
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .question-text {
            color: #1e293b;
            font-weight: 500;
            line-height: 1.5;
        }
        .badge-answer {
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 1rem;
            padding: 0.5rem 1rem;
            border-radius: 10px;
        }
    </style>
</head>
<body>

    <div class="dashboard-layout">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-container">
            <?php include 'includes/topbar.php'; ?>

            <main role="main" class="content-body">
                <div class="container-fluid p-0">
                    <!-- Result Hero -->
                    <div class="result-hero mb-4 animate-in">
                        <div class="row align-items-center">
                            <div class="col-lg-8">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                    <span class="badge" style="background-color: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                                        <i class="bi <?php echo $modeInfo['icon']; ?> me-1"></i>
                                        Tryb: <?php echo htmlspecialchars($modeInfo['name']); ?>
                                    </span>
                                    <span class="badge" style="background-color: rgba(255,255,255,0.2); backdrop-filter: blur(10px);">
                                        <?php echo $passed ? '<i class="bi bi-check-circle-fill me-1"></i>Zaliczony' : '<i class="bi bi-x-circle-fill me-1"></i>Niezaliczony'; ?>
                                    </span>
                                </div>
                                <h1 class="display-4 fw-800 mb-3">
                                    <?php echo htmlspecialchars($performanceLabel); ?>
                                </h1>
                                <p class="lead opacity-90 mb-4" style="font-weight: 500;">
                                    <?php echo $passed 
                                        ? "Świetny wynik! Rozwiązałeś arkusz z sukcesem, zdobywając $correctAnswers na $total_questions punktów."
                                        : "Tym razem się nie udało, ale każdy błąd to lekcja. Zdobyłeś $correctAnswers na $total_questions punktów."; ?>
                                </p>
                                
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="stat-pill">
                                        <i class="bi bi-stopwatch"></i>
                                        <div>
                                            <div class="small opacity-80" style="font-size: 0.7rem; text-transform: uppercase;">Czas trwania</div>
                                            <div class="fw-bold"><?php echo formatTime($time_spent); ?></div>
                                        </div>
                                    </div>
                                    <div class="stat-pill">
                                        <i class="bi bi-check2-all"></i>
                                        <div>
                                            <div class="small opacity-80" style="font-size: 0.7rem; text-transform: uppercase;">Poprawność</div>
                                            <div class="fw-bold"><?php echo $correctAnswers; ?> / <?php echo $total_questions; ?></div>
                                        </div>
                                    </div>
                                    <div class="stat-pill">
                                        <i class="bi bi-calendar-event"></i>
                                        <div>
                                            <div class="small opacity-80" style="font-size: 0.7rem; text-transform: uppercase;">Data wykonania</div>
                                            <div class="fw-bold"><?php echo !empty($test_date) ? date('d.m.Y H:i', strtotime($test_date)) : '-'; ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 d-flex justify-content-center mt-5 mt-lg-0">
                                <div class="score-circle">
                                    <span class="score-value"><?php echo round($score_percent); ?>%</span>
                                    <span class="score-label">Twój wynik</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action buttons -->
                    <div class="d-flex flex-wrap gap-3 mb-4 animate-in" style="animation-delay: 0.1s;">
                        <a href="test.php?setup=1" class="btn btn-primary btn-lg rounded-pill px-4">
                            <i class="bi bi-plus-circle me-2"></i>Nowy test
                        </a>
                        <a href="index.php" class="btn btn-outline-dark btn-lg rounded-pill px-4">
                            <i class="bi bi-grid-fill me-2"></i>Dashboard
                        </a>
                        <a href="progress.php" class="btn btn-outline-dark btn-lg rounded-pill px-4">
                            <i class="bi bi-clock-history me-2"></i>Historia
                        </a>
                    </div>

                    <div class="result-insights mb-4 animate-in" style="animation-delay: 0.15s;">
                        <div class="result-insight-card">
                            <i class="bi bi-check2-circle"></i>
                            <div class="h4 fw-bold mb-0 text-success"><?php echo $correctAnswers; ?></div>
                            <div class="text-muted small">Poprawne odpowiedzi</div>
                        </div>
                        <div class="result-insight-card">
                            <i class="bi bi-x-circle"></i>
                            <div class="h4 fw-bold mb-0 text-danger"><?php echo $wrongAnswers; ?></div>
                            <div class="text-muted small">Błędne odpowiedzi</div>
                        </div>
                        <div class="result-insight-card">
                            <i class="bi bi-speedometer2"></i>
                            <div class="h4 fw-bold mb-0"><?php echo formatTime($avgTime); ?></div>
                            <div class="text-muted small">Średnio na pytanie</div>
                        </div>
                        <div class="result-insight-card">
                            <i class="bi bi-bullseye"></i>
                            <div class="h4 fw-bold mb-0"><?php echo round($score_percent, 1); ?>%</div>
                            <div class="text-muted small">Skuteczność</div>
                        </div>
                    </div>

                    <!-- Detailed Answers -->
                    <?php if (!empty($answers)): ?>
                    <div class="dashboard-panel detailed-answers-panel animate-in" style="animation-delay: 0.2s;">
                        <div class="panel-header mb-0">
                            <div class="d-flex align-items-center justify-content-between w-100">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-list-stars text-primary fs-4"></i>
                                    <h5 class="panel-title mb-0">Szczegółowa analiza odpowiedzi</h5>
                                </div>
                                <div class="answer-filter-bar">
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill active" data-answer-filter="all">Wszystkie</button>
                                    <button type="button" class="btn btn-sm btn-outline-success rounded-pill" data-answer-filter="correct">Poprawne</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" data-answer-filter="wrong">Błędne</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr class="text-muted small uppercase fw-bold">
                                            <th class="ps-4" style="width: 60px;">#</th>
                                            <th>Treść pytania</th>
                                            <th class="text-center" style="width: 140px;">Twoja odp.</th>
                                            <th class="text-center" style="width: 140px;">Poprawna</th>
                                            <th class="pe-4 text-end" style="width: 100px;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($answers as $index => $answer): ?>
                                            <?php
                                            $user_answer = strtoupper(trim((string)($answer['user_answer'] ?? '')));
                                            $user_answer = $user_answer !== '' ? $user_answer : '-';
                                            $correct_answer = strtoupper(trim((string)($answer['correct_answer'] ?? '')));
                                            $is_correct = ((int)($answer['is_correct'] ?? 0) === 1) || ($user_answer !== '-' && $correct_answer !== '' && $user_answer === $correct_answer);
                                            
                                            $question_text = $answer['question_text'] ?? '';
                                            if (empty($question_text) && !empty($questions_map[$answer['question_id']])) {
                                                $question_text = $questions_map[$answer['question_id']]['question_text'] ?? '';
                                            }
                                            ?>
                                            <tr class="answer-row" data-answer-state="<?php echo $is_correct ? 'correct' : 'wrong'; ?>" style="cursor: pointer;" onclick="viewQuestion(<?php echo (int)$answer['question_id']; ?>, '<?php echo addslashes($user_answer); ?>', '<?php echo addslashes($correct_answer); ?>')">
                                                <td class="ps-4 text-muted fw-bold"><?php echo sprintf('%02d', $index + 1); ?></td>
                                                <td>
                                                    <div class="question-text"><?php echo htmlspecialchars($question_text); ?></div>
                                                    <?php if ($showAnswerQualifications && !empty($answer['qualification_label'])): ?>
                                                        <span class="badge bg-primary bg-opacity-10 text-primary mt-2">
                                                            Kwalifikacja: <?php echo htmlspecialchars($answer['qualification_label']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge-answer fw-800 <?php echo $is_correct ? 'text-success' : 'text-danger'; ?>" style="background: <?php echo $is_correct ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>;">
                                                        <?php echo htmlspecialchars($user_answer); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge-answer fw-800 text-success" style="background: rgba(16, 185, 129, 0.1);">
                                                        <?php echo htmlspecialchars($correct_answer); ?>
                                                    </span>
                                                </td>
                                                <td class="pe-4 text-end">
                                                    <span class="answer-status <?php echo $is_correct ? 'answer-correct' : 'answer-wrong'; ?>">
                                                        <i class="bi <?php echo $is_correct ? 'bi-check-lg' : 'bi-x-lg'; ?>"></i>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Question Detail Modal -->
    <div class="modal fade" id="questionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; background-color: var(--panel-bg); color: var(--text-main);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalQuestionTitle">Podgląd pytania</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="modalQuestionText" class="h4 fw-medium mb-4" style="line-height: 1.5;"></div>
                    <div id="modalQuestionMeta" class="mb-4 d-none"></div>
                    <div id="modalImageContainer" class="mb-4 text-center d-none">
                        <img id="modalQuestionImage" src="" class="img-fluid rounded shadow-sm" alt="Ilustracja do pytania" loading="lazy" decoding="async">
                    </div>
                    <div id="modalAnswersContainer" class="d-flex flex-column gap-3">
                        <!-- Options will be injected here -->
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">Zamknij</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        const questionsData = <?php echo json_encode($questions_map); ?>;
        const showAnswerQualifications = <?php echo $showAnswerQualifications ? 'true' : 'false'; ?>;
        const questionModal = new bootstrap.Modal(document.getElementById('questionModal'));

        document.querySelectorAll('[data-answer-filter]').forEach(button => {
            button.addEventListener('click', () => {
                const filter = button.dataset.answerFilter;
                document.querySelectorAll('[data-answer-filter]').forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                document.querySelectorAll('.answer-row').forEach(row => {
                    row.hidden = filter !== 'all' && row.dataset.answerState !== filter;
                });
            });
        });

        function viewQuestion(id, userAns, correctAns) {
            const q = questionsData[id];
            if (!q) return;

            document.getElementById('modalQuestionText').innerText = q.question_text;
            const meta = document.getElementById('modalQuestionMeta');
            if (showAnswerQualifications && q.category) {
                meta.innerHTML = '<span class="badge bg-primary bg-opacity-10 text-primary">Kwalifikacja: ' + String(q.category).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char])) + '</span>';
                meta.classList.remove('d-none');
            } else {
                meta.innerHTML = '';
                meta.classList.add('d-none');
            }
            
            const imgContainer = document.getElementById('modalImageContainer');
            const img = document.getElementById('modalQuestionImage');
            if (q.image_url) {
                img.src = q.image_url;
                img.alt = 'Ilustracja do pytania: ' + (q.question_text || 'pytanie testowe').slice(0, 90);
                imgContainer.classList.remove('d-none');
            } else {
                img.removeAttribute('src');
                img.alt = 'Ilustracja do pytania';
                imgContainer.classList.add('d-none');
            }

            const container = document.getElementById('modalAnswersContainer');
            container.innerHTML = '';

            const options = {
                'A': q.option_a || q.a,
                'B': q.option_b || q.b,
                'C': q.option_c || q.c,
                'D': q.option_d || q.d
            };

            for (const [key, text] of Object.entries(options)) {
                if (!text || text.trim() === '') continue;

                const div = document.createElement('div');
                div.className = 'd-flex align-items-center p-3 rounded border-2 mb-2';
                
                let borderColor = 'var(--border-color)';
                let bgColor = 'var(--bg-color)';
                let icon = '';

                if (key === correctAns) {
                    borderColor = '#10b981';
                    bgColor = 'rgba(16, 185, 129, 0.2)';
                    icon = '<i class="bi bi-check-circle-fill text-success ms-auto"></i>';
                } else if (key === userAns) {
                    borderColor = '#ef4444';
                    bgColor = 'rgba(239, 68, 68, 0.2)';
                    icon = '<i class="bi bi-x-circle-fill text-danger ms-auto"></i>';
                }

                div.style.borderColor = borderColor;
                div.style.backgroundColor = bgColor;
                div.style.color = 'var(--text-main)';

                const keyEl = document.createElement('div');
                keyEl.className = 'fw-bold me-3 text-center';
                keyEl.style.cssText = 'width: 30px; height: 30px; line-height: 26px; border: 2px solid currentColor; border-radius: 50%; flex-shrink: 0;';
                keyEl.textContent = key;

                const textEl = document.createElement('div');
                textEl.className = 'flex-grow-1';
                textEl.textContent = text;

                div.appendChild(keyEl);
                div.appendChild(textEl);
                if (icon) {
                    const iconWrap = document.createElement('span');
                    iconWrap.innerHTML = icon;
                    div.appendChild(iconWrap.firstElementChild);
                }
                container.appendChild(div);
            }

            questionModal.show();
        }
    </script>
</body>
</html>
