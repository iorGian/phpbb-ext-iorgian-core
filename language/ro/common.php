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
    'IORGIAN_CORE_REQUIRES_PHP'   => 'Iorgian Core necesită PHP 7.4.0 sau mai nou.',
    'IORGIAN_CORE_REQUIRES_PHPBB' => 'Iorgian Core necesită phpBB 3.3.0 sau mai nou.',
    'IORGIAN_CORE_MISSING'        => 'Extensia „Iorgian Core” trebuie instalată și activată mai întâi.',
    'IORGIAN_CORE_COLOR_RESET'    => 'Implicit',
    'IORGIAN_CORE_HELP_MIN' => 'Alege cel puțin %d.',
    'IORGIAN_CORE_HELP_MAX' => 'Alege cel mult %d.',
    'IORGIAN_CORE_HELP_MIN_MAX' => 'Alege între %d și %d.',
]);
