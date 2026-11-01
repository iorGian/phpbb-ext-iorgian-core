<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\util;

/**
 * User group helper.
 */
class groups
{
    /** @var \phpbb\db\driver\driver_interface */
    protected $db;
    /** @var \phpbb\group\helper */
    protected $group_helper;

    public function __construct(\phpbb\db\driver\driver_interface $db, \phpbb\group\helper $group_helper)
    {
        $this->db = $db;
        $this->group_helper = $group_helper;
    }

    /**
     * All groups as group_id => display name (translated for the built-in groups).
     *
     * @return array<int,string>
     */
    public function names(): array
    {
        $names = [];
        $sql = 'SELECT group_id, group_name
            FROM ' . GROUPS_TABLE . '
            ORDER BY group_type DESC, group_name ASC';
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result))
        {
            $names[(int) $row['group_id']] = $this->group_helper->get_name($row['group_name']);
        }
        $this->db->sql_freeresult($result);

        return $names;
    }
}
