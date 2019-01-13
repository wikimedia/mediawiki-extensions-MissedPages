<?php

namespace MediaWiki\Extension\MissedPages;

use Article;
use Title;
use Wikimedia\Rdbms\IResultWrapper;
use WikitextContentHandler;

/**
 * The core of the MissedPages extension.
 */
class MissedPages {

	const TABLE_NAME = 'missed_pages';

	/**
	 * @param Article $article
	 */
	public function recordMissingPage( Article $article ) {
		$dbw = wfGetDB( DB_MASTER );
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
		$values = [
			'mp_datetime' => wfTimestampNow(),
			'mp_page_title' => $pageTitle,
		];
		$dbw->insert( static::TABLE_NAME, $values, __METHOD__ );
	}

	/**
	 * @return bool|IResultWrapper
	 */
	public function getLogEntries() {
		$dbr = wfGetDB( DB_REPLICA );
		$records = $dbr->select(
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
		return $records;
	}

	/**
	 * @return bool|IResultWrapper
	 */
	public function getIgnoredEntries() {
		$dbr = wfGetDB( DB_REPLICA );
		$records = $dbr->select(
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
		return $records;
	}

	/**
	 * Delete all instances of the given title/query combination from the 404 log.
	 *
	 * @param string $title
	 */
	public function delete( $title ) {
		$dbw = wfGetDB( DB_MASTER );
		$title = Title::newFromText( $title );
		$conds = [
			'mp_page_title' => $title->getPrefixedDBkey(),
		];
		$dbw->delete( static::TABLE_NAME, $conds, __METHOD__ );
	}

	/**
	 * Redirect one page to another.
	 *
	 * @param string $from
	 * @param string $to
	 */
	public function redirect( $from, $to ) {
		$source = Title::newFromTextThrow( $from );
		$target = Title::newFromTextThrow( $to );
		$sourcePage = new Article( $source );
		$wikitextContentHandler = new WikitextContentHandler();
		$sourcePage->getPage()->doEditContent(
			$wikitextContentHandler->makeRedirectContent( $target ),
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
		$dbw = wfGetDB( DB_MASTER );
		$title = Title::newFromText( $titleString );
		$dbw->startAtomic( __METHOD__ );
		// Delete previous log entries.
		$this->delete( $titleString );
		// Then add a new one with the current timestamp, and the ignore flag set.
		$values = [
			'mp_datetime' => wfTimestampNow(),
			'mp_page_title' => $title->getPrefixedDBkey(),
			'mp_ignore' => true,
		];
		$dbw->insert( static::TABLE_NAME, $values, __METHOD__ );
		$dbw->endAtomic( __METHOD__ );
	}

	/**
	 * Get daily totals of misses for the given page.
	 * @param string $titleString The page title.
	 * @return int[] Page counts, ordered by date.
	 */
	public function getDayCounts( $titleString ) {
		$dbr = wfGetDB( DB_REPLICA );
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
