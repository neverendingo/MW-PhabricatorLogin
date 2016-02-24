# Mediawiki Phabricator-Login Extension

This extension enables the use of the builtin Phabricator Oauth2 Server.
It uses the [OAuth2 Client Library] availabe via Composer.

## Installation

Clone or copy to your extensions directory, then
```bash
composer install
```

The necessary changes to LocalSettings.php are
```php
wfLoadExtension( 'PhabricatorLogin');
// Necessary Settings
$wgPhabLogin['name'] = '<give the login a name>';
$wgPhabLogin['clientid'] = '<your client id>';
$wgPhabLogin['clientsecret'] = '<your client secret>';
$wgPhabLogin['baseurl'] = '<the base url to your phabricator installation>';
// Optional
$wgPhabLogin['login-text'] = '<the login text>';
$wgPhabLogin['phabonly'] = true; // Should core mw logins be disallowed?
```

[OAuth2 Client Library]: http://oauth2-client.thephpleague.com/