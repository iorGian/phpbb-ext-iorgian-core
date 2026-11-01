<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\migrations;

class install_core extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\phpbb\db\migration\data\v330\v330'];
    }

    public function update_data()
    {
        return [
            ['config.add', ['iorgian_core_version', '1.0.0-dev']],
        ];
    }
}
