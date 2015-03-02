<?php

/**
 * Alias for gettext.
 *
 * @param $msgid
 *
 * @return string
 */
function __($msgid){
    return gettext($msgid);
}

/**
 * Alias for gettext.
 *
 * @param $singular
 * @param $plural
 * @param $number
 *
 * @return string
 */
function __n($singular, $plural, $number){
    return ngettext($singular, $plural, $number);
}

/**
 * @param $domain
 * @param $locale
 * @param $charset
 * @param $localePath
 */
function init_translate_domain($domain, $locale, $charset, $localePath){
    setlocale(LC_ALL, $locale);
    bindtextdomain($domain, $localePath);
    bind_textdomain_codeset($domain, $charset);
    textdomain($domain);
}
