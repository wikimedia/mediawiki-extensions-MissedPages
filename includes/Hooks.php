<?php

namespace MediaWiki\Extension\MissedPages;

use Article;
use DatabaseUpdater;

/**
 * Hook handlers for the MissedPages extension.
 */
class Hooks {

	/**
	 * Set up MissedPages's database table.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$sqlDir = dirname( __DIR__ ) . '/sql';
		$updater->addExtensionTable( MissedPages::TABLE_NAME, "$sqlDir/create_missed_pages_table.sql" );
	}

	/**
	 * Called when generating the output for a non-existent page.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/ShowMissingArticle
	 * @param Article $article
	 */
	public static function onShowMissingArticle( Article $article ) {
		$log = new MissedPages();
		$log->recordMissingPage( $article );
	}
}
