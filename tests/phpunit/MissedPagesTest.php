<?php

namespace MediaWiki\Extension\MissedPages\Test;

use Article;
use MediaWiki\Extension\MissedPages\MissedPages;
use MediaWikiTestCase;
use Title;
use WikiPage;

/**
 * @group Database
 * @group extensions
 * @group MissedPages
 */
class MissedPagesTest extends MediaWikiTestCase {

	public function setUp() {
		parent::setUp();
		$this->tablesUsed = [ 'missed_pages' ];
	}

	/**
	 * @covers \MediaWiki\Extension\MissedPages\MissedPages::recordMissingPage()
	 */
	public function testBasics() {
		$log = new MissedPages();
		$testPage1 = new Article( Title::newFromText( __METHOD__ . 'Test_page_1' ) );
		$log->recordMissingPage( $testPage1 );
		static::assertCount( 1, $log->getLogEntries() );
		$testPage2 = new Article( Title::newFromText( __METHOD__ . 'Test_page_2' ) );
		$log->recordMissingPage( $testPage2 );
		static::assertCount( 2, $log->getLogEntries() );
	}

	/**
	 * @covers \MediaWiki\Extension\MissedPages\MissedPages::delete()
	 */
	public function testDelete() {
		$log = new MissedPages();
		$testTitle = __METHOD__ . 'Test page to delete';
		$testPage1 = new Article( Title::newFromText( $testTitle ) );
		$log->recordMissingPage( $testPage1 );
		$log->recordMissingPage( $testPage1 );
		$log->recordMissingPage( $testPage1 );
		static::assertCount( 1, $log->getLogEntries() );
		static::assertEquals( 3, $log->getLogEntries()->current()->count );
		$log->delete( $testTitle );
		static::assertCount( 0, $log->getLogEntries() );
	}

	/**
	 * @covers \MediaWiki\Extension\MissedPages\MissedPages::redirect()
	 */
	public function testAddRedirect() {
		$log = new MissedPages();
		$from = new Article( Title::newFromText( __METHOD__ . 'Test_page' ) );

		// Add a page to the log, and then redirect it.
		$log->recordMissingPage( $from );
		static::assertCount( 1, $log->getLogEntries() );
		$log->redirect( $from->getTitle()->getText(), 'Test_target' );
		static::assertCount( 0, $log->getLogEntries() );

		// Check that the redirect syntax was inserted correctly.
		$fromPage = new WikiPage( $from->getTitle() );
		static::assertEquals( '#REDIRECT [[Test target]]',
			$fromPage->getContent()->getNativeData()
		);

		// Check that the log was emptied.
		static::assertCount( 0, $log->getLogEntries() );

		// Subsequent requests for the original page should result in a log entry for the
		// redirect target.
	}

	/**
	 * @covers \MediaWiki\Extension\MissedPages\MissedPages::ignore()
	 */
	public function testIgnorePage() {
		$log = new MissedPages();
		$testPage = new Article( Title::newFromText( 'Test_page_to_ignore' ) );
		$log->ignore( $testPage->getTitle()->getText() );
		$log->recordMissingPage( $testPage );
		static::assertCount( 0, $log->getLogEntries() );
		static::assertCount( 1, $log->getIgnoredEntries() );
		$log->recordMissingPage( $testPage );
		static::assertCount( 0, $log->getLogEntries() );
	}
}
