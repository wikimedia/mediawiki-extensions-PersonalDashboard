<?php
/*
 * Lets phan run where DiscussionTools is not installed, which includes CI.
 *
 * This mirrors the part of DiscussionTools that DiscussionToolsThreadLookup
 * calls. A stub asserts a shape rather than checks it, so it cannot see a
 * signature that drifted; .phan/config.php drops this file wherever the real
 * code is present, and a local phan run against it is the check.
 */

namespace MediaWiki\Extension\DiscussionTools\Hooks {

	use MediaWiki\Extension\DiscussionTools\ContentThreadItemSetStatus;
	use MediaWiki\Revision\RevisionRecord;
	use MediaWiki\Title\Title;

	class HookUtils {

		public static function isAvailableForTitle( Title $title, ?string $feature = null ): bool {
			return false;
		}

		/**
		 * @param RevisionRecord $revRecord
		 * @param string|false $updateParserCacheFor
		 */
		public static function parseRevisionParsoidHtml(
			RevisionRecord $revRecord,
			$updateParserCacheFor
		): ContentThreadItemSetStatus {
			return new ContentThreadItemSetStatus();
		}
	}
}

namespace MediaWiki\Extension\DiscussionTools {

	use MediaWiki\Extension\DiscussionTools\ThreadItem\ContentHeadingItem;
	use StatusValue;

	class ContentThreadItemSetStatus extends StatusValue {

		public function getValueOrThrow(): ContentThreadItemSet {
			return new ContentThreadItemSet();
		}
	}

	class ContentThreadItemSet {

		/** @return ContentHeadingItem[] */
		public function getThreads(): array {
			return [];
		}
	}
}

namespace MediaWiki\Extension\DiscussionTools\ThreadItem {

	use DateTimeImmutable;

	interface ThreadItem {

		public function getId(): string;

		public function getParent(): ?ThreadItem;
	}

	abstract class ContentThreadItem implements ThreadItem {

		/** @return array[] */
		public function getAuthorsBelow(): array {
			return [];
		}

		public function getCommentCount(): int {
			return 0;
		}

		public function getLatestReply(): ?ContentCommentItem {
			return null;
		}

		public function getText(): string {
			return '';
		}

		public function getParent(): ?ThreadItem {
			return null;
		}

		public function getId(): string {
			return '';
		}
	}

	class ContentHeadingItem extends ContentThreadItem {

		public function getLinkableId(): string {
			return '';
		}

		public function isPlaceholderHeading(): bool {
			return false;
		}
	}

	class ContentCommentItem extends ContentThreadItem {

		public function getTimestamp(): DateTimeImmutable {
			return new DateTimeImmutable();
		}
	}
}
