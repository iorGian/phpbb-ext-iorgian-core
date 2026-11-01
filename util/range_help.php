<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\util;

/**
 * Translated help text for "choose at least X / up to Y / between X and Y".
 */
class range_help
{
    /** @var \phpbb\language\language */
    protected $language;

    public function __construct(\phpbb\language\language $language)
    {
        $this->language = $language;
    }

    /**
     * @param int $min minimum (0 = none)
     * @param int $max maximum (0 = no limit)
     * @return string empty when there is nothing to say
     */
    public function text(int $min, int $max): string
    {
        $this->language->add_lang('common', 'iorgian/core');

        if ($min > 0 && $max > 0)
        {
            return $this->language->lang('IORGIAN_CORE_HELP_MIN_MAX', $min, $max);
        }
        if ($min > 0)
        {
            return $this->language->lang('IORGIAN_CORE_HELP_MIN', $min);
        }
        if ($max > 0)
        {
            return $this->language->lang('IORGIAN_CORE_HELP_MAX', $max);
        }

        return '';
    }
}
