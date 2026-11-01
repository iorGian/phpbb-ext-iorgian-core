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
    'IORGIAN_CORE_REQUIRES_PHP'   => 'Iorgian Core requiere PHP 7.4.0 o superior.',
    'IORGIAN_CORE_REQUIRES_PHPBB' => 'Iorgian Core requiere phpBB 3.3.0 o superior.',
    'IORGIAN_CORE_MISSING'        => 'La extensión «Iorgian Core» debe estar instalada y activada primero.',
    'IORGIAN_CORE_COLOR_RESET'    => 'Por defecto',
    'IORGIAN_CORE_HELP_MIN' => 'Elige al menos %d.',
    'IORGIAN_CORE_HELP_MAX' => 'Elige como máximo %d.',
    'IORGIAN_CORE_HELP_MIN_MAX' => 'Elige entre %d y %d.',
]);
