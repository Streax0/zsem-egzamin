<?php
/**
 * Test Suite for Milestone 2: R1 Core Engine & Caching
 */

require_once __DIR__ . '/../includes/autoloader.php';

use App\Core\ConfigStore;
use App\Core\CacheManager;
use App\Core\ResponseBuffer;
use App\Core\Engine;

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, string &$failLog = '')
{
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] {$description}\n";
        $passed++;
    } else {
        echo " [FAIL] {$description}\n";
        if ($failLog) {
            echo "        Details: {$failLog}\n";
        }
        $failed++;
    }
}

echo "==================================================\n";
echo " Running Milestone 2 Core Engine & Caching Tests \n";
echo "==================================================\n\n";

// --- 1. Autoloading Test ---
echo "[1] Testing PSR-4 Autoloading...\n";
assertTest("ConfigStore class loaded", class_exists(ConfigStore::class));
assertTest("CacheManager class loaded", class_exists(CacheManager::class));
assertTest("ResponseBuffer class loaded", class_exists(ResponseBuffer::class));
assertTest("Engine class loaded", class_exists(Engine::class));
echo "\n";

// --- 2. ConfigStore Test ---
echo "[2] Testing ConfigStore...\n";
$testConfigPath = __DIR__ . '/../data/config/test_engine_config.json';
if (file_exists($testConfigPath)) {
    @unlink($testConfigPath);
}

$config = new ConfigStore($testConfigPath);
assertTest("ConfigStore default maintenance_mode is false", $config->get('maintenance_mode') === false);
assertTest("ConfigStore default minification_enabled is true", $config->get('minification_enabled') === true);
assertTest("ConfigStore default compression_enabled is true", $config->get('compression_enabled') === true);
assertTest("ConfigStore default waf_level is 'medium'", $config->get('waf_level') === 'medium');
assertTest("ConfigStore default csrf_enforced is true", $config->get('csrf_enforced') === true);
assertTest("ConfigStore fallback default for missing key", $config->get('non_existent_key', 'default_val') === 'default_val');

$config->set('waf_level', 'high');
assertTest("ConfigStore set key updates in-memory", $config->get('waf_level') === 'high');

// Verify persistence
$config2 = new ConfigStore($testConfigPath);
assertTest("ConfigStore persisted value reloaded from file", $config2->get('waf_level') === 'high');

$refConfig = new ReflectionClass(ConfigStore::class);
$propConfig = $refConfig->getProperty('apcuKey');
$propConfig->setAccessible(true);
$apcuKeyVal = $propConfig->getValue($config);
assertTest("ConfigStore APCu key is path-scoped", strpos($apcuKeyVal, md5($testConfigPath)) !== false);

if (file_exists($testConfigPath)) {
    @unlink($testConfigPath);
}
echo "\n";

// --- 3. CacheManager Test ---
echo "[3] Testing CacheManager...\n";
$testCacheDir = __DIR__ . '/../data/cache/test_cache';
$cache = new CacheManager($testCacheDir);

$stats = $cache->getStats();
assertTest("CacheManager getStats returns valid structure", isset($stats['hits'], $stats['misses'], $stats['apcu_enabled'], $stats['items_count']));

$cache->set('test_key', 'hello_world', 10);
assertTest("CacheManager get retrieves set value", $cache->get('test_key') === 'hello_world');

$missVal = $cache->get('non_existent_cache_key', 'fallback');
assertTest("CacheManager get missing key returns default", $missVal === 'fallback');

$rememberVal = $cache->remember('rem_key', 10, function() {
    return ['computed' => 42];
});
assertTest("CacheManager remember computes value", is_array($rememberVal) && $rememberVal['computed'] === 42);

$rememberCached = $cache->remember('rem_key', 10, function() {
    return ['computed' => 999];
});
assertTest("CacheManager remember returns cached value on second call", $rememberCached['computed'] === 42);

$cache->delete('test_key');
assertTest("CacheManager delete removes key", $cache->get('test_key') === null);

$clearOk = $cache->clear('all');
assertTest("CacheManager clear('all') succeeds", $clearOk === true);
$statsAfterClear = $cache->getStats();
assertTest("CacheManager items_count is 0 after clear", $statsAfterClear['items_count'] === 0);

$refCache = new ReflectionClass(CacheManager::class);
$propCache = $refCache->getProperty('apcuPrefix');
$propCache->setAccessible(true);
$apcuPrefixVal = $propCache->getValue($cache);
assertTest("CacheManager APCu prefix is path-scoped", strpos($apcuPrefixVal, md5($testCacheDir)) !== false);

// Test atomic overwrite with c+b and ftruncate
$cache->set('atomic_overwrite', str_repeat('LONG_DATA_', 500), 60);
$cache->set('atomic_overwrite', 'SHORT', 60);
assertTest("CacheManager set file writing uses c+b and ftruncate for atomic overwrite", $cache->get('atomic_overwrite') === 'SHORT');

