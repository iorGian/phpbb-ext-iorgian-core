<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Housekeeping only. Core deliberately never listens to private-message events.
 */
class listener implements EventSubscriberInterface
{
    /** @var \iorgian\core\service\keys */
    protected $keys;

    public function __construct(\iorgian\core\service\keys $keys)
    {
        $this->keys = $keys;
    }

    public static function getSubscribedEvents()
    {
        return [
            'core.delete_user_after' => 'delete_user_keys',
        ];
    }

    /** Remove key material of deleted users. */
    public function delete_user_keys($event)
    {
        foreach ((array) $event['user_ids'] as $user_id)
        {
            $this->keys->delete((int) $user_id);
        }
    }
}
