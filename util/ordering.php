<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\util;

/**
 * Generic "move up / move down" ordering for admin lists.
 * Table and column names must be developer-supplied constants, never user input.
 */
class ordering
{
    /** @var \phpbb\db\driver\driver_interface */
    protected $db;

    public function __construct(\phpbb\db\driver\driver_interface $db)
    {
        $this->db = $db;
    }

    /**
     * Next free position (max + 1).
     */
    public function next(string $table, string $order_col, string $where = ''): int
    {
        $sql = 'SELECT MAX(' . $order_col . ') AS max_order FROM ' . $table . ($where !== '' ? ' WHERE ' . $where : '');
        $result = $this->db->sql_query($sql);
        $max = (int) $this->db->sql_fetchfield('max_order');
        $this->db->sql_freeresult($result);

        return $max + 1;
    }

    /**
     * Move one row up or down and renumber the whole list (1..n).
     *
     * @param string $direction 'up' or 'down'
     * @return bool false if the row was not found or cannot move
     */
    public function move(string $table, string $id_col, string $order_col, int $id, string $direction, string $where = ''): bool
    {
        $sql = 'SELECT ' . $id_col . ' AS item_id FROM ' . $table
            . ($where !== '' ? ' WHERE ' . $where : '')
            . ' ORDER BY ' . $order_col . ' ASC, ' . $id_col . ' ASC';
        $result = $this->db->sql_query($sql);
        $ids = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $ids[] = (int) $row['item_id'];
        }
        $this->db->sql_freeresult($result);

        $pos = array_search($id, $ids, true);
        if ($pos === false)
        {
            return false;
        }

        $swap = ($direction === 'up') ? $pos - 1 : $pos + 1;
        if (!isset($ids[$swap]))
        {
            return false;
        }

        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];

        foreach ($ids as $index => $item_id)
        {
            $this->db->sql_query('UPDATE ' . $table . ' SET ' . $order_col . ' = ' . ($index + 1) . ' WHERE ' . $id_col . ' = ' . (int) $item_id);
        }

        return true;
    }
}
