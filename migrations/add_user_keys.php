<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\migrations;

/**
 * Per-user public key + passphrase-wrapped private key.
 * The server never stores a usable private key: only the public key and a
 * blob that can be opened exclusively with the user's own passphrase.
 */
class add_user_keys extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\iorgian\core\migrations\install_core'];
    }

    public function effectively_installed()
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'iorgian_user_keys');
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'iorgian_user_keys' => [
                    'COLUMNS' => [
                        'user_id'      => ['UINT', 0],
                        'public_key'   => ['VCHAR:64', ''],   // base64, 32 bytes
                        'wrapped_key'  => ['VCHAR:128', ''],  // base64, nonce(24) + secretbox(sk)(48) = 72 bytes
                        'kdf_salt'     => ['VCHAR:32', ''],   // base64, 16 bytes
                        'kdf_ops'      => ['USINT', 0],       // Argon2id passes
                        'kdf_mem_kib'  => ['UINT', 0],        // Argon2id memory in KiB
                        'key_version'  => ['USINT', 1],       // +1 every time the public key (identity) changes
                        'created_time' => ['TIMESTAMP', 0],
                        'updated_time' => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => 'user_id',
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_tables' => [$this->table_prefix . 'iorgian_user_keys'],
        ];
    }

    public function update_data()
    {
        return [
            ['config.update', ['iorgian_core_version', '0.2.0-a1']],
        ];
    }
}
