<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\service;

/**
 * Storage and validation of per-user key material.
 *
 * Zero-knowledge by design: this class only ever handles the PUBLIC key and a
 * private-key blob that was already wrapped in the browser with a key derived
 * from the user's passphrase. It cannot decrypt anything.
 */
class keys
{
    public const PUBLIC_KEY_BYTES  = 32;
    public const WRAPPED_KEY_BYTES = 72; // 24-byte nonce + 32-byte key + 16-byte MAC
    public const SALT_BYTES        = 16;

    public const KDF_OPS_MIN     = 2;
    public const KDF_OPS_MAX     = 12;
    public const KDF_MEM_KIB_MIN = 32768;   // 32 MiB: below this Argon2id is too cheap to resist guessing
    public const KDF_MEM_KIB_MAX = 1048576; // 1 GiB

    public const MAX_LOOKUP = 50;

    /** @var \phpbb\db\driver\driver_interface */
    protected $db;
    /** @var string */
    protected $table;

    public function __construct(\phpbb\db\driver\driver_interface $db, string $table)
    {
        $this->db = $db;
        $this->table = $table;
    }

    public function has_keys(int $user_id): bool
    {
        return $this->get_own_bundle($user_id) !== null;
    }

    /**
     * Everything the owner needs to unlock their identity in the browser.
     */
    public function get_own_bundle(int $user_id): ?array
    {
        $sql = 'SELECT public_key, wrapped_key, kdf_salt, kdf_ops, kdf_mem_kib, key_version
            FROM ' . $this->table . '
            WHERE user_id = ' . (int) $user_id;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$row)
        {
            return null;
        }

        return [
            'public_key'  => (string) $row['public_key'],
            'wrapped_key' => (string) $row['wrapped_key'],
            'kdf_salt'    => (string) $row['kdf_salt'],
            'kdf_ops'     => (int) $row['kdf_ops'],
            'kdf_mem_kib' => (int) $row['kdf_mem_kib'],
            'key_version' => (int) $row['key_version'],
        ];
    }

    /**
     * Public keys only (what anybody may know), keyed by user id.
     *
     * @param int[] $user_ids
     * @return array<int,array{public_key:string,key_version:int}>
     */
    public function get_public_keys(array $user_ids): array
    {
        $user_ids = array_slice(array_unique(array_filter(array_map('intval', $user_ids))), 0, self::MAX_LOOKUP);
        if (!$user_ids)
        {
            return [];
        }

        $sql = 'SELECT user_id, public_key, key_version
            FROM ' . $this->table . '
            WHERE ' . $this->db->sql_in_set('user_id', $user_ids);
        $result = $this->db->sql_query($sql);
        $out = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $out[(int) $row['user_id']] = [
                'public_key'  => (string) $row['public_key'],
                'key_version' => (int) $row['key_version'],
            ];
        }
        $this->db->sql_freeresult($result);

        return $out;
    }

    /**
     * Strict validation of what the browser sends. Returns error codes (empty = valid).
     *
     * @return string[]
     */
    public function validate(string $public_key, string $wrapped_key, string $kdf_salt, int $ops, int $mem_kib): array
    {
        $errors = [];

        if (!$this->is_b64_len($public_key, self::PUBLIC_KEY_BYTES))
        {
            $errors[] = 'BAD_PUBLIC_KEY';
        }
        if (!$this->is_b64_len($wrapped_key, self::WRAPPED_KEY_BYTES))
        {
            $errors[] = 'BAD_WRAPPED_KEY';
        }
        if (!$this->is_b64_len($kdf_salt, self::SALT_BYTES))
        {
            $errors[] = 'BAD_SALT';
        }
        if ($ops < self::KDF_OPS_MIN || $ops > self::KDF_OPS_MAX)
        {
            $errors[] = 'BAD_KDF_OPS';
        }
        if ($mem_kib < self::KDF_MEM_KIB_MIN || $mem_kib > self::KDF_MEM_KIB_MAX)
        {
            $errors[] = 'BAD_KDF_MEMORY';
        }

        return $errors;
    }

    /**
     * Create or replace the user's key bundle.
     * A different public key = new identity, so key_version is incremented
     * (clients use it to warn contacts that the key changed).
     * Re-wrapping the same key with a new passphrase keeps the version.
     *
     * @return string[] error codes, empty on success
     */
    public function store(int $user_id, string $public_key, string $wrapped_key, string $kdf_salt, int $ops, int $mem_kib): array
    {
        $errors = $this->validate($public_key, $wrapped_key, $kdf_salt, $ops, $mem_kib);
        if ($errors)
        {
            return $errors;
        }

        $existing = $this->get_own_bundle($user_id);
        $now = time();

        $data = [
            'public_key'   => $public_key,
            'wrapped_key'  => $wrapped_key,
            'kdf_salt'     => $kdf_salt,
            'kdf_ops'      => $ops,
            'kdf_mem_kib'  => $mem_kib,
            'updated_time' => $now,
        ];

        if ($existing === null)
        {
            $data += ['user_id' => $user_id, 'key_version' => 1, 'created_time' => $now];
            $this->db->sql_query('INSERT INTO ' . $this->table . ' ' . $this->db->sql_build_array('INSERT', $data));
        }
        else
        {
            $data['key_version'] = $existing['key_version'] + (hash_equals($existing['public_key'], $public_key) ? 0 : 1);
            $this->db->sql_query('UPDATE ' . $this->table . '
                SET ' . $this->db->sql_build_array('UPDATE', $data) . '
                WHERE user_id = ' . (int) $user_id);
        }

        return [];
    }

    /**
     * Remove a user's keys (e.g. when the user is deleted).
     */
    public function delete(int $user_id): void
    {
        $this->db->sql_query('DELETE FROM ' . $this->table . ' WHERE user_id = ' . (int) $user_id);
    }

    /**
     * Canonical standard base64 (with padding) decoding to exactly $bytes bytes.
     */
    protected function is_b64_len(string $value, int $bytes): bool
    {
        $raw = base64_decode($value, true);

        return $raw !== false
            && strlen($raw) === $bytes
            && base64_encode($raw) === $value;
    }
}
