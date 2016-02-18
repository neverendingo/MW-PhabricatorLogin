<?php

class PhabricatorLoginHooks {

    public static function onPersonalUrls( array &$personal_urls, &$title, SkinTemplate $skin ) {
        global $wgPhabLogin, $wgUser, $wgRequest;
        
        // Are we already logged in? if so, don't try a second time
        if( $wgUser->isLoggedIn() ) return true;

        $service_name = isset( $wgPhabLogin['name'] ) && 0 < strlen( $wgPhabLogin['name'] ) ? $wgPhabLogin['name'] : 'OAuth2';
        
        if( isset( $wgPhabLogin['login-text'] ) && 0 < strlen( $wgPhabLogin['login-text'] ) ) {
            $service_login_link_text = $wgPhabLogin['login-text'];
        } else {
            $service_login_link_text = wfMessage($service_name)->text();
        }
        
        // Remove account creation and normal login boxes
        foreach ( array( 'createaccount', 'login', 'anonlogin' ) as $k ) {
            if ( array_key_exists( $k, $personal_urls ) ) {
                unset( $personal_urls[$k] );
            }
        }

        $personal_urls['anon_oauth_login'] = array(
            'text' => $service_login_link_text,
            //'class' => ,
            'active' => false,
        );
        
        $personal_urls['anon_oauth_login']['href'] = Skin::makeSpecialUrlSubpage( 'PhabricatorLogin', 'redirect' );
                
        if( isset( $personal_urls['anonlogin'] ) ) {
            $personal_urls['anonlogin']['href'] = Skin::makeSpecialUrl( 'Userlogin' );
        }
        return true;
    }
    
    public static function onUserLogout( &$user ) {

        global $wgOut;
        
        return true;
    }
    
    public static function onLoadExtensionSchemaUpdates( $updater = null ) {
        switch ( $updater->getDB()->getType() ) {
            case "mysql":
                return self::MySQLSchemaUpdates( $updater );
            default:
                throw new MWException("PhabricatorLogin does not support {$updater->getDB()->getType()} yet.");
        }
    }
    
    /**
     * @param $updater MysqlUpdater
     * @return bool
     */
    public static function MySQLSchemaUpdates( $updater = null ) {
        $updater->addExtensionTable( 'external_user',
            dirname( __FILE__ ) . '/sql/phabricator-login.sql' );

        return true;
    }
}