<?php
namespace App\User;

class StringUtils {
    public static function formatTime($seconds) {
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        return sprintf("%d:%02d", $minutes, $remainingSeconds);
    }
}
