<?php

/**
 * This file extends the league/oauth2-client library
 *
 * It implements a Provider for Phabricator Installations
 * 
 *
 * @copyright Copyright (c) Ingo Malchow <ingomalchow@gmail.com>
 * @license http://opensource.org/licenses/MIT MIT
 */
 
namespace League\OAuth2\Client\Provider;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/PhabricatorResourceOwner.php';

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class PhabricatorProvider extends AbstractProvider
{
    use BearerAuthorizationTrait;
    /**
     * Domain
     *
     * @var string
     */
    public $domain = 'https://example.com';
    
    public function __construct($url, array $options = [], array $collaborators = [])
    {
        parent::__construct($options, $collaborators);
        $this->domain = $url;
    }
    /**
     * Get authorization url to begin OAuth flow
     *
     * @return string
     */
    public function getBaseAuthorizationUrl()
    {
        return $this->domain.'/oauthserver/auth/';
    }
    
    /**
     * Get access token url to retrieve token
     *
     * @param  array $params
     *
     * @return string
     */
     
    public function getBaseAccessTokenUrl(array $params)
    {
        return $this->domain.'/oauthserver/token/';
    }
    /**
     * Get provider url to fetch user details
     *
     * @param  AccessToken $token
     *
     * @return string
     */
     
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return $this->domain.'/api/user.whoami?access_token=' . $token;
    }
    /**
     * Get the default scopes used by this provider.
     *
     * This should not be a complete list of all scopes, but the minimum
     * required for the provider user interface! Phabricator uses "whoami"
     * @TODO: make it configurable
     *
     * @return array
     */
     
    protected function getDefaultScopes()
    {
        return ['whoami'];
    }
    /**
     * Check a provider response for errors.
     *
     * @throws IdentityProviderException
     * @param  ResponseInterface $response
     * @param  string $data Parsed response data
     * @return void
     */
     
    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (!empty($data['error_description'])) {
            $error = $data['error_description'];
            $code  = isset($data['statusCode']) ? $data['statusCode'] : 0 ;
            throw new IdentityProviderException($error, $code, $data);
        }
    }
    /**
     * Generate a user object from a successful user details request.
     *
     * @param array $response
     * @param AccessToken $token
     * @return League\OAuth2\Client\Provider\ResourceOwnerInterface
     */
     
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        $user = new PhabricatorResourceOwner($response);
        return $user->setDomain($this->domain);
    }
}