<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension, and must be run from within MediaWiki.' );
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/includes/PhabricatorProvider.php';

use MediaWiki\Session\SessionManager;

class SpecialPhabricatorLogin extends SpecialPage 
{
	// save an instance of the oauth2 login
    private $client;
    
    // The oauth data about the selected user
    private $resourceOwner;
    
    private $extuser;
    
    // Allow only logins with Phabricator
    private $phabOnlyLogin = false;
    
    // save the returnto path
    private $returnto = "";
    
    public function __construct() {
        parent::__construct( 'PhabricatorLogin', 'phabricatorlogin' );
        $this->listed = true;
        
        global $wgPhabLogin, $wgServer, $wgArticlePath;
        
        if( !isset($wgPhabLogin['phabonly']) || $wgPhabLogin['phabonly'] === null ) {
            $this->phabOnlyLogin = $wgPhabLogin['phabonly'];
        }

        $this->client = new Mediawiki\Extensions\PhabricatorLogin\PhabricatorProvider(
            $wgPhabLogin['baseurl'],
            [
                'clientId'      => $wgPhabLogin['clientid'],
                'clientSecret'  => $wgPhabLogin['clientsecret'],
                'redirectUri'   => $wgServer . str_replace( '$1', 'Special:PhabricatorLogin/callback', $wgArticlePath ),
            ]
        );
        
    }
    
    public function doesWrites() {
    	return true;
    }
    
