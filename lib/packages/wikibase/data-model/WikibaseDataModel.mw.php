<?php

/**
 * MediaWiki setup for the DataValues component of Wikibase.
 * The component should be included via the main entry point, DataValues.php.
 *
 * @since 0.4
 *
 * @file
 * @ingroup WikibaseDataModel
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */

if ( !defined( 'WIKIBASE_DATAMODEL_VERSION' ) ) {
	die( 'Not an entry point.' );
}

$GLOBALS['wgExtensionCredits']['wikibase'][] = array(
	'path' => __DIR__,
	'name' => 'Wikibase DataModel',
	'version' => WIKIBASE_DATAMODEL_VERSION,
	'author' => array(
		'[https://www.mediawiki.org/wiki/User:Jeroen_De_Dauw Jeroen De Dauw]',
		'The Wikidata team',
	),
	'url' => 'https://www.mediawiki.org/wiki/Extension:Wikibase_DataModel',
	'descriptionmsg' => 'wikibasedatamodel-desc'
);

$GLOBALS['wgExtensionMessagesFiles']['WikibaseDataModel'] = __DIR__ . '/WikibaseDataModel.i18n.php';
