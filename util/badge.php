<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\util;

/**
 * Colored badge (pill) renderer shared by iorgian extensions.
 * Styling comes from the .iorgian-badge class in this extension's stylesheet.
 */
class badge
{
    protected const FALLBACK_BG = '#6c757d';

    /** @var colors */
    protected $colors;

    public function __construct(colors $colors)
    {
        $this->colors = $colors;
    }

    /**
     * @param string $label         Text shown in the badge
     * @param string $bg            Background color (#rgb / #rrggbb); invalid = neutral grey
     * @param string $text          Text color; empty = black or white chosen for contrast
     * @param string $extra_class   Extra CSS class(es), developer supplied (never user input)
     * @param bool   $label_is_safe True if $label is already HTML-escaped (phpBB stores most names that way)
     */
    public function render(string $label, string $bg = '', string $text = '', string $extra_class = '', bool $label_is_safe = false): string
    {
        $bg = $this->colors->normalize($bg) ?? self::FALLBACK_BG;
        $text = ($text !== '') ? $this->colors->normalize($text) : null;
        $text = $text ?? $this->colors->contrast_text($bg);

        if (!$label_is_safe)
        {
            $label = htmlspecialchars($label, ENT_COMPAT, 'UTF-8');
        }

        return sprintf(
            '<span class="%s" style="background-color:%s;color:%s">%s</span>',
            trim('iorgian-badge ' . $extra_class),
            $bg,
            $text,
            $label
        );
    }
}
