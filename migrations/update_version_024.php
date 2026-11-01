<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\migrations;

class update_version_024 extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\iorgian\core\migrations\update_version_023'];
    }

    public function update_data()
    {
        return [
            ['config.update', ['iorgian_core_version', '0.2.4-a1']],
        ];
    }
}
