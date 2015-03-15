<?php
/*
   Copyright (c) 2003, 2009 Danilo Segan <danilo@kvota.net>.
   Copyright (c) 2005 Nico Kaiser <nico@siriux.net>
   Copyright (c) 2014 Igor Ognichenko <ognichenko.igor@gmail.com>

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
namespace Kasseler\Component\GetText;

use Kasseler\Component\GetText\Reader\FileReader;

/**
 * Provides a simple gettext replacement that works independently from
 * the system's gettext abilities.
 * It can read MO files and use them for translating strings.
 * The files are passed to gettext_reader as a Stream (see streams.php)
 * This version has the ability to cache all strings and translations to
 * speed up the string lookup.
 * While the cache is enabled by default, it can be switched off with the
 * second parameter in the constructor (e.g. whenusing very large MO files
 * that you don't want to keep in memory)
 */
class GetTextReader {
    /**
     * public variable that holds error code (0 if no error)
     *
     * @var int
     */
    public $error = 0;

    /**
     * 0: low endian, 1: big endian
     *
     * @var int
     */
    private $BYTEORDER = 0;

    /**
     * @var FileReader
     */
    private $STREAM;

    /**
     * @var bool
     */
    private $short_circuit = false;

    /**
     * @var bool
     */
    private $enable_cache = false;

    /**
     * offset of original table
     *
     * @var int
     */
    private $originals = null;

    /**
     * offset of translation table
     *
     * @var int
     */
    private $translations = null;

    /**
     * cache header field for plural forms
     *
     * @var mixed
     */
    private $pluralHeader = null;

    /**
     * total string count
     *
     * @var int
     */
    private $total = 0;

    private $revision = 0;

    /**
     * table for original strings (offsets)
     *
     * @var array
     */
    private $table_originals = null;

    /**
     * table for translated strings (offsets)
     *
     * @var array
     */
    private $table_translations = null;

    /**
     * original -> translation mapping
     *
     * @var array
     */
    private $cache_translations = null;

    /**
     * Constructor
     *
     * @param FileReader $FileReader
     * @param bool            $enable_cache
     */
    public function __construct(FileReader $FileReader, $enable_cache = false)
    {
        if (is_int($FileReader->error)) {
            $this->short_circuit = true;
        }

        // Caching can be turned off
        $this->enable_cache = $enable_cache;

        $MAGIC1 = "\x95\x04\x12\xde";
        $MAGIC2 = "\xde\x12\x04\x95";

        $this->STREAM = $FileReader;
        $magic = $this->read(4);
        if ($magic == $MAGIC1) {
            $this->BYTEORDER = 1;
        } elseif ($magic == $MAGIC2) {
            $this->BYTEORDER = 0;
        } else {
            $this->error = 1; // not MO file
        }

        $this->revision = $this->readInt();
        $this->total = $this->readInt();
        $this->originals = $this->readInt();
        $this->translations = $this->readInt();
    }

    /**
     * Reads a 32bit Integer from the Stream
     * @access private
     * @return Integer from the Stream
     */
    private function readInt()
    {
        $var = $this->BYTEORDER == 0
            ? unpack('V', $this->STREAM->read(4))
            : unpack('N', $this->STREAM->read(4));

        return array_shift($var);
    }

    private function read($bytes)
    {
        return $this->STREAM->read($bytes);
    }

    /**
     * Reads an array of Integers from the Stream
     *
     * @param int $count How many elements should be read
     *
     * @return Array of Integers
     */
    private function readIntArray($count)
    {

        return $this->BYTEORDER == 0
            ? unpack('V'.$count, $this->STREAM->read(4 * $count))
            : unpack('N'.$count, $this->STREAM->read(4 * $count));
    }

