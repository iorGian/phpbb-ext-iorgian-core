<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\service;

/**
 * Punto único para aislar diferencias entre phpBB 3.3.x y 4.0.
 * Las extensiones de iorgian NO deben comparar versiones por su cuenta:
 * preguntan aquí.
 */
class compat
{
    /** @var \phpbb\config\config */
    protected $config;

    public function __construct(\phpbb\config\config $config)
    {
        $this->config = $config;
    }

    public function phpbb_version(): string
    {
        return (string) $this->config['version'];
    }

    public function is_phpbb3(): bool
    {
        return phpbb_version_compare($this->phpbb_version(), '4.0.0-a1', '<');
    }

    public function is_phpbb4(): bool
    {
        return !$this->is_phpbb3();
    }
}
