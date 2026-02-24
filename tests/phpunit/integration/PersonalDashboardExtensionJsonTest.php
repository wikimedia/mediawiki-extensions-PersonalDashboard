<?php
use MediaWiki\Tests\ExtensionJsonTestBase;

/**
 * @group Test
 * @group PersonalDashboard
 * @coversNothing
 */
class PersonalDashboardExtensionJsonTest extends ExtensionJsonTestBase {

	/** @inheritDoc */
	protected static bool $requireHookHandlers = true;

	/** @inheritDoc */
	protected static string $extensionJsonPath = __DIR__ . '/../../../extension.json';

}
