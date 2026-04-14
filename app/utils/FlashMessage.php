<?php
/**
 * Flash Message Helper
 * Sets session flash messages that get displayed as toast notifications
 */
class FlashMessage {
    public static function set($message, $type = 'success') {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type']    = $type;
    }

    public static function success($message) { self::set($message, 'success'); }
    public static function error($message)   { self::set($message, 'error'); }
    public static function warning($message) { self::set($message, 'warning'); }
    public static function info($message)    { self::set($message, 'info'); }
}
