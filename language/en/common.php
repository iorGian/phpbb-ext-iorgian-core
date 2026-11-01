<?php
if (!defined('IN_PHPBB'))
{
    exit;
}
if (empty($lang) || !is_array($lang))
{
    $lang = [];
}

$lang = array_merge($lang, [
    'IORGIAN_CORE_REQUIRES_PHP'   => 'Iorgian Core requires PHP 7.4.0 or newer.',
    'IORGIAN_CORE_REQUIRES_PHPBB' => 'Iorgian Core requires phpBB 3.3.0 or newer.',
    'IORGIAN_CORE_MISSING'        => 'The extension “Iorgian Core” must be installed and enabled first.',
    'IORGIAN_CORE_COLOR_RESET'    => 'Default',
    'IORGIAN_CORE_HELP_MIN' => 'Choose at least %d.',
    'IORGIAN_CORE_HELP_MAX' => 'Choose up to %d.',
    'IORGIAN_CORE_HELP_MIN_MAX' => 'Choose between %d and %d.',
]);
