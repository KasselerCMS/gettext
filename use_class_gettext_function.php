<?php
/*
   Copyright (c) 2005 Steven Armstrong <sa at c-area dot ch>
   Copyright (c) 2009 Danilo Segan <danilo@kvota.net>
   Copyright (c) 2014 Igor Ognichenko <ognichenko.igor@gmail.com>

   Drop in replacement for native gettext.

   This file is part of PHP-gettext.

   PHP-gettext is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   PHP-gettext is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with PHP-gettext; if not, write to the Free Software
   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

//LC_CTYPE        0
//LC_NUMERIC      1
//LC_TIME         2
//LC_COLLATE      3
//LC_MONETARY     4
//LC_MESSAGES     5
//LC_ALL          6


// LC_MESSAGES is not available if php-gettext is not loaded
// while the other constants are already available from session extension.
use Kasseler\Component\GetText\Domain;
use Kasseler\Component\GetText\GetTextReader;
use Kasseler\Component\GetText\GetTextVars;
use Kasseler\Component\GetText\Reader\FileReader;

/**
 * Return a list of locales to try for any POSIX-style locale specification.
 *
 * @param $locale
 *
 * @return array
 */
function get_list_of_locales($locale) {
    /* Figure out all possible locale names and start with the most
     * specific ones.  I.e. for sr_CS.UTF-8@latin, look through all of
     * sr_CS.UTF-8@latin, sr_CS@latin, sr@latin, sr_CS.UTF-8, sr_CS, sr.
     */
    $locale_names = array();
    $lang = null;
    $country = null;
    $charset = null;
    $modifier = null;
    if($locale) {
        if (preg_match('/^(?P<lang>[a-z]{2,3})'       // language code
            .'(?:_(?P<country>[A-Z]{2}))?'           // country code
            .'(?:\.(?P<charset>[-A-Za-z0-9_]+))?'    // charset
            .'(?:@(?P<modifier>[-A-Za-z0-9_]+))?$/', // @ modifier
            $locale, $matches)) {

            isset($matches["lang"])     && $lang = $matches["lang"];
            isset($matches["country"])  && $country = $matches["country"];
            isset($matches["charset"])  && $charset = $matches["charset"];
            isset($matches["modifier"]) && $modifier = $matches["modifier"];

            if ($modifier !== null) {
                if ($country !== null) {
                    if ($charset !== null) {
                        array_push($locale_names, "${lang}_$country.$charset@$modifier");
                    }
                    array_push($locale_names, "${lang}_$country@$modifier");
                } elseif($charset !== null) {
                    array_push($locale_names, "${lang}.$charset@$modifier");
                }
                array_push($locale_names, "$lang@$modifier");
            }
            if ($country !== null) {
                if ($charset !== null) {
                    array_push($locale_names, "${lang}_$country.$charset");
                }
                array_push($locale_names, "${lang}_$country");
            } elseif($charset !== null) {
                array_push($locale_names, "${lang}.$charset");
            }
            array_push($locale_names, $lang);
        }

        // If the locale name doesn't match POSIX style, just include it as-is.
        !in_array($locale, $locale_names) && array_push($locale_names, $locale);
    }

    return $locale_names;
}

/**
 * Utility function to get a StreamReader for the given text domain.
 *
 * @param null $domain
 * @param int  $category
 * @param bool $enable_cache
 *
 * @return GetTextReader
 */
function _get_reader($domain = null, $category = 5, $enable_cache = true) {
    !isset($domain) && $domain = GetTextVars::$default_domain;

    if (!isset(GetTextVars::$text_domains[$domain]->l10n)) {
        // get the current locale
        $locale = _setlocale(LC_MESSAGES, 0);
        $bound_path = isset(GetTextVars::$text_domains[$domain]->path)
            ? GetTextVars::$text_domains[$domain]->path
            : './';
        $subpath = GetTextVars::$LC_CATEGORIES[$category]."/$domain.mo";

        $locale_names = get_list_of_locales($locale);

        $input = null;
        foreach ($locale_names as $locale) {
            $full_path = $bound_path.$locale."/".$subpath;
            if (file_exists($full_path)) {
                $input = new FileReader($full_path);
                break;
            }
        }

        if (!array_key_exists($domain, GetTextVars::$text_domains)) {
            // Initialize an empty domain object.
            GetTextVars::$text_domains[$domain] = new Domain();
        }
        if ($input !== null) {
            GetTextVars::$text_domains[$domain]->l10n = new GetTextReader($input, $enable_cache);
        }
    }

    return GetTextVars::$text_domains[$domain]->l10n;
}

