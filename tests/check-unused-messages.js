#!/usr/bin/env node
'use strict';

/**
 * Check that every i18n message key defined in en.json files is
 * referenced somewhere in the extension's source code.
 *
 * Message directories are read from MessagesDirs in extension.json.
 * Keys built dynamically in code must be documented in a code comment
 * listing the possible messages, per MediaWiki convention — this
 * checker (like grep and Codesearch) will find them there.
 *
 * Keys that are intentionally absent from the code but still expected
 * to exist in en.json (e.g. messages MediaWiki core reads on its own)
 * are listed in i18n-ignore.json at the repo root.
 *
 * Exits non-zero if unused messages are found, so it fails `npm test`.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const ROOT = process.cwd();
const IGNORE_FILE = path.join( ROOT, 'i18n-ignore.json' );
const SOURCE_EXTENSIONS = new Set( [ '.php', '.js', '.json', '.vue' ] );
const EXCLUDED_DIRS = new Set( [ 'node_modules', 'vendor', 'coverage', 'dist' ] );

function readJson( filePath ) {
	return JSON.parse( fs.readFileSync( filePath, 'utf8' ) );
}

// Read message directories from MessagesDirs in extension.json.
function getMessageDirs() {
	const manifestPath = path.join( ROOT, 'extension.json' );
	if ( !fs.existsSync( manifestPath ) ) {
		throw new Error( 'no extension.json found in ' + ROOT );
	}
	const conf = readJson( manifestPath );
	if ( !conf.MessagesDirs ) {
		throw new Error( 'extension.json has no MessagesDirs.' );
	}
	return Object.values( conf.MessagesDirs )
		.flat()
		.map( ( dir ) => path.join( ROOT, dir ) );
}

// Collect message keys from every en.json under the message directories.
function getMessageKeys( messageDirs ) {
	const keys = [];
	for ( const dir of messageDirs ) {
		( function walk( d ) {
			for ( const entry of fs.readdirSync( d, { withFileTypes: true } ) ) {
				const full = path.join( d, entry.name );
				if ( entry.isDirectory() ) {
					walk( full );
				} else if ( entry.name === 'en.json' ) {
					for ( const key of Object.keys( readJson( full ) ) ) {
						if ( key !== '@metadata' ) {
							keys.push( { key, file: path.relative( ROOT, full ) } );
						}
					}
				}
			}
		}( dir ) );
	}
	return keys;
}

// Concatenate all source file contents into one searchable string,
// skipping the message directories themselves.
function getSourceBlob( messageDirs ) {
	const messageDirSet = new Set( messageDirs.map( ( d ) => path.resolve( d ) ) );
	const chunks = [];
	( function walk( dir ) {
		for ( const entry of fs.readdirSync( dir, { withFileTypes: true } ) ) {
			const full = path.join( dir, entry.name );
			if ( entry.isDirectory() ) {
				// Hidden directories hold tooling state, not our source; reading
				// them makes an unused key look referenced.
				if (
					entry.name.startsWith( '.' ) ||
					EXCLUDED_DIRS.has( entry.name ) ||
					messageDirSet.has( path.resolve( full ) )
				) {
					continue;
				}
				walk( full );
			} else if (
				full !== IGNORE_FILE &&
				SOURCE_EXTENSIONS.has( path.extname( entry.name ) )
			) {
				chunks.push( fs.readFileSync( full, 'utf8' ) );
			}
		}
	}( ROOT ) );
	return chunks.join( '\n' );
}

// Keys expected to exist in en.json despite having no code reference.
function getIgnoredKeys() {
	return new Set( fs.existsSync( IGNORE_FILE ) ? readJson( IGNORE_FILE ) : [] );
}

function main() {
	const messageDirs = getMessageDirs();
	const keys = getMessageKeys( messageDirs );
	const blob = getSourceBlob( messageDirs );
	const ignored = getIgnoredKeys();

	const unused = keys.filter( ( { key } ) => !ignored.has( key ) && !blob.includes( key ) );

	if ( unused.length ) {
		console.error( `Found ${ unused.length } message key(s) not referenced in source:\n` );
		for ( const { key, file } of unused ) {
			console.error( `  ${ key }  (${ file })` );
		}
		console.error(
			'\nIf a key is constructed dynamically, document the possible\n' +
			'messages in a code comment where the key is built. If it is\n' +
			'expected to exist without a code reference, list it in\n' +
			'i18n-ignore.json.'
		);
		process.exitCode = 1;
		return;
	}

	console.log( `check-unused-messages: all ${ keys.length } message keys are referenced.` );
}

try {
	main();
} catch ( e ) {
	console.error( 'check-unused-messages: ' + e.message );
	process.exitCode = 1;
}
