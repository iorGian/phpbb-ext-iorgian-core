<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\util;

/**
 * Forum list helper. Always reads the forums table directly (no query cache),
 * so a newly created forum is visible immediately. (phpBB's make_forum_select()
 * caches its query for 10 minutes.)
 */
class forums
{
    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    public function __construct(\phpbb\db\driver\driver_interface $db)
    {
        $this->db = $db;
    }

    /**
     * All forums in tree order, with a 'depth' key (0 = top level).
     *
     * @return array[]
     */
    public function all(): array
    {
        $sql = 'SELECT forum_id, forum_name, parent_id, forum_type, left_id, right_id
            FROM ' . FORUMS_TABLE . '
            ORDER BY left_id ASC';
        $result = $this->db->sql_query($sql);

        $rows = [];
        $stack = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            while ($stack && end($stack) < (int) $row['left_id'])
            {
                array_pop($stack);
            }

            $row['forum_id'] = (int) $row['forum_id'];
            $row['forum_type'] = (int) $row['forum_type'];
            $row['depth'] = count($stack);
            $stack[] = (int) $row['right_id'];
            $rows[] = $row;
        }
        $this->db->sql_freeresult($result);

        return $rows;
    }

    /**
     * Only forums that can contain topics (not categories or links).
     *
     * @return array[]
     */
    public function postable(): array
    {
        return array_values(array_filter($this->all(), function ($row)
        {
            return $row['forum_type'] === FORUM_POST;
        }));
    }

    /**
     * <option> list for a multi/single select. Categories are shown as disabled
     * headings to keep the hierarchy readable; links are skipped.
     * Forum names are already HTML-safe in phpBB's database, so they are output as stored.
     *
     * @param int[] $selected forum IDs to mark as selected
     */
    public function options(array $selected = []): string
    {
        $selected = array_map('intval', $selected);
        $html = '';

        foreach ($this->all() as $row)
        {
            if ($row['forum_type'] === FORUM_LINK)
            {
                continue;
            }

            $pad = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $row['depth']);

            if ($row['forum_type'] === FORUM_POST)
            {
                $is_selected = in_array($row['forum_id'], $selected, true) ? ' selected="selected"' : '';
                $html .= '<option value="' . $row['forum_id'] . '"' . $is_selected . '>' . $pad . $row['forum_name'] . '</option>';
            }
            else
            {
                $html .= '<option value="" disabled="disabled">' . $pad . $row['forum_name'] . '</option>';
            }
        }

        return $html;
    }
}
