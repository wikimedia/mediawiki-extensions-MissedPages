<?php

namespace MediaWiki\Extension\MissedPages;

use Article;
use MediaWiki\MediaWikiServices;
use Title;
use User;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * The core of the MissedPages extension.
 */
class MissedPages {

	public const TABLE_NAME = 'missed_pages';

	/**
	 * @param Article $article
	 */
	public function recordMissingPage( Article $article ) {
		$dbw = MediaWikiServices::getInstance()
			->getDBLoadBalancer()
			->getConnection( DB_PRIMARY );
		$pageTitle = $article->getTitle()->getPrefixedDBkey();
		// See if it's ignored.
		$ignored = $dbw->selectRowCount(
			static::TABLE_NAME,
			'*',
			[
				'mp_page_title' => $pageTitle,
				'mp_ignore' => true,
			],
			__METHOD__
		);
		if ( $ignored ) {
			return;
		}
		// If not ignored, save the page data.
		$dbw->insert(
			static::TABLE_NAME,
			[
				'mp_datetime' => wfTimestampNow(),
				'mp_page_title' => $pageTitle,
			],
			__METHOD__
		);
	}

	/**
	 * @return bool|IResultWrapper
	 */
	public function getLogEntries() {
		$dbr = MediaWikiServices::getInstance()
			->getDBLoadBalancer()
			->getConnection( DB_REPLICA );
		return $dbr->select(
			static::TABLE_NAME,
			[
				'count' => 'COUNT(*)',
				'mp_page_title',
			],
			[ 'mp_ignore' => false ],
			__METHOD__,
			[
				'ORDER BY' => $dbr->addIdentifierQuotes( 'count' ) . ' DESC',
				'GROUP BY' => 'mp_page_title',
				// Arbitrary limit. A temporary measure until this is switched to a paged layout.
				'LIMIT' => 100,
			]
		);
	}

	/**
	 * @return bool|IResultWrapper
	 */
	public function getIgnoredEntries() {
		$dbr = MediaWikiServices::getInstance()
			->getDBLoadBalancer()
			->getConnection( DB_REPLICA );
		return $dbr->select(
			static::TABLE_NAME,
			[
				'count' => 'COUNT(mp_id)',
				'mp_page_title',
			],
			[ 'mp_ignore' => true ],
			__METHOD__,
			[
				'ORDER BY' => 'count DESC',
				'GROUP BY' => ' mp_page_title',
			]
		);
	}

	/**
	 * Delete all instances of the given title/query combination from the 404 log.
	 *
	 * @param string $title
	 */
	public function delete( $title ) {
		$dbw = MediaWikiServices::getInstance()
			->getDBLoadBalancer()
			->getConnection( DB_PRIMARY );
		$dbw->delete(
			static::TABLE_NAME,
			[
				'mp_page_title' => Title::newFromText( $title )->getPrefixedDBkey(),
			],
			__METHOD__
		);
	}

	/**
	 * Redirect one page to another.
	 *
	 * @param string $from
	 * @param string $to
	 * @param User $editor
	 */
	public function redirect( $from, $to, User $editor ) {
		$source = Title::newFromTextThrow( $from );
		$target = Title::newFromTextThrow( $to );
		$sourcePage = new Article( $source );
		$wikitextContentHandler = MediaWikiServices::getInstance()
			->getContentHandlerFactory()
			->getContentHandler( CONTENT_MODEL_WIKITEXT );
		$sourcePage->getPage()->doUserEditContent(
			$wikitextContentHandler->makeRedirectContent( $target ),
			$editor,
			wfMessage( 'missedpages-redirect-comment' )->parse()
		);
		$this->delete( $sourcePage->getTitle()->getPrefixedDBkey() );
	}

	/**
	 * Ignore the given title.
	 *
	 * @param string $titleString
	 */
	public function ignore( $titleString ) {
		$dbw = MediaWikiServices::getInstance()
			->getDBLoadBalancer()
			->getConnection( DB_PRIMARY );
		$title = Title::newFromText( $titleString );
		$dbw->startAtomic( __METHOD__ );
		// Delete previous log entries.
		$this->delete( $titleString );
		// Then add a new one with the current timestamp, with the ignore flag set.
		$dbw->insert(
			static::TABLE_NAME,
			[
				'mp_datetime' => wfTimestampNow(),
				'mp_page_title' => $title->getPrefixedDBkey(),
				'mp_ignore' => true,
			],
			__METHOD__
		);
		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Get daily totals of misses for the given page.
	 * @param string $titleString The page title.
	 * @return int[] Page counts, ordered by date.
	 */
	public function getDayCounts( $titleString ) {
		$dbr = MediaWikiServices::getInstance()
			->getDBLoadBalancer()
			->getConnection( DB_REPLICA );
		$records = $dbr->select(
			static::TABLE_NAME,
			[
				'count' => 'COUNT(mp_id)',
			],
			[
				'mp_ignore' => false,
				'mp_page_title' => $titleString,
			],
			__METHOD__,
			[
				'ORDER BY' => 'mp_datetime ASC',
				'GROUP BY' => 'YEAR(mp_datetime), MONTH(mp_datetime), DAY(mp_datetime)',
			]
		);
		$counts = [];
		foreach ( $records as $record ) {
			$counts[] = $record->count;
		}
		return $counts;
	}
}
