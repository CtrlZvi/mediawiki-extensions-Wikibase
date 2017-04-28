<?php

namespace Wikibase;

use Action;
use BaseTemplate;
use EchoEvent;
use EditPage;
use IContextSource;
use OutputPage;
use Parser;
use Skin;
use StubObject;
use Title;
use UnexpectedValueException;
use User;
use Wikibase\Client\DataAccess\Scribunto\Scribunto_LuaWikibaseEntityLibrary;
use Wikibase\Client\DataAccess\Scribunto\Scribunto_LuaWikibaseLibrary;
use Wikibase\Client\Hooks\BaseTemplateAfterPortletHandler;
use Wikibase\Client\Hooks\BeforePageDisplayHandler;
use Wikibase\Client\Hooks\DeletePageNoticeCreator;
use Wikibase\Client\Hooks\EchoNotificationsHandlers;
use Wikibase\Client\Hooks\EditActionHookHandler;
use Wikibase\Client\Hooks\InfoActionHookHandler;
use Wikibase\Client\Specials\SpecialPagesWithBadges;
use Wikibase\Client\Specials\SpecialUnconnectedPages;
use Wikibase\Client\Specials\SpecialEntityUsage;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Lib\AutoCommentFormatter;
use Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookupFactory;

/**
 * File defining the hook handlers for the Wikibase Client extension.
 *
 * @license GPL-2.0+
 */
final class ClientHooks {

	/**
	 * @see NamespaceChecker::isWikibaseEnabled
	 *
	 * @param int $namespace
	 *
	 * @return bool
	 */
	protected static function isWikibaseEnabled( $namespace ) {
		return WikibaseClient::getDefaultInstance()->getNamespaceChecker()->isWikibaseEnabled( $namespace );
	}

	/**
	 * Hook to add PHPUnit test cases.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 *
	 * @param string[] &$paths
	 *
	 * @return bool
	 */
	public static function registerUnitTests( array &$paths ) {
		$paths[] = __DIR__ . '/tests/phpunit/';

		return true;
	}

