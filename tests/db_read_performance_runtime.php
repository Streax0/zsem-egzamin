<?php
require dirname(__DIR__) . '/includes/functions.php';

function checkDbReadPerformance(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class DbReadPerformanceStatementStub extends PDOStatement {
    public string $sql = '';
    public array $params = [];
    public array $rows = [];
    public array|false $row = false;
    public mixed $columnValue = false;
    private DbReadPerformancePdoStub $pdoStub;

    protected function __construct() {}

    public static function create(DbReadPerformancePdoStub $pdo, string $sql): self {
        $statement = new self();
        $statement->pdoStub = $pdo;
        $statement->sql = $sql;
        return $statement;
    }

    public function execute(?array $params = null): bool {
        $this->params = $params ?? [];
        $this->pdoStub->executedSql[] = $this->sql;
        $normalized = preg_replace('/\s+/', ' ', trim($this->sql));

        if (str_contains($normalized, 'FROM INFORMATION_SCHEMA.COLUMNS')) {
            $this->rows = $this->pdoStub->schemaColumns[(string)($this->params[0] ?? '')] ?? [];
        } elseif (str_contains($normalized, 'FROM INFORMATION_SCHEMA.STATISTICS')) {
            $this->rows = $this->pdoStub->schemaIndexes[(string)($this->params[0] ?? '')] ?? [];
        } elseif (str_starts_with($normalized, 'SHOW TABLES') || str_starts_with($normalized, 'SHOW COLUMNS') || str_starts_with($normalized, 'SHOW INDEX')) {
            $this->columnValue = $this->pdoStub->schemaExists ? 1 : false;
            $this->row = $this->pdoStub->schemaExists ? ['exists' => 1] : false;
        } elseif (str_contains($normalized, 'SELECT setting_value FROM app_settings')) {
            $this->columnValue = $this->pdoStub->settings[(string)($this->params[0] ?? '')] ?? false;
        } elseif (str_contains($normalized, 'INSERT INTO app_settings')) {
            $this->pdoStub->settings[(string)$this->params[0]] = (string)$this->params[1];
        } elseif (str_contains($normalized, 'ROW_NUMBER() OVER')) {
            $this->rows = $this->pdoStub->streakRows;
        } elseif (str_contains($normalized, 'FROM test_results tr') && str_contains($normalized, 'COALESCE(SUM(time_spent)')) {
            $this->row = ['total_tests' => 4, 'average_score' => 75.5, 'total_time' => 321];
        } elseif (str_contains($normalized, 'FROM user_question_progress') && str_contains($normalized, 'seen_count')) {
            $this->row = ['mastered_count' => 3, 'seen_count' => 5];
        }

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        return $this->row;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        return $this->rows;
    }

    public function fetchColumn(int $column = 0): mixed {
        return $this->columnValue;
    }
}

final class DbReadPerformancePdoStub extends PDO {
    public array $executedSql = [];
    public array $settings = ['perf_key' => 'alpha'];
    public bool $schemaExists = false;
    public array $schemaTables = [];
    public array $schemaColumns = [];
    public array $schemaIndexes = [];
    public array $streakRows = [
        ['user_id' => 10, 'score_percent' => 92],
        ['user_id' => 10, 'score_percent' => 88],
        ['user_id' => 10, 'score_percent' => 70],
        ['user_id' => 20, 'score_percent' => 40],
        ['user_id' => 20, 'score_percent' => 45],
        ['user_id' => 20, 'score_percent' => 65],
    ];

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false {
        return DbReadPerformanceStatementStub::create($this, $query);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $statement = DbReadPerformanceStatementStub::create($this, $query);
        $statement->execute();
        $normalized = preg_replace('/\s+/', ' ', trim($query));
        if (str_contains($normalized, 'SELECT COUNT(*) FROM ranking_event_templates')) {
            $statement->columnValue = 50;
        } elseif (str_contains($normalized, 'FROM INFORMATION_SCHEMA.TABLES')) {
            $statement->rows = $this->schemaTables;
        } elseif (str_contains($normalized, 'SELECT * FROM questions')) {
            $statement->rows = [[
                'id' => 1,
                'category' => 'INF.03',
                'question_text' => 'Pytanie testowe',
                'option_a' => 'A',
                'option_b' => 'B',
                'option_c' => 'C',
                'option_d' => 'D',
                'correct_answer' => 'A',
                'explanation' => '',
                'image_url' => null,
            ]];
        }
        return $statement;
    }

    public function exec(string $statement): int|false {
        $this->executedSql[] = $statement;
        return 0;
    }
}

function countExecutedSql(DbReadPerformancePdoStub $pdo, string $needle): int {
    return count(array_filter($pdo->executedSql, static fn($sql) => str_contains($sql, $needle)));
}

$pdo = new DbReadPerformancePdoStub();

checkDbReadPerformance(getAppSetting($pdo, 'perf_key', 'fallback') === 'alpha', 'setting read failed');
checkDbReadPerformance(getAppSetting($pdo, 'perf_key', 'fallback') === 'alpha', 'cached setting changed');
checkDbReadPerformance(countExecutedSql($pdo, 'WHERE setting_key = ? LIMIT 1') === 1, 'setting was queried more than once');
checkDbReadPerformance(setAppSetting($pdo, 'perf_key', 'beta'), 'setting write stub failed');
checkDbReadPerformance(getAppSetting($pdo, 'perf_key', 'fallback') === 'beta', 'setting cache was not refreshed after write');
checkDbReadPerformance(countExecutedSql($pdo, 'WHERE setting_key = ? LIMIT 1') === 1, 'setting was re-read after cache refresh');

$pdo->schemaExists = true;
$pdo->schemaTables = ['perf_probe', 'perf_probe_two'];
$pdo->schemaColumns = ['perf_probe' => ['probe_column', 'probe_column_two']];
$pdo->schemaIndexes = ['perf_probe' => ['probe_index', 'probe_index_two']];
$tableProbeBaseline = countExecutedSql($pdo, 'FROM INFORMATION_SCHEMA.TABLES');
checkDbReadPerformance(dbTableExists($pdo, 'perf_probe'), 'table probe failed');
checkDbReadPerformance(dbTableExists($pdo, 'perf_probe_two'), 'second cached table probe failed');
checkDbReadPerformance(!dbTableExists($pdo, 'missing_probe'), 'missing table probe changed');
checkDbReadPerformance(!dbTableExists($pdo, 'missing_probe'), 'missing table result was not cached');
checkDbReadPerformance(countExecutedSql($pdo, 'FROM INFORMATION_SCHEMA.TABLES') === $tableProbeBaseline + 1, 'table metadata was queried more than once');
$columnProbeBaseline = countExecutedSql($pdo, 'FROM INFORMATION_SCHEMA.COLUMNS');
checkDbReadPerformance(dbColumnExists($pdo, 'perf_probe', 'probe_column'), 'column probe failed');
checkDbReadPerformance(dbColumnExists($pdo, 'perf_probe', 'probe_column_two'), 'second cached column probe failed');
checkDbReadPerformance(!dbColumnExists($pdo, 'perf_probe', 'missing_column'), 'missing column probe changed');
checkDbReadPerformance(!dbColumnExists($pdo, 'perf_probe', 'missing_column'), 'missing column result was not cached');
checkDbReadPerformance(countExecutedSql($pdo, 'FROM INFORMATION_SCHEMA.COLUMNS') === $columnProbeBaseline + 1, 'column metadata was queried more than once');
$indexProbeBaseline = countExecutedSql($pdo, 'FROM INFORMATION_SCHEMA.STATISTICS');
checkDbReadPerformance(dbIndexExists($pdo, 'perf_probe', 'probe_index'), 'index probe failed');
checkDbReadPerformance(dbIndexExists($pdo, 'perf_probe', 'probe_index_two'), 'second cached index probe failed');
checkDbReadPerformance(!dbIndexExists($pdo, 'perf_probe', 'missing_index'), 'missing index probe changed');
checkDbReadPerformance(!dbIndexExists($pdo, 'perf_probe', 'missing_index'), 'missing index result was not cached');
checkDbReadPerformance(countExecutedSql($pdo, 'FROM INFORMATION_SCHEMA.STATISTICS') === $indexProbeBaseline + 1, 'index metadata was queried more than once');

$invalidIdentifierRejected = false;
try {
    dbColumnExists($pdo, 'perf_probe', 'probe%');
} catch (InvalidArgumentException $error) {
    $invalidIdentifierRejected = true;
}
checkDbReadPerformance($invalidIdentifierRejected, 'invalid schema identifier was accepted');
checkDbReadPerformance(countExecutedSql($pdo, 'SHOW TABLES') === 0, 'non-preparable SHOW TABLES query is still used');
checkDbReadPerformance(countExecutedSql($pdo, 'SHOW COLUMNS') === 0, 'non-preparable SHOW COLUMNS query is still used');
checkDbReadPerformance(countExecutedSql($pdo, 'SHOW INDEX') === 0, 'non-preparable SHOW INDEX query is still used');

$streaks = getUsersPerformanceStreaks($pdo, [10, 20, 30, 10]);
checkDbReadPerformance(countExecutedSql($pdo, 'ROW_NUMBER() OVER') === 1, 'bulk streak lookup did not use one query');
checkDbReadPerformance($streaks[10]['type'] === 'win' && $streaks[10]['count'] === 2, 'winning streak result changed');
checkDbReadPerformance($streaks[20]['type'] === 'cold' && $streaks[20]['count'] === 2, 'cold streak result changed');
checkDbReadPerformance($streaks[30]['type'] === 'none', 'empty streak result changed');

$testAggregateBaseline = countExecutedSql($pdo, 'FROM test_results tr');
$progressAggregateBaseline = countExecutedSql($pdo, 'FROM user_question_progress');
$stats = getUserStats($pdo, 7);
checkDbReadPerformance($stats['tests_taken'] === 4, 'test count changed');
checkDbReadPerformance($stats['average_score'] === 75.5, 'average score changed');
checkDbReadPerformance($stats['total_time_seconds'] === 321, 'total time changed');
checkDbReadPerformance($stats['mastered_questions'] === 3, 'mastered count changed');
checkDbReadPerformance(countExecutedSql($pdo, 'FROM test_results tr') === $testAggregateBaseline + 1, 'test result aggregates use more than one query');
checkDbReadPerformance(countExecutedSql($pdo, 'FROM user_question_progress') === $progressAggregateBaseline + 1, 'progress aggregates use more than one query');

echo "database read performance runtime OK\n";
