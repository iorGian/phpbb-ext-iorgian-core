<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\util;

/**
 * ACP color field shared by the iorgian extensions: the browser's color selector (real-time,
 * no hexadecimal code needed), a text field that still accepts typed #rgb / #rrggbb codes, and an
 * optional "Default" button that empties the field.
 *
 * The extension puts the returned HTML in a template variable (raw) and reads the value back
 * from the POST field named $name, validating it with iorgian.core.colors.
 */
class color_field
{
    /** @var colors */
    protected $colors;
    /** @var \phpbb\language\language */
    protected $language;

    public function __construct(colors $colors, \phpbb\language\language $language)
    {
        $this->colors = $colors;
        $this->language = $language;
    }

    /**
     * @param string $name        field name and element id (letters, digits and underscore only)
     * @param string $value       current value, RAW (not HTML-escaped): it is escaped here
     * @param bool   $allow_reset show the "Default" button (for fields where empty is meaningful)
     * @return string HTML to print without further escaping
     */
    public function render(string $name, string $value, bool $allow_reset = true): string
    {
        $id = (string) preg_replace('/[^A-Za-z0-9_]/', '', $name);
        if ($id === '')
        {
            return '';
        }

        $this->language->add_lang('common', 'iorgian/core');

        $html = '<input type="color" id="' . $id . '_picker" value="' . $this->colors->picker_value($value) . '" /> '
            . '<input type="text" id="' . $id . '" name="' . $id . '" value="' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '" size="9" maxlength="7" />';

        if ($allow_reset)
        {
            $html .= ' <input type="button" class="button2" id="' . $id . '_reset" value="'
                . htmlspecialchars($this->language->lang('IORGIAN_CORE_COLOR_RESET'), ENT_COMPAT, 'UTF-8') . '" />';
        }

        $html .= '<script>(function () {'
            . 'var p = document.getElementById("' . $id . '_picker"), t = document.getElementById("' . $id . '"), r = document.getElementById("' . $id . '_reset");'
            . 'if (!p || !t) { return; }'
            . 'p.addEventListener("input", function () { t.value = p.value; });'
            . 't.addEventListener("input", function () { if (/^#[0-9a-fA-F]{6}$/.test(t.value)) { p.value = t.value; } });'
            . 'if (r) { r.addEventListener("click", function () { t.value = ""; }); }'
            . '})();</script>';

        return $html;
    }
}
