<?php
namespace App\User;

class StringUtils {
    public static function formatTime($seconds) {
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        return sprintf("%d:%02d", $minutes, $remainingSeconds);
    }
    
    public static function countWordsUtf8($text) {
        if (empty(trim((string)$text))) {
            return 0;
        }
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        $words = preg_split('/\s+/', trim((string)$normalized), -1, PREG_SPLIT_NO_EMPTY);
        return count($words);
    }
}