// Cleanup test cache dir
$files = glob($testCacheDir . '/*');
if (is_array($files)) {
    foreach ($files as $f) {
        if (is_file($f)) @unlink($f);
    }
}
@rmdir($testCacheDir);
echo "\n";

// --- 4. ResponseBuffer Test ---
echo "[4] Testing ResponseBuffer...\n";
$buffer = new ResponseBuffer();

$rawHtml = "<html>\n  <!-- Comment -->\n  <body>  <p>Hello   World</p>  </body>\n</html>";
$minHtml = $buffer->minifyHtml($rawHtml);
assertTest("ResponseBuffer minifyHtml removes comments and collapses whitespace", strpos($minHtml, '<!-- Comment -->') === false && strpos($minHtml, '><') !== false);

$rawCss = "body {\n  color: red; \n  /* CSS Comment */ \n  margin: 0px; \n}";
$minCss = $buffer->minifyCss($rawCss);
assertTest("ResponseBuffer minifyCss removes comments and trims whitespace", strpos($minCss, '/* CSS Comment */') === false && strpos($minCss, 'color:red;') !== false);

$rawJs = "function test() {\n  // JS comment\n  var x = 10;\n  return x;\n}";
$minJs = $buffer->minifyJs($rawJs);
assertTest("ResponseBuffer minifyJs removes comments", strpos($minJs, '// JS comment') === false && strpos($minJs, 'var x=10;') !== false);

$formattedHtml = "<div>\n  <pre>\n    line 1\n    line 2\n  </pre>\n  <textarea name=\"code\">\n    first line\n    second line\n  </textarea>\n</div>";
$minHtmlFormatting = $buffer->minifyHtml($formattedHtml);
assertTest("ResponseBuffer minifyHtml preserves formatting inside <pre>", strpos($minHtmlFormatting, "<pre>\n    line 1\n    line 2\n  </pre>") !== false);
assertTest("ResponseBuffer minifyHtml preserves formatting inside <textarea>", strpos($minHtmlFormatting, "<textarea name=\"code\">\n    first line\n    second line\n  </textarea>") !== false);

$rawAsiJs = "let a = 1\nlet b = 2";
$minAsiJs = $buffer->minifyJs($rawAsiJs);
assertTest("ResponseBuffer minifyJs preserves newline/valid syntax (no invalid 'let a=1 let b=2')", strpos($minAsiJs, 'let a=1 let b=2') === false && (strpos($minAsiJs, "let a=1\nlet b=2") !== false || strpos($minAsiJs, "let a=1;let b=2") !== false));

$buffer->addTiming('db_query', 4.56, 'User Lookup Query');
$timings = $buffer->getTimings();
assertTest("ResponseBuffer addTiming records metric", isset($timings['db_query']) && $timings['db_query']['duration'] == 4.56);
echo "\n";

// --- 5. Engine Test ---
echo "[5] Testing Engine Singleton & Boot...\n";
Engine::resetInstance();
$engine1 = Engine::getInstance();
$engine2 = Engine::getInstance();
assertTest("Engine getInstance returns same singleton instance", $engine1 === $engine2);

$engine1->boot($testConfigPath, $testCacheDir);
// Output buffer is active now, so we turn off minification for test output readability
$engine1->getResponseBuffer()->setMinification(false);
$engine1->getResponseBuffer()->setCompression(false);

assertTest("Engine isBooted returns true after boot()", $engine1->isBooted() === true);
assertTest("Engine getConfig returns ConfigStore", $engine1->getConfig() instanceof ConfigStore);
assertTest("Engine getCache returns CacheManager", $engine1->getCache() instanceof CacheManager);
assertTest("Engine getResponseBuffer returns ResponseBuffer", $engine1->getResponseBuffer() instanceof ResponseBuffer);

$bootTimings = $engine1->getResponseBuffer()->getTimings();
assertTest("Engine boot records 'boot' timing in ResponseBuffer", isset($bootTimings['boot']));

// Flush buffer output cleanly to stdout so CLI receives it
$bufferedContent = $engine1->getResponseBuffer()->getClean();
echo $bufferedContent;

// Cleanup test config & cache dir
if (file_exists($testConfigPath)) {
    @unlink($testConfigPath);
}
$files = glob($testCacheDir . '/*');
if (is_array($files)) {
    foreach ($files as $f) {
        if (is_file($f)) @unlink($f);
    }
}
@rmdir($testCacheDir);
echo "\n";

// Summary
echo "==================================================\n";
echo " Test Summary: {$passed} PASSED, {$failed} FAILED \n";
echo "==================================================\n";

if ($failed > 0) {
    exit(1);
}