/**
 * Returns whether we are using our emulated gettext API or PHP built-in one.
 */
function locale_emulation() {
    return GetTextVars::$emulate;
}

/**
 * Checks if the current locale is supported on this system.
 *
 * @param bool $function
 *
 * @return bool
 */
function _check_locale_and_function($function = false) {
    return $function && !function_exists($function)
        ? false
        : !GetTextVars::$emulate;
}

/**
 * Get the codeSet for the given domain.
 *
 * @param null $domain
 *
 * @return string
 */
function _get_codeSet($domain = null) {
    if (!isset($domain)) {
        $domain = GetTextVars::$default_domain;
    }

    return (isset(GetTextVars::$text_domains[$domain]->codeSet))
        ? GetTextVars::$text_domains[$domain]->codeSet
        : ini_get('mbstring.internal_encoding');
}

/**
 * Convert the given string to the encoding set by bind_textdomain_codeSet.
 *
 * @param $text
 *
 * @return string
 */
function _encode($text) {
    $source_encoding = mb_detect_encoding($text);
    $target_encoding = _get_codeSet();

    return $source_encoding != $target_encoding
        ? mb_convert_encoding($text, $target_encoding, $source_encoding)
        : $text;
}

/**
 * Returns passed in $locale, or environment variable $LANG if $locale == ''.
 *
 * @param $locale
 *
 * @return string
 */
function _get_default_locale($locale) {
    return $locale == ''
        ? getenv('LANG')
        : $locale;
}

/**
 * Sets a requested locale, if needed emulates it.
 *
 * @param $category
 * @param $locale
 *
 * @return string
 */
function _setlocale($category, $locale) {
    if ($locale === 0) { // use === to differentiate between string "0"
        return GetTextVars::$currentLocale != ''
            ? GetTextVars::$currentLocale
            : _setlocale($category, GetTextVars::$currentLocale);
    } else {
        if (function_exists('setlocale')) {
            $ret = setlocale($category, $locale);
            if (($locale == '' && !$ret) || ($locale != '' && $ret != $locale)) {
                // Failed setting it according to environment.
                GetTextVars::$currentLocale = _get_default_locale($locale);
                GetTextVars::$emulate = 1;
            } else {
                GetTextVars::$currentLocale = $ret;
                GetTextVars::$emulate = 0;
            }
        } else {
            // No function setlocale(), emulate it all.
            GetTextVars::$currentLocale = _get_default_locale($locale);
            GetTextVars::$emulate = 1;
        }
        // Allow locale to be changed on the go for one translation domain.
        if (array_key_exists(GetTextVars::$default_domain, GetTextVars::$text_domains)) {
            unset(GetTextVars::$text_domains[GetTextVars::$default_domain]->l10n);
        }

        return GetTextVars::$currentLocale;
    }
}

/**
 * Sets the path for a domain.
 *
 * @param $domain
 * @param $path
 */
function _bindtextdomain($domain, $path) {
    // ensure $path ends with a slash ('/' should work for both, but lets still play nice)
    if (substr(php_uname(), 0, 7) == "Windows") {
        if ($path[strlen($path)-1] != '\\' && $path[strlen($path)-1] != '/') {
            $path .= '\\';
        }
    } else {
        if ($path[strlen($path)-1] != '/') {
            $path .= '/';
        }
    }
    if (!array_key_exists($domain, GetTextVars::$text_domains)) {
        // Initialize an empty domain object.
        GetTextVars::$text_domains[$domain] = new Domain();
    }
    GetTextVars::$text_domains[$domain]->path = $path;

    return $domain;
}

