<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\service;

/**
 * Structural validation of the armored envelopes produced by iorgian_crypto.js.
 * It cannot (and does not try to) read the content: it only tells whether a string
 * has the exact shape of a valid envelope, so servers can refuse anything else.
 *
 * Layout (before base64): [1] version, [4] sender id (big endian), [1] recipient count,
 * [24] content nonce, count x ([8] key id, [24] nonce, [48] boxed content key), then the ciphertext.
 */
class envelope
{
    public const VERSION         = 1;
    public const MAX_ARMORED     = 1048576; // bytes of armored text
    public const MAX_RECIPIENTS  = 255;
    public const HEADER_BYTES    = 30;      // version + sender + count + content nonce
    public const ENTRY_BYTES     = 80;      // key id + nonce + boxed key
    public const MIN_CIPHERTEXT  = 17;      // at least one byte plus the 16-byte MAC

    /**
     * @return array{sender:int,recipients:int,key_ids:string[]}|null  null when the string is not a valid envelope
     */
    public function parse(string $armored): ?array
    {
        if ($armored === '' || strlen($armored) > self::MAX_ARMORED)
        {
            return null;
        }

        $raw = base64_decode($armored, true);
        if ($raw === false || $raw === '' || base64_encode($raw) !== $armored)
        {
            return null;
        }

        $len = strlen($raw);
        if ($len < self::HEADER_BYTES + self::ENTRY_BYTES + self::MIN_CIPHERTEXT || ord($raw[0]) !== self::VERSION)
        {
            return null;
        }

        $unpacked = unpack('N', substr($raw, 1, 4));
        $sender = (int) $unpacked[1];
        $count = ord($raw[5]);

        if ($sender < 0 || $count < 1 || $count > self::MAX_RECIPIENTS
            || $len < self::HEADER_BYTES + $count * self::ENTRY_BYTES + self::MIN_CIPHERTEXT)
        {
            return null;
        }

        $ids = [];
        for ($i = 0; $i < $count; $i++)
        {
            $ids[bin2hex(substr($raw, self::HEADER_BYTES + $i * self::ENTRY_BYTES, 8))] = true;
        }

        if (count($ids) !== $count)
        {
            return null; // duplicated key id
        }

        return ['sender' => $sender, 'recipients' => $count, 'key_ids' => array_map('strval', array_keys($ids))];
    }
}
