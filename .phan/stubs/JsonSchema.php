<?php

namespace MediaWiki\Extension\CommunityConfiguration\Schema;

abstract class JsonSchema {

	public const TYPE = 'type';
	public const TYPE_OBJECT = 'type_object';
	public const PROPERTIES = 'properties';
	public const TYPE_BOOLEAN = 'type_boolean';
	public const TYPE_INTEGER = 'type_integer';
	public const DEFAULT = 'default';
	public const MINIMUM = 'minimum';
	public const REF = 'ref';
}
