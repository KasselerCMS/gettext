<?php

namespace Kasseler\Component\GetText;

class GetTextVars
{
    public static $text_domains = array();
    public static $default_domain = 'messages';
    public static $LC_CATEGORIES = array('LC_CTYPE', 'LC_NUMERIC', 'LC_TIME', 'LC_COLLATE', 'LC_MONETARY', 'LC_MESSAGES', 'LC_ALL');
    public static $emulate = 0;
    public static $currentLocale = '';
}
