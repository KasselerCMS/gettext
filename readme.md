Gettext Component
=======
Gettext *.mo files reader for PHP. Original package https://launchpad.net/php-gettext
### Requirements
 - PHP >= 5.3

### Installation
```sh
$ composer require kasseler/config
```

### Introduction

How many times did you look for a good translation tool, and found out that gettext is best for the job? Many times.
How many times did you try to use gettext in PHP, but failed miserably, because either your hosting provider didn't support it, or the server didn't have adequate locale? Many times.
Well, this is a solution to your needs. It allows using gettext tools for managing translations, yet it doesn't require gettext library at all. It parses generated MO files directly, and thus might be a bit slower than the (maybe provided) gettext library.
PHP-gettext is a simple reader for GNU gettext MO files. Those are binary containers for translations, produced by GNU msgfmt.

#### Usage
You must use the initialization function:
```php
//                    filename   locale   charset
init_translate_domain('message', 'fr',    'UTF-8',  'path_to_locales_dir');

echo _gettext('Add');
echo _ngettext('Minute', 'Minutes', 2);
```

To force the use of the class, you must install define:
```php
define('GETTEXT_CLASS', true);
```