/**
 * Specify the character encoding in which the messages from the DOMAIN message catalog will be returned.
 *
 * @param $domain
 * @param $codeSet
 */
function _bind_textdomain_codeset($domain, $codeSet) {
    GetTextVars::$text_domains[$domain]->codeSet = $codeSet;

    return $domain;
}

/**
 * Sets the default domain.
 *
 * @param $domain
 */
function _textdomain($domain) {
    GetTextVars::$default_domain = $domain;

    return $domain;
}

/**
 * Lookup a message in the current domain.
 *
 * @param $msgid
 *
 * @return string
 */
function _gettext($msgid) {
    $l10n = _get_reader();

    return _encode($l10n->translate($msgid));
}

/**
 * Plural version of gettext.
 *
 * @param $singular
 * @param $plural
 * @param $number
 *
 * @return string
 */
function _ngettext($singular, $plural, $number) {
    $l10n = _get_reader();

    return _encode($l10n->ngettext($singular, $plural, $number));
}

/**
 * Override the current domain.
 *
 * @param $domain
 * @param $msgid
 *
 * @return string
 */
function _dgettext($domain, $msgid) {
    $l10n = _get_reader($domain);

    return _encode($l10n->translate($msgid));
}

/**
 * Plural version of dgettext.
 *
 * @param $domain
 * @param $singular
 * @param $plural
 * @param $number
 *
 * @return string
 */
function _dngettext($domain, $singular, $plural, $number) {
    $l10n = _get_reader($domain);

    return _encode($l10n->ngettext($singular, $plural, $number));
}

/**
 * Overrides the domain and category for a single lookup.
 *
 * @param $domain
 * @param $msgid
 * @param $category
 *
 * @return string
 */
function _dcgettext($domain, $msgid, $category) {
    $l10n = _get_reader($domain, $category);

    return _encode($l10n->translate($msgid));
}

/**
 * Plural version of dcgettext.
 *
 * @param $domain
 * @param $singular
 * @param $plural
 * @param $number
 * @param $category
 *
 * @return string
 */
function _dcngettext($domain, $singular, $plural, $number, $category) {
    $l10n = _get_reader($domain, $category);

    return _encode($l10n->ngettext($singular, $plural, $number));
}

/**
 * Context version of gettext.
 *
 * @param $context
 * @param $msgid
 *
 * @return string
 */
function _pgettext($context, $msgid) {
    $l10n = _get_reader();

    return _encode($l10n->pgettext($context, $msgid));
}

/**
 * Override the current domain in a context gettext call.
 *
 * @param $domain
 * @param $context
 * @param $msgid
 *
 * @return string
 */
function _dpgettext($domain, $context, $msgid) {
    $l10n = _get_reader($domain);

    return _encode($l10n->pgettext($context, $msgid));
}

/**
 * Overrides the domain and category for a single context-based lookup.
 *
 * @param $domain
 * @param $context
 * @param $msgid
 * @param $category
 *
 * @return string
 */
function _dcpgettext($domain, $context, $msgid, $category) {
    $l10n = _get_reader($domain, $category);

    return _encode($l10n->pgettext($context, $msgid));
}

/**
 * Context version of ngettext.
 *
 * @param $context
 * @param $singular
 * @param $plural
 *
 * @return string
 */
function _npgettext($context, $singular, $plural) {
    $l10n = _get_reader();

    return _encode($l10n->npgettext($context, $singular, $plural));
}

/**
 * Override the current domain in a context ngettext call.
 *
 * @param $domain
 * @param $context
 * @param $singular
 * @param $plural
 *
 * @return string
 */
function _dnpgettext($domain, $context, $singular, $plural) {
    $l10n = _get_reader($domain);

    return _encode($l10n->npgettext($context, $singular, $plural));
}

/**
 * Overrides the domain and category for a plural context-based lookup.
 *
 * @param $domain
 * @param $context
 * @param $singular
 * @param $plural
 * @param $category
 *
 * @return string
 */
