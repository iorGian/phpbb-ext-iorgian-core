<?php
/**
 * Iorgian Core
 *
 * @copyright (c) Georgian Iordache
 * @license GNU General Public License, version 2 (GPL-2.0-only)
 */

namespace iorgian\core\controller;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * JSON endpoints used by the browser crypto module (iorgian_crypto.js).
 * All of them require a logged-in user; the one that writes also needs the CSRF hash
 * that GET /iorgian/keys/me hands out (same-origin only, so other sites cannot read it).
 */
class keys
{
    public const LINK_NAME = 'iorgian_keys';

    /** @var \iorgian\core\service\keys */
    protected $keys;
    /** @var \phpbb\user */
    protected $user;
    /** @var \phpbb\request\request_interface */
    protected $request;

    public function __construct(\iorgian\core\service\keys $keys, \phpbb\user $user, \phpbb\request\request_interface $request)
    {
        $this->keys = $keys;
        $this->user = $user;
        $this->request = $request;
    }

    /** GET: own bundle (may be null if no keys yet) + CSRF hash. */
    public function me(): JsonResponse
    {
        if (!$this->is_logged_in())
        {
            return $this->json(['error' => 'AUTH'], 403);
        }

        return $this->json([
            'bundle' => $this->keys->get_own_bundle((int) $this->user->data['user_id']),
            'hash'   => generate_link_hash(self::LINK_NAME),
        ]);
    }

    /** GET ?ids=1,2,3: public keys only. */
    public function public_keys(): JsonResponse
    {
        if (!$this->is_logged_in())
        {
            return $this->json(['error' => 'AUTH'], 403);
        }

        $ids = array_map('intval', explode(',', $this->request->variable('ids', '')));

        return $this->json(['keys' => (object) $this->keys->get_public_keys($ids)]);
    }

    /** POST: create / replace own bundle. */
    public function store(): JsonResponse
    {
        if (!$this->is_logged_in())
        {
            return $this->json(['error' => 'AUTH'], 403);
        }

        if (!check_link_hash($this->request->variable('hash', ''), self::LINK_NAME))
        {
            return $this->json(['error' => 'CSRF'], 403);
        }

        $errors = $this->keys->store(
            (int) $this->user->data['user_id'],
            $this->request->variable('public_key', ''),
            $this->request->variable('wrapped_key', ''),
            $this->request->variable('kdf_salt', ''),
            $this->request->variable('kdf_ops', 0),
            $this->request->variable('kdf_mem_kib', 0)
        );

        if ($errors)
        {
            return $this->json(['error' => 'INVALID', 'details' => $errors], 400);
        }

        return $this->json(['ok' => true]);
    }

    protected function is_logged_in(): bool
    {
        return (int) $this->user->data['user_id'] !== ANONYMOUS
            && (int) $this->user->data['user_type'] !== USER_IGNORE;
    }

    protected function json(array $data, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
