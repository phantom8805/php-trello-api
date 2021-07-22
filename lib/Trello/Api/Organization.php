<?php

namespace Trello\Api;

/**
 * Trello Organization API
 * @link https://trello.com/docs/api/organization
 *
 * Not implemented.
 */
class Organization extends AbstractApi
{
    const USER_TYPES = [
        self::USER_TYPE_ADMIN,
        self::USER_TYPE_NORMAL,
    ];

    const USER_TYPE_ADMIN = 'admin';
    const USER_TYPE_NORMAL = 'normal';
    /**
     * Base path of organizations api
     * @var string
     */
    protected $path = 'organizations';

    /**
     * Organization fields
     * @link https://trello.com/docs/api/organization/#get-1-organizations-idorg-or-name-field
     * @var array
     */
    public static $fields = [
        'name',
        'displayName',
        'desc',
        'descData',
        'idBoards',
        'invited',
        'invitations',
        'memberships',
        'prefs',
        'powerUps',
        'products',
        'billableMemberCount',
        'url',
        'website',
        'logoHash',
        'premiumFeatures',
    ];

    /**
     * Find an organization by id
     * @link https://trello.com/docs/api/organization/#get-1-organizations-idorg-or-name
     *
     * @param string $id the organization's id
     * @param array  $params optional attributes
     *
     * @return array
     */
    public function show($id, array $params = [])
    {
        return $this->get($this->getPath() . '/' . rawurlencode($id), $params);
    }

    public function boards($id, array $params = [])
    {
        return $this->get($this->getPath() . '/' . rawurlencode($id) . '/boards', $params);
    }

    public function members($id, array $params = [])
    {
        return $this->get($this->getPath() . '/' . rawurlencode($id) . '/memberships', [...$params, 'member' => true]);
    }

    public function invite(string $id, string $email, string $fullName, string $type)
    {
        return $this->put($this->getPath() . '/' . rawurlencode($id) . '/members', [
            'email'    => $email,
            'fullName' => $fullName,
            'type'     => $type,
        ]);
    }
}
