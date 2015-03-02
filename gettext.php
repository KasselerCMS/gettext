<?php

if(!defined('LC_MESSAGES')){
    define('LC_MESSAGES', 5);
}

if(!function_exists('gettext') || defined('GETTEXT_CLASS')) {
    require_once __DIR__.'/use_class_gettext_function.php';
} else {
    require_once __DIR__.'/use_native_gettext_function.php';
}
