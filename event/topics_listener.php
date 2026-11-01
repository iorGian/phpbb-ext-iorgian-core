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
 * Turns phpBB's "topics are about to be moved" into one reliable event fired AFTER the move:
 *
 *   iorgian.core.topics_moved   (topic_ids: int[])
 *
 * Extensions that keep data linked to topics subscribe to it instead of guessing the destination
 * forum from the variables of core.move_topics_before_query: at that point every topic already has
 * its new forum_id in the database.
 */
class topics_listener implements EventSubscriberInterface
{
    /** @var \phpbb\event\dispatcher_interface */
    protected $dispatcher;

    /** @var int[] */
    protected $moved = [];

    public function __construct(\phpbb\event\dispatcher_interface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public static function getSubscribedEvents()
    {
        return [
            'core.move_topics_before_query' => 'collect',
            'core.garbage_collection'       => 'announce',
        ];
    }

    public function collect($event)
    {
        $this->moved = array_merge($this->moved, array_map('intval', (array) $event['topic_ids']));
    }

    public function announce($event)
    {
        if (!$this->moved)
        {
            return;
        }

        $topic_ids = array_values(array_unique($this->moved));
        $this->moved = [];

        $this->dispatcher->trigger_event('iorgian.core.topics_moved', ['topic_ids' => $topic_ids]);
    }
}
