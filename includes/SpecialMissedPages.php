<?php

namespace MediaWiki\Extension\MissedPages;

use Html;
use MediaWiki\Widget\TitleInputWidget;
use OOUI\ActionFieldLayout;
use OOUI\ButtonInputWidget;
use OOUI\HorizontalLayout;
use SpecialPage;
use stdClass;
use Title;

/**
 * Special Page for MissedPages extension.
 */
class SpecialMissedPages extends SpecialPage {

	/** @var string The user right required to add redirects. */
	protected $redirectRight = 'edit';

	/** @var string The user right required to ignore pages in the missed pages log. */
	protected $ignoreRight = 'block';

	/** @var string The user right required to delete pages from the missed pages log. */
	protected $deleteRight = 'delete';

	/**
	 * SpecialPage constructor.
	 * @param string $name
	 */
	public function __construct( $name = 'MissedPages' ) {
		parent::__construct( $name );
	}

	/**
	 * @return string
	 */
	protected function getGroupName() {
		return 'other';
	}

	/**
	 * Show the page to the user.
	 *
	 * @param string $sub The subpage string argument (if any).
	 */
	public function execute( $sub ) {
		$this->getOutput()->enableOOUI();
		$out = $this->getOutput();

		$out->setPageTitle( $this->msg( 'missedpages-special-page-title' ) );
		$out->addHelpLink( 'Help:Extension:MissedPages' );
		$out->addModules( 'ext.missedpages' );

		$log = new MissedPages();

		// Save any submitted data.
		if ( $this->getRequest()->wasPosted() ) {
			$postVals = $this->getRequest()->getPostValues();
			$redirect = false;
			if ( isset( $postVals['redirect'] ) && $this->getUser()->isAllowed( $this->redirectRight ) ) {
				$log->redirect( $postVals['redirect'], $postVals['redirect_target'][$postVals['redirect']] );
				$redirect = true;
			}
			if ( isset( $postVals['ignore'] ) && $this->getUser()->isAllowed( $this->ignoreRight ) ) {
				$log->ignore( $postVals['ignore'] );
				$redirect = true;
			}
			if ( isset( $postVals['delete'] ) && $this->getUser()->isAllowed( $this->deleteRight ) ) {
				$log->delete( $postVals['delete'] );
				$redirect = true;
			}
			if ( $redirect ) {
				// @TODO Add confirmation message.
				$out->redirect( $this->getPageTitle()->getCanonicalURL() );
				return;
			}
		}

		// Build the output.
		$rows = [];
		foreach ( $log->getLogEntries() as $logEntry ) {
			$rows[] = $this->getTableRow( $logEntry );
		}
		$headers = Html::rawElement( 'tr', [],
			Html::element( 'th', [], $this->msg( 'missedpages-count' )->plain() )
			. Html::element( 'th', [], $this->msg( 'missedpages-page' )->plain() )
			. Html::element( 'th', [], $this->msg( 'missedpages-actions' )->plain() )
		);
		$table = Html::rawElement(
			'table',
			[ 'class' => 'wikitable' ],
			$headers . implode( "\n", $rows )
		);
		$form = Html::rawElement( 'form', [
			'method' => 'post',
			'action' => $this->getPageTitle()->getInternalURL(),
			'class' => 'ext-missedpages',
		], $table );
		$out->addHTML( $form );
	}

	/**
	 * Get one TR for the given entry.
	 *
	 * @param stdClass $logEntry
	 * @return string
	 */
	protected function getTableRow( stdClass $logEntry ) {
		$countCell = Html::rawElement( 'td', [], $logEntry->count );
		$pageLink = $this->getLinkRenderer()->makeLink( Title::newFromText( $logEntry->mp_page_title ) );
		$pageCell = Html::rawElement( 'td', [], $pageLink );

		$ignoreButton = new ButtonInputWidget( [
			'type' => 'submit',
			'name' => 'ignore',
			'value' => $logEntry->mp_page_title,
			'label' => $this->msg( "missedpages-ignore" )->plain(),
			'title' => $this->msg( "missedpages-ignore-desc" )->plain(),
			// This isn't really the same as blocking a user, but it's a similar level of responsibility.
			'disabled' => !$this->getUser()->isAllowed( $this->ignoreRight ),
		] );
		$deleteButton = new ButtonInputWidget( [
			'type' => 'submit',
			'name' => 'delete',
			'value' => $logEntry->mp_page_title,
			'label' => $this->msg( "missedpages-delete" )->plain(),
			'title' => $this->msg( "missedpages-delete-desc" )->plain(),
			'disabled' => !$this->getUser()->isAllowed( $this->deleteRight ),
		] );
		$disableRedirectField = !$this->getUser()->isAllowed( $this->redirectRight );
		$redirectField = new ActionFieldLayout(
			new TitleInputWidget( [
				'name' => 'redirect_target[' . $logEntry->mp_page_title . ']',
				'infusable' => true,
				'disabled' => $disableRedirectField
			] ),
			new ButtonInputWidget( [
				'type' => 'submit',
				'name' => 'redirect',
				'value' => $logEntry->mp_page_title,
				'label' => $this->msg( 'missedpages-redirect' )->plain(),
				'title' => $this->msg( 'missedpages-redirect-desc' )->plain(),
				'disabled' => $disableRedirectField
			] )
		);
		$fields = new HorizontalLayout( [
			'items' => [ $redirectField, $ignoreButton, $deleteButton ],
		] );

		$actionCell = Html::rawElement( 'td', [], $fields->toString() );
		return Html::rawElement( 'tr', [], $countCell . $pageCell . $actionCell );
	}

}