	/**
	 * External library for Scribunto
	 *
	 * @param string $engine
	 * @param array $extraLibraries
	 * @return bool
	 */
	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ) {
		$allowDataTransclusion = WikibaseClient::getDefaultInstance()->getSettings()->getSetting( 'allowDataTransclusion' );
		if ( $engine == 'lua' && $allowDataTransclusion === true ) {
			$extraLibraries['mw.wikibase'] = Scribunto_LuaWikibaseLibrary::class;
			$extraLibraries['mw.wikibase.entity'] = Scribunto_LuaWikibaseEntityLibrary::class;
		}

		return true;
	}

	/**
	 * Handler for the FormatAutocomments hook, implementing localized formatting
	 * for machine readable autocomments generated by SummaryFormatter.
	 *
	 * @param string &$comment reference to the autocomment text
	 * @param bool $pre true if there is content before the autocomment
	 * @param string $auto the autocomment unformatted
	 * @param bool $post true if there is content after the autocomment
	 * @param Title|null $title use for further information
	 * @param bool $local shall links be generated locally or globally
	 * @param string|null $wikiId The ID of the wiki the comment applies to, if not the local wiki.
	 *
	 * @return bool
	 */
	public static function onFormat( &$comment, $pre, $auto, $post, $title, $local, $wikiId = null ) {
		global $wgContLang;

		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$repoId = $wikibaseClient->getSettings()->getSetting( 'repoSiteId' );

		// Only do special formatting for comments from a wikibase repo.
		// XXX: what to do if the local wiki is the repo? For entity pages, RepoHooks has a handler.
		// But what to do for other pages? Note that if the local wiki is the repo, $repoId will be
		// false, and $wikiId will be null.
		if ( $wikiId !== $repoId ) {
			return;
		}

		StubObject::unstub( $wgContLang );

		$formatter = new AutoCommentFormatter( $wgContLang, array( 'wikibase-entity' ) );
		$formattedComment = $formatter->formatAutoComment( $auto );

		if ( is_string( $formattedComment ) ) {
			$comment = $formatter->wrapAutoComment( $pre, $formattedComment, $post );
		}
	}

	/**
	 * Add Wikibase item link in toolbox
	 *
	 * @param BaseTemplate $baseTemplate
	 * @param array $toolbox
	 *
	 * @return bool
	 */
	public static function onBaseTemplateToolbox( BaseTemplate $baseTemplate, array &$toolbox ) {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$skin = $baseTemplate->getSkin();
		$title = $skin->getTitle();
		$idString = $skin->getOutput()->getProperty( 'wikibase_item' );
		$entityId = null;

		if ( $idString !== null ) {
			$entityIdParser = $wikibaseClient->getEntityIdParser();
			$entityId = $entityIdParser->parse( $idString );
		} elseif ( $title && Action::getActionName( $skin ) !== 'view' && $title->exists() ) {
			// Try to load the item ID from Database, but only do so on non-article views,
			// (where the article's OutputPage isn't available to us).
			$entityId = self::getEntityIdForTitle( $title );
		}

		if ( $entityId !== null ) {
			$repoLinker = $wikibaseClient->newRepoLinker();
			$toolbox['wikibase'] = array(
				'text' => $baseTemplate->getMsg( 'wikibase-dataitem' )->text(),
				'href' => $repoLinker->getEntityUrl( $entityId ),
				'id' => 't-wikibase'
			);
		}

		return true;
	}

	/**
	 * @param Title|null $title
	 *
	 * @return EntityId|null
	 */
	private static function getEntityIdForTitle( Title $title = null ) {
		if ( $title === null || !self::isWikibaseEnabled( $title->getNamespace() ) ) {
			return null;
		}

		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$entityIdLookup = $wikibaseClient->getStore()->getEntityIdLookup();
		return $entityIdLookup->getEntityIdForTitle( $title );
	}

	/**
	 * Add the connected item prefixed id as a JS config variable, for gadgets etc.
	 *
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 *
	 * @return bool
	 */
	public static function onBeforePageDisplayAddJsConfig( OutputPage &$out, Skin &$skin ) {
		$prefixedId = $out->getProperty( 'wikibase_item' );

		if ( $prefixedId !== null ) {
			$out->addJsConfigVars( 'wgWikibaseItemId', $prefixedId );
		}

		return true;
	}

	/**
	 * Adds css for the edit links sidebar link or JS to create a new item
	 * or to link with an existing one.
	 *
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 *
	 * @return bool
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$namespaceChecker = WikibaseClient::getDefaultInstance()->getNamespaceChecker();
		$beforePageDisplayHandler = new BeforePageDisplayHandler( $namespaceChecker );

		$actionName = Action::getActionName( $skin->getContext() );
		$beforePageDisplayHandler->addModules( $out, $actionName );

		return true;
	}

	/**
	 * Initialise beta feature preferences
	 *
	 * @param User $user
	 * @param array $betaPreferences
	 *
	 * @return bool
	 */
	public static function onGetBetaFeaturePreferences( User $user, array &$betaPreferences ) {
		global $wgExtensionAssetsPath;

		preg_match( '+' . preg_quote( DIRECTORY_SEPARATOR ) . '(?:vendor|extensions)'
			. preg_quote( DIRECTORY_SEPARATOR ) . '.*+', __DIR__, $remoteExtPath );

		$assetsPath = $wgExtensionAssetsPath . DIRECTORY_SEPARATOR . '..' . $remoteExtPath[0];

		$settings = WikibaseClient::getDefaultInstance()->getSettings();
		if ( !$settings->getSetting( 'otherProjectsLinksBeta' ) || $settings->getSetting( 'otherProjectsLinksByDefault' ) ) {
			return true;
		}

		$betaPreferences['wikibase-otherprojects'] = array(
			'label-message' => 'wikibase-otherprojects-beta-message',
			'desc-message' => 'wikibase-otherprojects-beta-description',
			'screenshot' => array(
				'ltr' => $assetsPath . '/resources/images/wb-otherprojects-beta-ltr.svg',
				'rtl' => $assetsPath . '/resources/images/wb-otherprojects-beta-rtl.svg'
			),
			'info-link' => 'https://www.mediawiki.org/wiki/Wikibase/Beta_Features/Other_projects_sidebar',
			'discussion-link' => 'https://www.mediawiki.org/wiki/Talk:Wikibase/Beta_Features/Other_projects_sidebar'
		);

		return true;
	}

	/**
	 * Adds a preference for showing or hiding Wikidata entries in recent changes
	 *
	 * @param User $user
	 * @param array[] &$prefs
	 *
	 * @return bool
	 */
	public static function onGetPreferences( User $user, array &$prefs ) {
		$settings = WikibaseClient::getDefaultInstance()->getSettings();

		if ( !$settings->getSetting( 'showExternalRecentChanges' ) ) {
			return true;
		}

		$prefs['rcshowwikidata'] = array(
			'type' => 'toggle',
			'label-message' => 'wikibase-rc-show-wikidata-pref',
			'section' => 'rc/advancedrc',
		);

		$prefs['wlshowwikibase'] = array(
			'type' => 'toggle',
			'label-message' => 'wikibase-watchlist-show-changes-pref',
			'section' => 'watchlist/advancedwatchlist',
		);

		return true;
	}

	/**
	 * Register the parser functions.
	 *
	 * @param Parser $parser
	 *
	 * @return bool
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		WikibaseClient::getDefaultInstance()->getParserFunctionRegistrant()->register( $parser );

		return true;
	}

	/**
	 * Adds the Entity ID of the corresponding Wikidata item in action=info
	 *
	 * @param IContextSource $context
	 * @param array $pageInfo
	 *
	 * @return bool
	 */
	public static function onInfoAction( IContextSource $context, array &$pageInfo ) {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$settings = $wikibaseClient->getSettings();

		$namespaceChecker = $wikibaseClient->getNamespaceChecker();
		$usageLookup = $wikibaseClient->getStore()->getUsageLookup();
		$labelDescriptionLookupFactory = new LanguageFallbackLabelDescriptionLookupFactory(
			$wikibaseClient->getLanguageFallbackChainFactory(),
			$wikibaseClient->getTermLookup(),
			$wikibaseClient->getTermBuffer()
		);
		$idParser = $wikibaseClient->getEntityIdParser();

		$infoActionHookHandler = new InfoActionHookHandler(
			$namespaceChecker,
			$wikibaseClient->newRepoLinker(),
			$wikibaseClient->getStore()->getSiteLinkLookup(),
			$settings->getSetting( 'siteGlobalID' ),
			$usageLookup,
			$labelDescriptionLookupFactory,
			$idParser
		);

		$pageInfo = $infoActionHookHandler->handle( $context, $pageInfo );

		return true;
	}

	/**
	 * Adds the Entity usage data in ActionEdit
	 *
	 * @param EditPage $editor
	 * @param OutputPage $output
	 * @param int $tabindex
	 */
	public static function onEditAction( EditPage $editor, OutputPage $output, &$tabindex ) {
		if ( $editor->preview || $editor->section ) {
			// Shorten out, like template transclusion in core
			return;
		}

		$editActionHookHandler = EditActionHookHandler::newFromGlobalState(
			$editor->getContext()
		);
		$editActionHookHandler->handle( $editor );

		$output->addModules( 'wikibase.client.action.edit.collapsibleFooter' );
	}

	/**
	 * Notify the user that we have automatically updated the repo or that they
	 * need to do that per hand.
	 *
	 * @param Title $title
	 * @param OutputPage $out
	 *
	 * @return bool
	 */
	public static function onArticleDeleteAfterSuccess( Title $title, OutputPage $out ) {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		$siteLinkLookup = $wikibaseClient->getStore()->getSiteLinkLookup();
		$repoLinker = $wikibaseClient->newRepoLinker();

		$deletePageNotice = new DeletePageNoticeCreator(
			$siteLinkLookup,
			$wikibaseClient->getSettings()->getSetting( 'siteGlobalID' ),
			$repoLinker
		);

		$html = $deletePageNotice->getPageDeleteNoticeHtml( $title );

		$out->addHTML( $html );

		return true;
	}

	/**
	 * @param BaseTemplate $skinTemplate
	 * @param string $name
	 * @param string &$html
	 *
	 * @return boolean
	 */
	public static function onBaseTemplateAfterPortlet( BaseTemplate $skinTemplate, $name, &$html ) {
		$handler = new BaseTemplateAfterPortletHandler();
		$link = $handler->getEditLink( $skinTemplate, $name );

		if ( $link ) {
			$html .= $link;
		}
	}

	public static function onwgQueryPages( &$queryPages ) {
		$queryPages[] = array( SpecialUnconnectedPages::class, 'UnconnectedPages' );
		$queryPages[] = array( SpecialPagesWithBadges::class, 'PagesWithBadges' );
		$queryPages[] = array( SpecialEntityUsage::class, 'EntityUsage' );
		return true;
	}

	/**
	 * Do special hook registrations.  These are affected by ordering issues and/or
	 * conditional on another extension being registered.
	 *
	 * @see https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:$wgExtensionFunctions
	 */
	public static function onExtensionLoad() {
		global $wgHooks;

		// These hooks should only be run if we use the Echo extension
		if ( class_exists( EchoEvent::class ) ) {
			$wgHooks['LocalUserCreated'][] = EchoNotificationsHandlers::class . '::onLocalUserCreated';
			$wgHooks['WikibaseHandleChange'][] = EchoNotificationsHandlers::class . '::onWikibaseHandleChange';
		}

		// This is in onExtensionLoad to ensure we register our
		// ChangesListSpecialPageStructuredFilters after ORES's.
		//
		// However, ORES is not required.
		//
		// recent changes / watchlist hooks
		$wgHooks['ChangesListSpecialPageStructuredFilters'][] =
			'\Wikibase\Client\Hooks\ChangesListSpecialPageHookHandlers::onChangesListSpecialPageStructuredFilters';
	}

}
