<?php
$dictionaryFile = __DIR__ . '/data/dictionary.json';

// Baseline
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $dictionaryData = [];
    if (is_file($dictionaryFile)) {
        $dictionaryData = json_decode((string)file_get_contents($dictionaryFile), true) ?: [];
    }
}
$end = microtime(true);
$baselineTime = $end - $start;
echo "Baseline (100 iterations): " . $baselineTime . " seconds\n";

// Optimized with APCu
$start = microtime(true);
for ($i = 0; $i < 100; $i++) {
    $dictionaryData = [];
    if (is_file($dictionaryFile)) {
        $cacheKey = 'dictionary_data_' . filemtime($dictionaryFile);
        if (function_exists('apcu_fetch')) {
            $dictionaryData = apcu_fetch($cacheKey, $success);
            if (!$success) {
                $dictionaryData = json_decode((string)file_get_contents($dictionaryFile), true) ?: [];
                apcu_store($cacheKey, $dictionaryData);
            }
        } else {
            $dictionaryData = json_decode((string)file_get_contents($dictionaryFile), true) ?: [];
        }
    }
}
$end = microtime(true);
$optimizedTime = $end - $start;
echo "Optimized (100 iterations): " . $optimizedTime . " seconds\n";
echo "Improvement: " . round((($baselineTime - $optimizedTime) / $baselineTime) * 100, 2) . "%\n";
