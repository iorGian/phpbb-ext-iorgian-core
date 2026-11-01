<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\permission;

/**
 * Ayudante de permisos: ACL local por foro + pertenencia a grupos.
 */
class acl_helper
{
    /** @var \phpbb\auth\auth */
    protected $auth;
    /** @var \phpbb\db\driver\driver_interface */
    protected $db;
    /** @var \phpbb\user */
    protected $user;
    /** @var string */
    protected $table_prefix;
    /** @var array<int,int[]> caché por usuario */
    protected $group_cache = [];

    public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, \phpbb\user $user, string $table_prefix)
    {
        $this->auth = $auth;
        $this->db = $db;
        $this->user = $user;
        $this->table_prefix = $table_prefix;
    }

    /**
     * ¿Tiene el usuario actual la permisión local $option en el foro $forum_id?
     */
    public function can_in_forum(string $option, int $forum_id): bool
    {
        return (bool) $this->auth->acl_get($option, $forum_id);
    }

    /**
     * IDs de grupo (no pendientes) del usuario indicado, o del actual si es null.
     *
     * @return int[]
     */
    public function user_group_ids(?int $user_id = null): array
    {
        $user_id = $user_id ?? (int) $this->user->data['user_id'];

        if (isset($this->group_cache[$user_id]))
        {
            return $this->group_cache[$user_id];
        }

        $sql = 'SELECT group_id
            FROM ' . $this->table_prefix . 'user_group
            WHERE user_id = ' . (int) $user_id . '
                AND user_pending = 0';
        $result = $this->db->sql_query($sql);
        $ids = [];
        while ($row = $this->db->sql_fetchrow($result))
        {
            $ids[] = (int) $row['group_id'];
        }
        $this->db->sql_freeresult($result);

        return $this->group_cache[$user_id] = $ids;
    }

    /**
     * ¿Pertenece el usuario a alguno de los grupos dados?
     * Lista vacía = sin restricción (devuelve true).
     *
     * @param int[] $allowed_group_ids
     */
    public function in_any_group(array $allowed_group_ids, ?int $user_id = null): bool
    {
        if (!$allowed_group_ids)
        {
            return true;
        }

        return (bool) array_intersect(array_map('intval', $allowed_group_ids), $this->user_group_ids($user_id));
    }
}
