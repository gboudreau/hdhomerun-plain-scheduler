<?php
namespace GBoudreau\HDHomeRun\Scheduler;

function array_remove($array, $value_to_remove) {
    return array_values(array_diff($array, array($value_to_remove)));
}

function strip_accents($text) {
    return strtr($text, 'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ', 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
}

function _array_shift($array) {
    return array_shift($array);
}

function string_contains($haystack, $needle) {
    return stripos($haystack, $needle) !== FALSE;
}

function string_begins_with($haystack, $needle) {
    return ( stripos($haystack, $needle) === 0 );
}

function string_ends_with($haystack, $needle) {
    return ( substr(strtolower($haystack), -strlen($needle)) == strtolower($needle) );
}

function string_contains_array($haystack, $needle_array) {
    if (empty($haystack)) {
        return FALSE;
    }
    foreach ($needle_array as $el) {
        if (string_contains($haystack, $el)) {
            return TRUE;
        }
    }
    return FALSE;
}

function array_contains($haystack, $needle) {
    if (empty($haystack)) {
        return FALSE;
    }
    return array_search($needle, $haystack) !== FALSE;
}

function array_clone(array $array) : array {
    $new_array = [];
    foreach ($array as $k => $v) {
        if (is_object($v)) {
            $new_array[$k] = clone $v;
        } elseif (is_array($v)) {
            $new_array[$k] = array_clone($v);
        } else {
            $new_array[$k] = $v;
        }
    }
    return $new_array;
}

function last($array) {
    if (empty($array) || !is_array($array)) {
        return FALSE;
    }
    foreach (array_reverse($array) as $el) {
        return $el;
    }
}

function clean_dir_name($dir_name) {
    return str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $dir_name);
}

function _log($log) {
    $success = FALSE;
    if (defined('RECORDING_HASH')) {
        $log = "[record-" . RECORDING_HASH . "] $log";
    } else {
        $log = "[cron] $log";
    }
    $log = "[" . date('Y-m-d H:i:s') . "] $log";
    $log_file = Config::get('LOG_FILE');
    if ($log_file) {
        // Log to file
        $success = error_log($log . "\n", 3, $log_file);
    }
    if (!$success) {
        // Log to stderr
        error_log($log);
    }
}
