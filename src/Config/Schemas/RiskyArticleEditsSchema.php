<?php

namespace MediaWiki\Extension\PersonalDashboard\Config\Schemas;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;
use MediaWiki\Extension\CommunityConfiguration\Schemas\MediaWiki\MediaWikiDefinitions;

class RiskyArticleEditsSchema extends JsonSchema {
	public const VERSION = '1.0.0';
	public const SCHEMA_PREVIOUS_VERSION = '1.0.0';
	public const PersonalDashboardRiskyArticleEdits = [
		self::TYPE => self::TYPE_OBJECT,
		self::PROPERTIES => [
			'enabled' => [
				self::TYPE => self::TYPE_BOOLEAN,
				self::DEFAULT => true,
			],
			'minimumUserEdits' => [
				self::TYPE => self::TYPE_INTEGER,
				self::DEFAULT => 0,
				self::MINIMUM => 0,
			],
			'learnmore' => [
				self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitle' ],
			],
			'helpdesk' => [
				self::REF => [ 'class' => MediaWikiDefinitions::class, 'field' => 'PageTitle' ],
			],
		],
	];
}
