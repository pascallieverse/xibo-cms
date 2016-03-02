<?php
/*
 * Spring Signage Ltd - http://www.springsignage.com
 * Copyright (C) 2015 Spring Signage Ltd
 * (ApplicationFactory.php)
 */


namespace Xibo\Factory;


use League\OAuth2\Server\Util\SecureKey;
use Xibo\Entity\Application;
use Xibo\Exception\NotFoundException;

class ApplicationFactory extends BaseFactory
{
    public function create()
    {
        $application = new Application();
        // Make and ID/Secret
        $application->secret = SecureKey::generate(254);
        // Assign this user
        $application->userId = $this->getUser()->userId;
        return $application;
    }

    /**
     * Get by ID
     * @param $clientId
     * @return Application
     * @throws NotFoundException
     */
    public function getById($clientId)
    {
        $client = $this->query(null, ['clientId' => $clientId]);

        if (count($client) <= 0)
            throw new NotFoundException();

        return $client[0];
    }

    public function getByUserId($userId)
    {
        return $this->query(null, ['userId' => $userId]);
    }

    public function query($sortOrder = null, $filterBy = null)
    {
        $entries = array();
        $params = array();

        $select = '
            SELECT `oauth_clients`.id AS `key`,
                `oauth_clients`.secret,
                `oauth_clients`.name,
                `oauth_clients`.authCode,
                `oauth_clients`.clientCredentials,
                `oauth_clients`.userId ';

        $body = '
              FROM `oauth_clients`
        ';

        if ($this->getSanitizer()->getInt('userId', $filterBy) !== null) {

            $select .= '
                , `oauth_auth_codes`.expire_time AS expires
            ';

            $body .= '
                INNER JOIN `oauth_sessions`
                ON `oauth_sessions`.client_id = `oauth_clients`.id
                    AND `oauth_sessions`.owner_id = :userId
                INNER JOIN `oauth_auth_codes`
                ON `oauth_auth_codes`.session_id = `oauth_sessions`.id
            ';

            $params['userId'] = $this->getSanitizer()->getInt('userId', $filterBy);
        }

        $body .= ' WHERE 1 = 1 ';


        if ($this->getSanitizer()->getString('clientId', $filterBy) != null) {
            $body .= ' AND `oauth_clients`.id = :clientId ';
            $params['clientId'] = $this->getSanitizer()->getString('clientId', $filterBy);
        }

        // Sorting?
        $order = '';
        if (is_array($sortOrder))
            $order .= 'ORDER BY ' . implode(',', $sortOrder);

        $limit = '';
        // Paging
        if ($this->getSanitizer()->getInt('start', $filterBy) !== null && $this->getSanitizer()->getInt('length', $filterBy) !== null) {
            $limit = ' LIMIT ' . intval($this->getSanitizer()->getInt('start', $filterBy), 0) . ', ' . $this->getSanitizer()->getInt('length', 10, $filterBy);
        }

        // The final statements
        $sql = $select . $body . $order . $limit;

        foreach ($this->getStore()->select($sql, $params) as $row) {
            $entries[] = (new Application())->setContainer($this->getContainer())->hydrate($row);
        }

        // Paging
        if ($limit != '' && count($entries) > 0) {
            $results = $this->getStore()->select('SELECT COUNT(*) AS total ' . $body, $params);
            $this->_countLast = intval($results[0]['total']);
        }

        return $entries;
    }
}