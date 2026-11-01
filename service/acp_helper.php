<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\service;

/**
 * Small helpers shared by the ACP controllers of the iorgian extensions: form keys, success / error
 * messages with the "back" link, delete confirmation and signed move up / down links.
 * Only meant to be used while an ACP page is being handled (it wraps phpBB's ACP functions).
 */
class acp_helper
{
    /** @var \phpbb\request\request */
    protected $request;

    public function __construct(\phpbb\request\request $request)
    {
        $this->request = $request;
    }

    public function add_form_key(string $name): void
    {
        add_form_key($name);
    }

    public function check_form_key(string $name): bool
    {
        return (bool) check_form_key($name);
    }

    /** Shows a success message with the back link and stops. */
    public function success(string $message, string $u_action): void
    {
        trigger_error($message . adm_back_link($u_action));
    }

    /** Shows a warning with the back link and stops. */
    public function fail(string $message, string $u_action): void
    {
        trigger_error($message . adm_back_link($u_action), E_USER_WARNING);
    }

    /**
     * Delete confirmation. Returns true once the administrator has confirmed.
     * Otherwise it shows the confirmation box (and stops).
     *
     * @param array $hidden fields to resend, e.g. ['id' => 5, 'action' => 'delete']
     */
    public function confirm(string $message, array $hidden): bool
    {
        if (confirm_box(true))
        {
            return true;
        }

        confirm_box(false, $message, build_hidden_fields($hidden));

        return false;
    }

    /** URL of an action of the current ACP page (already HTML-escaped). */
    public function action_url(string $u_action, string $action, int $id = 0): string
    {
        return $u_action . '&amp;action=' . $action . ($id ? '&amp;id=' . $id : '');
    }

    /**
     * Signed "move up" / "move down" links for one row.
     *
     * @return string[] ['up' => url, 'down' => url]
     */
    public function move_urls(string $u_action, string $link_name, int $id): array
    {
        $hash = generate_link_hash($link_name . '_' . $id);

        return [
            'up'   => $u_action . '&amp;action=move_up&amp;id=' . $id . '&amp;hash=' . $hash,
            'down' => $u_action . '&amp;action=move_down&amp;id=' . $id . '&amp;hash=' . $hash,
        ];
    }

    /** Validates the hash of a move link built with move_urls(). */
    public function check_move(string $link_name, int $id): bool
    {
        return (bool) check_link_hash($this->request->variable('hash', ''), $link_name . '_' . $id);
    }

    /** Integer field of the posted form, limited to [$min, $max]. */
    public function int_field(string $field, int $min, int $max, int $default = 0): int
    {
        return max($min, min($max, $this->request->variable($field, $default)));
    }
}
