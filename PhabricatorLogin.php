<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is a MediaWiki extension, and must be run from within MediaWiki.' );
}

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'PhabricatorLogin' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['PhabricatorLogin'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['PhabricatorLoginAlias'] = __DIR__ . '/PhabricatorLogin.alias.php';

	/* wfWarn(
		'Deprecated PHP entry point used for Nuke extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */

	return true;
} else {
	die( 'This version of the PhabricatorLogin extension requires MediaWiki 1.27+' );
}
