<?php

namespace MediaWiki\Extension\CommunityConfiguration\Schemas\MediaWiki;

use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
class MediaWikiDefinitions extends JsonSchema {

	public const CommonsFile = [
		self::TYPE => self::TYPE_OBJECT,
		self::PROPERTIES => [
			'title' => [
				self::TYPE => self::TYPE_STRING,
				self::DEFAULT => '',
			],
			'url' => [
				self::TYPE => self::TYPE_STRING,
				self::DEFAULT => '',
			],
		],
		self::DEFAULT => [
			'title' => '',
			'url' => '',
		],
	];

	public const PageTitle = [
		self::TYPE => self::TYPE_STRING,
		self::DEFAULT => '',
	];

	public const PageTitles = [
		self::TYPE => self::TYPE_ARRAY,
		self::ITEMS => [
			self::TYPE => self::TYPE_STRING,
			self::DEFAULT => '',
		],
		self::DEFAULT => [],
	];

	public const Namespaces = [
		self::TYPE => self::TYPE_ARRAY,
		self::ITEMS => [
			self::TYPE => self::TYPE_INTEGER,
		],
		self::DEFAULT => [],
	];
}
