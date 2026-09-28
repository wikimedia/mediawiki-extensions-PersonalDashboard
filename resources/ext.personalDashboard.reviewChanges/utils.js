// The module moves to ModeratorToolkit later, so the option name has that prefix.
const PERSONALIZE_OPTION_NAME = 'moderatortoolkit-reviewchanges-personalize';

/**
 * @return {boolean} Whether the feed follows the interests of the user
 */
function isPersonalized() {
	const value = mw.user.options.get( PERSONALIZE_OPTION_NAME, false );
	return value === true || value === 1 || value === '1';
}

/**
 * Save the setting for the user, then use it on this page.
 *
 * @param {boolean} value
 * @return {Promise<void>} Rejects when the setting does not save
 */
async function savePersonalized( value ) {
	const optionValue = value ? '1' : '0';
	await new mw.Api().saveOption( PERSONALIZE_OPTION_NAME, optionValue );
	mw.user.options.set( PERSONALIZE_OPTION_NAME, optionValue );
}

module.exports = { isPersonalized, savePersonalized };