    /**
     * Loads the translation tables from the MO file into the cache
     * If caching is enabled, also loads all strings into a cache
     * to speed up translation lookups
     * @access private
     */
    private function loadTables()
    {
        if (is_array($this->cache_translations) && is_array($this->table_originals) && is_array($this->table_translations)) {
            return false;
        }

        /* get original and translations tables */
        if (!is_array($this->table_originals)) {
            $this->STREAM->seekto($this->originals);
            $this->table_originals = $this->readIntArray($this->total * 2);
        }
        if (!is_array($this->table_translations)) {
            $this->STREAM->seekto($this->translations);
            $this->table_translations = $this->readIntArray($this->total * 2);
        }

        if ($this->enable_cache) {
            $this->cache_translations = array();
            /* read all strings in the cache */
            for ($i = 0; $i < $this->total; $i++) {
                $this->STREAM->seekto($this->table_originals[$i * 2 + 2]);
                $original = $this->STREAM->read($this->table_originals[$i * 2 + 1]);
                $this->STREAM->seekto($this->table_translations[$i * 2 + 2]);
                $translation = $this->STREAM->read($this->table_translations[$i * 2 + 1]);
                $this->cache_translations[$original] = $translation;
            }
        }

        return true;
    }

    /**
     * Returns a string from the "originals" table
     * @access private
     *
     * @param int $num Offset number of original string
     *
     * @return string Requested string if found, otherwise ''
     */
    private function getOriginalString($num)
    {
        if(empty($this->table_originals)) {
            return '';
        }
        $length = $this->table_originals[$num * 2 + 1];
        $offset = $this->table_originals[$num * 2 + 2];
        if (!$length) {
            return '';
        }
        $this->STREAM->seekto($offset);
        $data = $this->STREAM->read($length);

        return (string) $data;
    }

    /**
     * Returns a string from the "translations" table
     * @access private
     *
     * @param int $num Offset number of original string
     *
     * @return string Requested string if found, otherwise ''
     */
    private function getTranslationString($num)
    {
        $length = $this->table_translations[$num * 2 + 1];
        $offset = $this->table_translations[$num * 2 + 2];
        if (!$length) {
            return '';
        }
        $this->STREAM->seekto($offset);
        $data = $this->STREAM->read($length);

        return (string) $data;
    }

    /**
     * Binary search for string
     * @access private
     *
     * @param string string
     * @param int $start (internally used in recursive function)
     * @param int $end (internally used in recursive function)
     *
     * @return int string number (offset in originals table)
     */
    private function findString($string, $start = -1, $end = -1)
    {
        if (($start == -1) || ($end == -1)) {
            // find_string is called with only one parameter, set start end end
            $start = 0;
            $end = $this->total;
        }
        if (abs($start - $end) <= 1) {
            // We're done, now we either found the string, or it doesn't exist
            $txt = $this->getOriginalString($start);
            return $string == $txt
                ? $start
                : -1;
        } elseif($start > $end) {
            // start > end -> turn around and start over
            return $this->findString($string, $end, $start);
        } else {
            // Divide table in two parts
            $half = (int) (($start + $end) / 2);
            $cmp = strcmp($string, $this->getOriginalString($half));
            if ($cmp == 0) {
                return $half;
            } else if($cmp < 0) { // The string is in the upper half
                return $this->findString($string, $start, $half);
            } else {
                // The string is in the lower half
                return $this->findString($string, $half, $end);
            }
        }
    }

    /**
     * Translates a string
     * @access public
     *
     * @param string string to be translated
     *
     * @return string translated string (or original, if not found)
     */
    public function translate($string)
    {
        if ($this->short_circuit) {
            return $string;
        }
        $this->loadTables();

        if ($this->enable_cache) {
            // Caching enabled, get translated string from cache
            return array_key_exists($string, $this->cache_translations)
                ? $this->cache_translations[$string]
                : $string;
        } else {
            // Caching not enabled, try to find string
            $num = $this->findString($string);

            return $num == -1
                ? $string
                : $this->getTranslationString($num);
        }
    }

