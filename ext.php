<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core;

class ext extends \phpbb\extension\base
{
    public const MIN_PHP   = '7.4.0';
    public const MIN_PHPBB = '3.3.0';

    /**
     * Comprueba requisitos mínimos antes de permitir la activación.
     * Devuelve true o un array de claves de idioma con el motivo del rechazo.
     */
    public function is_enableable()
    {
        $errors = [];

        if (phpbb_version_compare(PHP_VERSION, self::MIN_PHP, '<'))
        {
            $errors[] = 'IORGIAN_CORE_REQUIRES_PHP';
        }

        $phpbb_version = (string) $this->container->get('config')['version'];

        if (phpbb_version_compare($phpbb_version, self::MIN_PHPBB, '<'))
        {
            $errors[] = 'IORGIAN_CORE_REQUIRES_PHPBB';
        }

        if ($errors)
        {
            $this->container->get('language')->add_lang('common', 'iorgian/core');
            return array_map([$this->container->get('language'), 'lang'], $errors);
        }

        return true;
    }
}
