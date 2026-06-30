<?php

namespace MediaWiki\Extension\PersonalDashboard\HookHandler;

use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\FilePath;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;

class ResourceLoaderRegisterModulesHandler implements ResourceLoaderRegisterModulesHook {
	/** @inheritDoc */
	public function onResourceLoaderRegisterModules( ResourceLoader $rl ): void {
		$rl->register( [
			'vue-router' => [
				'packageFiles' => [
					[
						'name' => 'resources/lib/vue-router/vue-router.js',
						'callback' => static function ( Context $context, Config $config ) {
							// Use the development version if development mode is enabled, or if we're in debug mode
							$file = $config->get( MainConfigNames::ExtensionDirectory ) . '/PersonalDashboard/' .
								( $config->get( MainConfigNames::VueDevelopmentMode ) || $context->getDebug() ?
									'resources/lib/vue-router/vue-router.global.js' :
									'resources/lib/vue-router/vue-router.global.prod.js' );
							// The file shipped by Vue Router does var VueRouter = ...;, but doesn't export it
							// Add module.exports = VueRouter; programmatically, and import Vue
							return 'var Vue=require("vue");' .
								file_get_contents( $file ) .
								';module.exports=VueRouter;';
						},
						'versionCallback' => static function ( Context $context, Config $config ) {
							$file = $config->get( MainConfigNames::ExtensionDirectory ) . '/PersonalDashboard/' .
								( $config->get( MainConfigNames::VueDevelopmentMode ) || $context->getDebug() ?
									'resources/lib/vue-router/vue-router.global.js' :
									'resources/lib/vue-router/vue-router.global.prod.js' );
							return new FilePath( $file );
						}
					],
				],
				'dependencies' => [
					'vue',
				],
			],
		] );
	}
}
