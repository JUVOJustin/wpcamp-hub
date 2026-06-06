<?php
/**
 * A single page of a paginated WordCamp API collection.
 *
 * @package WPCAMP_HUB
 */

namespace WPCAMP_HUB\Import;

/**
 * Immutable carrier for one page of decoded API items plus pagination metadata.
 */
class WordCamp_Page {

	/**
	 * Decoded items on this page.
	 *
	 * @param list<array<string,mixed>> $items Decoded collection items.
	 * @param int                       $page Current 1-based page number.
	 * @param int                       $total_pages Total number of pages.
	 * @param int                       $total_items Total number of items across all pages.
	 */
	public function __construct(
		public readonly array $items,
		public readonly int $page,
		public readonly int $total_pages,
		public readonly int $total_items,
	) {
	}

	/**
	 * Whether a further page exists after this one.
	 */
	public function has_more(): bool {
		return $this->page < $this->total_pages;
	}

	/**
	 * The next page number, or null when this is the last page.
	 */
	public function next_page(): ?int {
		return $this->has_more() ? $this->page + 1 : null;
	}
}
