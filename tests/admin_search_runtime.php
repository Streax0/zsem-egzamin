<?php
require dirname(__DIR__) . '/includes/functions.php';

function checkAdminSearch($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: $message\n");
        exit(1);
    }
}

final class AdminSearchStatementStub extends PDOStatement {
    public string $sql = '';
    public array $bindings = [];
    public bool $executed = false;
    public array $rows = [];
    public int $count = 0;

    protected function __construct() {}

    public static function create(string $sql): self {
        $statement = new self();
        $statement->sql = $sql;
        return $statement;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool {
        if (array_key_exists($param, $this->bindings)) {
            throw new RuntimeException('duplicate binding: ' . $param);
        }
        $this->bindings[$param] = ['value' => $value, 'type' => $type];
        return true;
    }

    public function execute(?array $params = null): bool {
        checkAdminSearch($params === null, 'search unexpectedly used execute parameters');

        preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $this->sql, $matches);
        $sqlParameters = $matches[0];
        if (count($sqlParameters) !== count(array_unique($sqlParameters))) {
            throw new PDOException('SQLSTATE[HY093]: Invalid parameter number: named PDO placeholder reused');
        }

        $expectedBindings = array_values(array_unique($sqlParameters));
        $actualBindings = array_keys($this->bindings);
        sort($expectedBindings);
        sort($actualBindings);
        if ($actualBindings !== $expectedBindings) {
            throw new PDOException('SQLSTATE[HY093]: Invalid parameter number: SQL placeholders and bindings differ');
        }

        $this->executed = true;
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        checkAdminSearch($mode === PDO::FETCH_ASSOC, 'search did not fetch associative rows');
        return $this->rows;
    }

    public function fetchColumn(int $column = 0): mixed {
        return $this->count;
    }
}

final class AdminSearchPdoStub extends PDO {
    /** @var AdminSearchStatementStub[] */
    public array $statements = [];
    public bool $injectDuplicatePlaceholder = false;

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false {
        if ($this->injectDuplicatePlaceholder && !str_starts_with($query, 'SELECT COUNT(*)')) {
            $query .= ' OR username LIKE :q_username';
        }
        $statement = AdminSearchStatementStub::create($query);
        if (str_starts_with($query, 'SELECT COUNT(*)')) {
            $statement->count = 7;
        } else {
            $statement->rows = [['id' => 42, 'username' => 'tester']];
        }
        $this->statements[] = $statement;
        return $statement;
    }
}

$pdo = new AdminSearchPdoStub();
$result = searchAdminUsers($pdo, ' tester ', 20, 40);

checkAdminSearch($result['users'] === [['id' => 42, 'username' => 'tester']], 'unexpected user rows');
checkAdminSearch($result['total'] === 7, 'unexpected total count');
checkAdminSearch($result['error'] === false, 'successful search reported an error');
checkAdminSearch(count($pdo->statements) === 2, 'search did not prepare exactly two statements');

$expectedSearch = [':q_username', ':q_email', ':q_first_name', ':q_last_name', ':q_class'];
$listStatement = $pdo->statements[0];
$countStatement = $pdo->statements[1];
checkAdminSearch($listStatement->executed && $countStatement->executed, 'search statement was not executed');
checkAdminSearch(substr_count($listStatement->sql, ' LIKE :q_') === 5, 'list query does not use five unique LIKE placeholders');
checkAdminSearch(substr_count($countStatement->sql, ' LIKE :q_') === 5, 'count query does not use five unique LIKE placeholders');

foreach ($expectedSearch as $placeholder) {
    foreach ([$listStatement, $countStatement] as $statement) {
        checkAdminSearch(isset($statement->bindings[$placeholder]), 'missing binding ' . $placeholder);
        checkAdminSearch($statement->bindings[$placeholder]['value'] === '%tester%', 'wrong LIKE value for ' . $placeholder);
        checkAdminSearch($statement->bindings[$placeholder]['type'] === PDO::PARAM_STR, 'wrong LIKE type for ' . $placeholder);
    }
}

checkAdminSearch($listStatement->bindings[':limit'] === ['value' => 20, 'type' => PDO::PARAM_INT], 'limit binding invalid');
checkAdminSearch($listStatement->bindings[':offset'] === ['value' => 40, 'type' => PDO::PARAM_INT], 'offset binding invalid');
checkAdminSearch(!isset($countStatement->bindings[':limit'], $countStatement->bindings[':offset']), 'count query received pagination bindings');

$invalidPdo = new AdminSearchPdoStub();
$invalidPdo->injectDuplicatePlaceholder = true;
$invalidResult = searchAdminUsers($invalidPdo, 'tester', 20, 0);
checkAdminSearch($invalidResult === ['users' => [], 'total' => 0, 'error' => true], 'HY093 did not produce an explicit search error');

$duplicateStatement = AdminSearchStatementStub::create('SELECT * FROM users WHERE username LIKE :q OR email LIKE :q');
$duplicateStatement->bindValue(':q', '%tester%', PDO::PARAM_STR);
$hy093Raised = false;
try {
    $duplicateStatement->execute();
} catch (PDOException $e) {
    $hy093Raised = str_contains($e->getMessage(), 'HY093');
}
checkAdminSearch($hy093Raised, 'duplicate named placeholder did not raise HY093');

echo "admin search runtime OK\n";