    public function execute( $parameter ){
    	    	
    	$session = SessionManager::getGlobalSession();
    	$session->persist();
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
            case 'finish':
            	$this->_finish();
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
    	global $wgCookieExpiration;
    	
        $request = $this->getRequest();
        
        if (!isset($_GET['code'])) {

        	$this->returnto = $_GET['returnto'];
        	$request->response()->setcookie("Phablogin", $this->returnto, time() + $wgCookieExpiration );
        	
            // Fetch the authorization URL from the provider; this returns the
            // urlAuthorize option and generates and applies any necessary parameters
            // (e.g. state).
            $options = [
                'scopes' => ['whoami', 'offline_access'] 
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
            $this->getOutput()->addWikiText('Invalid state');
        }
    }
    
    /**
     * When coming back from Phabricator, take care of the login
     */
    private function _handleCallback() {
        global $wgPhabLogin, $wgUser;
        
        $out = $this->getOutput();
        $request = $this->getRequest();
        
        try {

        	// Try to get an access token using the authorization code grant.
            $accessToken = $this->client->getAccessToken('authorization_code', [
            	'code' => $_GET['code']
            ]);
				
            $resourceOwner = $this->client->getResourceOwner($accessToken);
            
            // Let's get an oauth user
            $oauthId = $resourceOwner->getId();
            $oauthName = $resourceOwner->getName();
            $oauthNickname = $resourceOwner->getNickname();
            $oauthEmail = $resourceOwner->getEmail();
            
            // the external id is formed from clientid + phid
            $externalId = $wgPhabLogin['clientid'] . "." . $oauthId;
            
            $dbr = wfGetDB( DB_SLAVE );
            $extuser = PhabricatorUser::newFromEmail($externalId, $oauthNickname, $oauthEmail, $accessToken->getToken(), wfGetDB( DB_SLAVE ) );
            
            // Phabricator User already exists
            if ( 0 !== $extuser->getLocalId() ) {
            	$user = User::newFromId( $extuser->getLocalId() );
            	$extuser->updateInDatabase( wfGetDB( DB_MASTER ) );
            	$user->invalidateCache();
            	$wgUser = $user;
            	$this->getContext()->setUser( $user );
            	$user->setCookies($request, $this->isSecure() ? true: false , true);
            	
            	$out->addWikiMsg( 'phabricatorlogin-successful' );
            	$returnto = Title::newFromText($request->getcookie("Phablogin"));
            	$out->addReturnTo($returnto);
            // No Phabricator User yet
            } else {
            	$_SESSION['phid'] =  $externalId;
            	$_SESSION['external_name'] =  $oauthNickname;
            	$_SESSION['external_email'] =  $oauthEmail;
            	$_SESSION['phab_token'] = $accessToken->getToken();
            	$this->chooseAccount($resourceOwner, $extuser);
            }
            
            if( false === $extuser || $extuser->getRemoteId() != 0 ) {
            	throw new MWException('Unable to create new user account, please contact the Wiki administrator');
            }
            
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

        	// Failed to get the access token or user details.
            $this->getOutput()->addWikiText($e->getMessage());

        }

    }

    private function _default() {
        global $wgUser;
        
        $out = $this->getOutput();
        
        $out->setPageTitle( wfMessage('phabricatorlogin-header-link-text')->text() );
        if ( !$wgUser->isLoggedIn() ) {
            $out->addWikiMsg( 'phabricatorlogin-login-with-phab' );
        }
    }

    private function _finish() {
    	global $wgUser;
    	
    	$dbw = wfGetDB( DB_MASTER );
    	$out = $this->getOutput();
    	$request = $this->getRequest();
    	 
    	// Try to link Phabricator User to an existing local user
    	if ( $request->getVal("wpNameChoice") === "existing") {
    		$user = User::newFromName( $request->getVal("username_existing") );
    		if ( true === $user->checkPassword( $request->getVal("wpExistingPassword") ) ) {
    			$extuser = PhabricatorUser::newFromRemoteId($_SESSION['phid'], $_SESSION['external_name'], $_SESSION['phab_token'], $dbw );
    			$extuser->setLocalId($user->getId());
    			$extuser->setAccessToken( $_SESSION['phab_token'] );
    			$extuser->setTimestamp(new \MWTimestamp());
    			$extuser->setEmail( $_SESSION['external_email'] );
    			$extuser->addToDatabase( $dbw );
    			$wgUser = $user;
    			$this->getContext()->setUser( $user );
    			$user->setCookies($request, $this->isSecure() ? true: false , true);
    			$out->addWikiMsg( 'phabricatorlogin-successful' );
    			$returnto = Title::newFromText($request->getcookie("Phablogin"));
    			$out->addReturnTo($returnto);
    		} else {
    			$out->addWikiMsg( 'phabricatorlogin-wrong-password' );
    		}
    		 
    	// The Phabricator User is new
    	} else if ( $request->getVal("wpNameChoice") === "new" ) {
    		$user = User::newFromName( $request->getVal("username_new") );
    		// The given local user already exists
    		if ( 0 !== $user->getId() ) {
    			$out->addWikiMsg( 'phabricatorlogin-already-exists' );
    		} else {
    			$oauthEmail = $_SESSION['external_email'];
    			if( strlen($oauthEmail) > 0 ) {
    				$user->setEmail($oauthEmail);
    			}
    			$user->addToDatabase();
    			$extuser = PhabricatorUser::newFromRemoteId($_SESSION['phid'], $_SESSION['external_name'], $_SESSION['phab_token'], $dbw );
    			$extuser->setLocalId($user->getId());
    			$extuser->setTimestamp(new \MWTimestamp());
    			$extuser->setEmail( $user->getEmail() );
    			$extuser->addToDatabase( $dbw );
    			$user->addNewUserLogEntry( 'create' );
    			$wgUser = $user;
    			$this->getContext()->setUser( $user );
    			$user->setCookies($request, $this->isSecure() ? true: false , true);
    			$returnto = Title::newFromText($request->getcookie("Phablogin"));
            	$out->addReturnTo($returnto);
    		}
    	}
    
    }
    
    public function chooseAccount( $resourceOwner, $user ) {
    	global $wgAuth, $wgHiddenPrefs, $wgScript, $wgCookieExpiration;
    	$user = $this->getUser();
    	$out = $this->getOutput();
    	$request = $this->getRequest();
    	
    	$expirationDays = ceil( $wgCookieExpiration / ( 3600 * 24 ) );
    	// Check for a username in the cookies
    	global $wgCookiePrefix;
    	$name = '';
    	if ( isset( $_COOKIE["{$wgCookiePrefix}UserName"] ) ) {
    		$name = trim( $_COOKIE["{$wgCookiePrefix}UserName"] );
    	}
    	
    	$def = false;
    	
    	$out->addWikiMsg( 'phabricator-chooseinstructions' );
    	// Prepare Form for account setup
    	$out->addHTML( Xml::openElement( 'form', array(	'id' => 'specialphabricator', 'action' => $this->getPageTitle()->getLocalUrl() . "/finish", 'method' => 'POST'	) ) );
    	
    	// Choose a local existing user
    	$out->addHTML( Xml::openElement( 'div') );
		$out->addHTML( Xml::radio( 'wpNameChoice', 'existing', !$def, array('id' => 'wpNameChoiceExisting') ) );
		$out->addHTML( Xml::label( wfMessage( 'phabricator-chooseexisting' )->text(), 'wpNameChoiceExisting' ) . "<br />");
    	$out->addHTML( Xml::element( 'input', array( 'name' => 'username_existing', 'type' => 'text', 'value' => '' ) ) );
    	$out->addHTML( Xml::password( 'wpExistingPassword' ) );
    	$out->addHTML( Xml::closeElement( 'div') );
    	
    	$out->addHTML( Xml::openElement( 'div') );
    	$out->addHTML( Xml::radio( 'wpNameChoice', 'new', !$def, array('id' => 'wpNameChoiceNew') ) );
    	$out->addHTML( Xml::label( wfMessage( 'phabricator-choosenew' )->text(), 'wpNameChoiceNew' ) . "<br />");
    	$out->addHTML( Xml::element( 'input', array( 'name' => 'username_new', 'type' => 'text', 'value' => '', 'width' => "50%", 'placeholder' => $name ) ) );
    	
    	$out->addHTML( Xml::closeElement( 'div') );
    	
    	$out->addHTML( Xml::check('wpRemember', $user->getOption( 'rememberpassword' ) ? true: false ));
    	$out->addHTML( Xml::label( wfMessage( 'remembermypassword' )->numParams( $expirationDays )->text(), 'wpRemember' ) . "<br />");
    	
		$out->addHTML( Xml::submitButton( $this->msg( 'phabricator-use-this' )->text() ) );
    	$out->addHTML( Html::Hidden( 'phabricatorChooseNameBeforeLoginToken', LoginForm::getLoginToken() ) );
    	$out->addHTML( Xml::closeElement( 'form' ) );
    	$oauthAttributes = $resourceOwner->toArray();
    	$oauthAttributesNew = array();
    }
    
    function isSecure() {
    	return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    }
}
