<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension, and must be run from within MediaWiki.' );
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/PhabricatorProvider.php';

class SpecialPhabricatorLogin extends SpecialPage 
{
    private $client;
    
    public function __construct() {
        
        parent::__construct( 'PhabricatorLogin', 'phabricatorlogin' );
        
        global $wgPhabLogin, $wgScriptPath;
        global $wgServer, $wgArticlePath;

        $this->client = new \League\OAuth2\Client\Provider\PhabricatorProvider(
            $wgPhabLogin['baseurl'],
            [
                'clientId'      => $wgPhabLogin['clientid'],
                'clientSecret'  => $wgPhabLogin['clientsecret'],
                'redirectUri'   => $wgServer . str_replace( '$1', 'Special:PhabricatorLogin/callback', $wgArticlePath ),
            ]
        );
        
    }
    
    public function execute( $parameter ){
        
        session_start();
        $this->setHeaders();
        
        switch($parameter){
            case 'redirect':
                $this->_redirect();
                break;
            case 'callback':
                $this->_handleCallback();
                break;
            case 'logout':
                $this->_logout();
                break;
            default:
                $this->_default();
            break;
        }
    }
    
    /**
     * Redirect to the Phabricator Instance
     */
    private function _redirect() {

        global $wgRequest;
        
        $_SESSION['returnto'] = $wgRequest->getVal( 'returnto' );
        
        if (!isset($_GET['code'])) {

            // Fetch the authorization URL from the provider; this returns the
            // urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state).
            $options = [
                'scope' => ['whoami'] 
            ];
            $authorizationUrl = $this->client->getAuthorizationUrl($options);

            // Get the state generated for you and store it to the session.
            $_SESSION['oauth2state'] = $this->client->getState();
            //Redirect the user to the authorization URL.
            header('Location: ' . $authorizationUrl);
            exit;

            // Check given state against previously stored one to mitigate CSRF attack
        } elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            exit('Invalid state');
        }
    }
    
    /**
     * When coming back from Phabricator the data is prepared here
     */
    private function _handleCallback() {
        global $wgPhabLogin, $wgRequest;
        try {

                // Try to get an access token using the authorization code grant.
                $accessToken = $this->client->getAccessToken('authorization_code', [
                    'code' => $_GET['code']
                ]);
                
                // We have an access token, which we may use in authenticated
                // requests against the service provider's API.
                $this->getOutput()->addWikiText("Access Token: " . $accessToken->getToken() . "\n");
                $this->getOutput()->addWikiText("Refresh Token: " .$accessToken->getRefreshToken() . "\n");
                $this->getOutput()->addWikiText("expires: " . $accessToken->getExpires() . "\n");
                $this->getOutput()->addWikiText(($accessToken->hasExpired() ? 'expired' : 'not expired') . "\n");

                // Using the access token, we may look up details about the
                // resource owner.
                $resourceOwner = $this->client->getResourceOwner($accessToken);
                
                //$resourceOwner = $resourceOwner->toArray();
                
                $user = $this->_userHandling( $resourceOwner );
		//$user->setCookies();
		
            } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

                // Failed to get the access token or user details.
                $this->getOutput()->addWikiText($e->getMessage());

            }

    }
    
    /**
     * Handle user login/verification
     *
     * @param  PhabricatorResourcOwner $resourceOwner
     * @return object
     */
    protected function _userHandling( $resourceOwner ) {
        global $wgPhabLogin, $wgAuth;
        
        $oauthId = $resourceOwner->getId();
        $oauthName = $resourceOwner->getName();
        $oauthNickname = $resourceOwner->getNickname();
        $oauthEmail = $resourceOwner->getEmail();
    }
    
    /**
     * No idea yet what to do here
     */
    private function _default() {
        global $wgUser;
        
        $output = $this->getOutput();
        
        $output->setPageTitle( wfMessage('phabricatorlogin-header-link-text')->text() );
        if ( !$wgUser->isLoggedIn() ) {
            $output->addWikiMsg( 'phabricatorlogin-login-with-phab' );
        }
    }
}
