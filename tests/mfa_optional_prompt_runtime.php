<?php
require dirname(__DIR__) . '/includes/functions.php';

function checkMfaPrompt(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

final class MfaPromptStatementStub extends PDOStatement {
    public string $sql = '';
    public array $bindings = [];
    public array $params = [];
    public mixed $columnValue = false;
    public array $rows = [];

    protected function __construct() {}

    public static function create(string $sql): self {
        $statement = new self();
        $statement->sql = $sql;
        return $statement;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool {
        $this->bindings[$param] = ['value' => $value, 'type' => $type];
        return true;
    }

    public function execute(?array $params = null): bool {
        $this->params = $params ?? [];
        return true;
    }

    public function fetchColumn(int $column = 0): mixed {
        return $this->columnValue;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        return $this->rows;
    }
}

final class MfaPromptPdoStub extends PDO {
    /** @var MfaPromptStatementStub[] */
    public array $statements = [];

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false {
        $statement = MfaPromptStatementStub::create($query);
        if (str_contains($query, "type = 'mfa_optional_prompt'")) {
            $statement->columnValue = 91;
        } elseif (str_contains($query, 'SELECT COUNT(*) FROM notifications')) {
            $statement->columnValue = 3;
        }
        $this->statements[] = $statement;
        return $statement;
    }
}

$pdo = new MfaPromptPdoStub();
checkMfaPrompt(getPendingOptionalMfaPrompt($pdo, 7, 'teacher') === ['id' => 91], 'teacher prompt missing');
checkMfaPrompt(getPendingOptionalMfaPrompt($pdo, 7, 'dyrektor') === ['id' => 91], 'director prompt missing');
$beforeUserLookup = count($pdo->statements);
checkMfaPrompt(getPendingOptionalMfaPrompt($pdo, 7, 'user') === null, 'student received MFA prompt');
checkMfaPrompt(count($pdo->statements) === $beforeUserLookup, 'student prompt queried database');

getNotifications($pdo, 7, 5);
getUnreadNotificationsCount($pdo, 7);
$notificationQueries = array_slice($pdo->statements, -2);
checkMfaPrompt(str_contains($notificationQueries[0]->sql, "type NOT IN ('mfa_optional_prompt', 'mfa_optional_declined')"), 'MFA decision visible in notification list');
checkMfaPrompt(str_contains($notificationQueries[1]->sql, "type NOT IN ('mfa_optional_prompt', 'mfa_optional_declined')"), 'MFA decision counted in notification badge');

echo "optional MFA prompt runtime OK\n";