function _dcnpgettext($domain, $context, $singular, $plural, $category) {
    $l10n = _get_reader($domain, $category);

    return _encode($l10n->npgettext($context, $singular, $plural));
}

/**
 * Alias for gettext.
 *
 * @param $msgid
 *
 * @return string
 */
function __($msgid) {
    return _gettext($msgid);
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
function __n($singular, $plural, $number) {
    return _ngettext($singular, $plural, $number);
}

/**
 * @param $domain
 * @param $locale
 * @param $charset
 * @param $localePath
 */
function init_translate_domain($domain, $locale, $charset, $localePath) {
    _setlocale(LC_ALL, $locale);
    _bindtextdomain($domain, $localePath);
    _bind_textdomain_codeset($domain, $charset);
    _textdomain($domain);
}

if(!function_exists('gettext')){
    /**
     * @param $domain
     * @param $path
     */
    function bindtextdomain($domain, $path) {
        return _bindtextdomain($domain, $path);
    }

    /**
     * @param $domain
     * @param $codeSet
     *
     * @return mixed
     */
    function bind_textdomain_codeset($domain, $codeSet) {
        return _bind_textdomain_codeset($domain, $codeSet);
    }

    /**
     * @param $domain
     *
     * @return mixed
     */
    function textdomain($domain) {
        return _textdomain($domain);
    }

    /**
     * @param $msgid
     *
     * @return string
     */
    function gettext($msgid) {
        return _gettext($msgid);
    }

    /**
     * @param $msgid
     *
     * @return string
     */
    function _($msgid) {
        return _gettext($msgid);
    }

    /**
     * @param $singular
     * @param $plural
     * @param $number
     *
     * @return string
     */
    function ngettext($singular, $plural, $number) {
        return _ngettext($singular, $plural, $number);
    }

    /**
     * @param $domain
     * @param $msgid
     *
     * @return string
     */
    function dgettext($domain, $msgid) {
        return _dgettext($domain, $msgid);
    }

    /**
     * @param $domain
     * @param $singular
     * @param $plural
     * @param $number
     *
     * @return string
     */
    function dngettext($domain, $singular, $plural, $number) {
        return _dngettext($domain, $singular, $plural, $number);
    }

    /**
     * @param $domain
     * @param $msgid
     * @param $category
     *
     * @return string
     */
    function dcgettext($domain, $msgid, $category) {
        return _dcgettext($domain, $msgid, $category);
    }

    /**
     * @param $domain
     * @param $singular
     * @param $plural
     * @param $number
     * @param $category
     *
     * @return string
     */
    function dcngettext($domain, $singular, $plural, $number, $category) {
        return _dcngettext($domain, $singular, $plural, $number, $category);
    }

    /**
     * @param $context
     * @param $msgid
     *
     * @return string
     */
    function pgettext($context, $msgid) {
        return _pgettext($context, $msgid);
    }

    /**
     * @param $context
     * @param $singular
     * @param $plural
     *
     * @return string
     */
    function npgettext($context, $singular, $plural) {
        return _npgettext($context, $singular, $plural);
    }

    /**
     * @param $domain
     * @param $context
     * @param $msgid
     *
     * @return string
     */
    function dpgettext($domain, $context, $msgid) {
        return _dpgettext($domain, $context, $msgid);
    }

    /**
     * @param $domain
     * @param $context
     * @param $singular
     * @param $plural
     *
     * @return string
     */
    function dnpgettext($domain, $context, $singular, $plural) {
        return _dnpgettext($domain, $context, $singular, $plural);
    }

    /**
     * @param $domain
     * @param $context
     * @param $msgid
     * @param $category
     *
     * @return string
     */
    function dcpgettext($domain, $context, $msgid, $category) {
        return _dcpgettext($domain, $context, $msgid, $category);
    }

    /**
     * @param $domain
     * @param $context
     * @param $singular
     * @param $plural
     * @param $category
     *
     * @return string
     */
    function dcnpgettext($domain, $context, $singular, $plural, $category) {
        return _dcnpgettext($domain, $context, $singular, $plural, $category);
    }
}
