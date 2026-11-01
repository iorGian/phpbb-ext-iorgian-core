<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\util;

/**
 * "Where to show it" option relative to a title: before, after or below.
 */
class position
{
    public const BEFORE = 'before';
    public const AFTER = 'after';
    public const BELOW = 'below';

    /** @return string[] */
    public function all(): array
    {
        return [self::BEFORE, self::AFTER, self::BELOW];
    }

    /** Valid position, or $default when the value is empty or unknown. */
    public function normalize($value, string $default = self::BEFORE): string
    {
        $value = (string) $value;

        return in_array($value, $this->all(), true) ? $value : $default;
    }

    /**
     * Template variable name for a position, e.g. var_key('IORGIAN_MP_', 'before') = IORGIAN_MP_BEFORE.
     * The extension assigns the HTML to that key; one template event file per position prints it.
     */
    public function var_key(string $prefix, string $position): string
    {
        return $prefix . strtoupper($this->normalize($position));
    }
}
