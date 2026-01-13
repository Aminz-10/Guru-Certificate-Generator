<?php
// includes/polyfills.php

if (!defined('PASSWORD_DEFAULT')) {
    define('PASSWORD_DEFAULT', 1);
}

if (!function_exists('password_hash')) {
    function password_hash($password, $algo, $options = array()) {
        if (!function_exists('crypt')) {
            trigger_error("Crypt must be loaded for password_hash", E_USER_WARNING);
            return null;
        }
        $salt = '$2y$10$' . substr(md5(uniqid(rand(), true)), 0, 22);
        return crypt($password, $salt);
    }
}

if (!function_exists('password_verify')) {
    function password_verify($password, $hash) {
        return (crypt($password, $hash) === $hash);
    }
}

if (!function_exists('random_bytes')) {
    function random_bytes($length) {
        if (function_exists('openssl_random_pseudo_bytes')) {
            return openssl_random_pseudo_bytes($length);
        }
        // Fallback for very old systems (insecure but functional)
        $bytes = '';
        for ($i = 0; $i < $length; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }
        return $bytes;
    }
}

if (!function_exists('array_column')) {
    function array_column(array $input, $columnKey, $indexKey = null) {
        $array = array();
        foreach ($input as $value) {
            if ( !array_key_exists($columnKey, $value)) {
                // Silently skip or return null behavior similar to PHP usually? 
                // Actually PHP array_column just skips if not found? 
                // Let's just do simple check
                continue; 
            }
            if (is_null($indexKey)) {
                $array[] = $value[$columnKey];
            } else {
                if ( !array_key_exists($indexKey, $value)) {
                    continue;
                }
                $array[$value[$indexKey]] = $value[$columnKey];
            }
        }
        return $array;
    }
}
