<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\util;

/**
 * Keeps phpBB's sorting parameters (st, sk, sd) when an extension builds its own links
 * (filters, pagination).
 */
class sort_params
{
    /** @var \phpbb\request\request */
    protected $request;

    public function __construct(\phpbb\request\request $request)
    {
        $this->request = $request;
    }

    /**
     * Query string fragment such as "&amp;st=7&amp;sk=t&amp;sd=d" (empty when no sort parameter is present).
     */
    public function query(): string
    {
        $query = '';
        foreach (['st', 'sk', 'sd'] as $name)
        {
            if ($this->request->is_set($name))
            {
                $query .= '&amp;' . $name . '=' . rawurlencode($this->request->variable($name, ''));
            }
        }

        return $query;
    }
}
