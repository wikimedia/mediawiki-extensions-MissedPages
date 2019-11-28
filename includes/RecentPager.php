<?php

namespace MediaWiki\Extension\MissedPages;

use IndexPager;
use TablePager;
use Title;

class RecentPager extends TablePager {

	public $mDefaultDirection = IndexPager::DIR_DESCENDING;

	/**
	 * This function should be overridden to provide all parameters
	 * needed for the main paged query. It returns an associative
	 * array with the following elements:
	 *    tables => Table(s) for passing to Database::select()
	 *    fields => Field(s) for passing to Database::select(), may be *
	 *    conds => WHERE conditions
	 *    options => option array
	 *    join_conds => JOIN conditions
	 *
	 * @return array
	 */
	public function getQueryInfo() {
		return [
			'tables' => 'missed_pages',
			'fields' => 'mp_id, mp_datetime, mp_page_title',
			'conds' => [ 'mp_ignore = 0' ],
			'options' => [],
			'join_conds' => [],
		];
	}

	/**
	 * Return true if the named field should be sortable by the UI, false
	 * otherwise
	 *
	 * @param string $field
	 * @return bool
	 */
	public function isFieldSortable( $field ) {
		return false;
	}

	/**
	 * Format a table cell. The return value should be HTML, but use an empty
	 * string not &#160; for empty cells. Do not include the <td> and </td>.
	 *
	 * The current result row is available as $this->mCurrentRow, in case you
	 * need more context.
	 *
	 * @protected
	 *
	 * @param string $name The database field name
	 * @param string $value The value retrieved from the database
	 *
	 * @return string
	 */
	public function formatValue( $name, $value ) {
		if ( $name === 'mp_page_title' ) {
			return $this->getLinkRenderer()->makeLink( Title::newFromText( $value ) );
		}
		return $value;
	}

	/**
	 * The database field name used as a default sort order.
	 *
	 * @protected
	 *
	 * @return string
	 */
	public function getDefaultSort() {
		return 'mp_datetime';
	}

	/**
	 * An array mapping database field names to a textual description of the
	 * field name, for use in the table header. The description should be plain
	 * text, it will be HTML-escaped later.
	 *
	 * @return array
	 */
	public function getFieldNames() {
		return [
			'mp_id' => $this->msg( 'missedpages-id' )->text(),
			'mp_datetime' => $this->msg( 'missedpages-datetime' )->text(),
			'mp_page_title' => $this->msg( 'missedpages-page' )->text(),
		];
	}
}
