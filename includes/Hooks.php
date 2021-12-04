<?php

namespace MediaWiki\Extension\MissedPages;

use Article;
use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\Page\Hook\ShowMissingArticleHook;

/**
 * Hook handlers for the MissedPages extension.
 */
class Hooks implements LoadExtensionSchemaUpdatesHook, ShowMissingArticleHook {

	/**
	 * Set up MissedPages's database table.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$sqlDir = dirname( __DIR__ ) . '/sql';
		$updater->addExtensionTable( MissedPages::TABLE_NAME, "$sqlDir/create_missed_pages_table.sql" );
	}

	/**
	 * Called when generating the output for a non-existent page.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/ShowMissingArticle
	 * @param Article $article
	 */
	public function onShowMissingArticle( $article ) {
		$log = new MissedPages();
		$log->recordMissingPage( $article );
	}
}