    /**
     * Sanitize plural form expression for use in PHP eval call.
     * @access private
     *
     * @param $expr
     *
     * @return string sanitized plural form expression
     */
    private function sanitizePluralExpression($expr)
    {
        // Get rid of disallowed characters.
        $expr = preg_replace('@[^a-zA-Z0-9_:;\(\)\?\|\&=!<>+*/\%-]@', '', $expr);

        // Add parenthesis for tertiary '?' operator.
        $expr .= ';';
        $res = '';
        $p = 0;
        for ($i = 0; $i < strlen($expr); $i++) {
            $ch = $expr[$i];
            switch($ch){
                case '?':
                    $res .= ' ? (';
                    $p++;
                    break;
                case ':':
                    $res .= ') : (';
                    break;
                case ';':
                    $res .= str_repeat(')', $p).';';
                    $p = 0;
                    break;
                default:
                    $res .= $ch;
            }
        }

        return $res;
    }

    /**
     * Parse full PO header and extract only plural forms line.
     * @access private
     *
     * @param $header
     *
     * @return string verbatim plural form header field
     */
    private function extractPluralFormsHeader($header)
    {
        return preg_match("/(^|\n)plural-forms: ([^\n]*)\n/i", $header, $regs)
            ? $regs[2]
            : "nplurals=2; plural=n == 1 ? 0 : 1;";
    }

    /**
     * Get possible plural forms from MO header
     * @access private
     * @return string plural form header
     */
    private function getPluralForms()
    {
        $this->loadTables();

        // cache header field for plural forms
        if (!is_string($this->pluralHeader)) {
            $header = $this->enable_cache
                ? $this->cache_translations[""]
                : $this->getTranslationString(0);
            $expr = $this->extractPluralFormsHeader($header);
            $this->pluralHeader = $this->sanitizePluralExpression($expr);
        }

        return $this->pluralHeader;
    }

    /**
     * Detects which plural form to take
     * @access private
     *
     * @param int $n
     *
     * @return int array index of the right plural form
     * @internal param count $n
     */
    private function selectString($n)
    {
        $string = $this->getPluralForms();
        $string = str_replace('nplurals', "\$total", $string);
        $string = str_replace("n", $n, $string);
        $string = str_replace('plural', "\$plural", $string);

        $total = 0;
        $plural = 0;

        eval("$string");
        if ($plural >= $total) {
            $plural = $total-1;
        }

        return $plural;
    }

    /**
     * Plural version of gettext
     * @access public
     *
     * @param string $single
     * @param string $plural
     * @param string $number
     *
     * @return string plural form
     */
    public function ngettext($single, $plural, $number)
    {
        if ($this->short_circuit) {
            return $number != 1
                ? $plural
                : $single;
        }

        // find out the appropriate form
        $select = $this->selectString($number);

        // this should contains all strings separated by NULLs
        $key = $single.chr(0).$plural;

        if ($this->enable_cache) {
            if (!array_key_exists($key, $this->cache_translations)) {
                return ($number != 1) ? $plural : $single;
            } else {
                $result = $this->cache_translations[$key];
                $list = explode(chr(0), $result);

                return $list[$select];
            }
        } else {
            $num = $this->findString($key);
            if ($num == -1) {
                return ($number != 1) ? $plural : $single;
            } else {
                $result = $this->getTranslationString($num);
                $list = explode(chr(0), $result);

                return $list[$select];
            }
        }
    }

    /**
     * @param $context
     * @param $msgid
     *
     * @return string
     */
    public function pgettext($context, $msgid)
    {
        $key = $context.chr(4).$msgid;
        $ret = $this->translate($key);

        return strpos($ret, "\004") !== false
            ? $msgid
            : $ret;
    }

    /**
     * @param $context
     * @param $singular
     * @param $plural
     * @param $number
     *
     * @return string
     */
    public function npgettext($context, $singular, $plural, $number = 0)
    {
        $key = $context.chr(4).$singular;
        $ret = $this->ngettext($key, $plural, $number);

        return strpos($ret, "\004") !== false
            ? $singular
            : $ret;
    }
}
