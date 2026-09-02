/**
 * @file teleportTargets.js
 *
 * The DOM ids an island can teleport into when it stands in as the whole
 * focused view rather than a card: the narrow-viewport dialog, or the
 * wide-viewport in-page frame. Each has two, one for the body and one for the
 * header menu, since a stand-in replaces both halves of the card. Shared so
 * each id lives in one place instead of a literal string repeated in every
 * component that mints or targets it.
 *
 * Deliberately a distinct id per slot per stand-in, never one shared between
 * the dialog and the frame: crossing the viewport breakpoint while a module is open swaps which
 * of the two renders, and Vue's <teleport> only re-resolves its target when
 * the `to` value itself changes between renders. A shared id left that swap
 * invisible to <teleport>, so the island's DOM stayed parented to whichever
 * component had just torn down instead of moving to the one that replaced it.
 */

const DIALOG_TARGET_ID = 'personal-dashboard-teleport';
const FRAME_TARGET_ID = 'personal-dashboard-focused-frame-teleport';
const DIALOG_HEADER_TARGET_ID = 'personal-dashboard-header-teleport';
const FRAME_HEADER_TARGET_ID = 'personal-dashboard-focused-frame-header-teleport';

module.exports = {
	DIALOG_TARGET_ID,
	FRAME_TARGET_ID,
	DIALOG_HEADER_TARGET_ID,
	FRAME_HEADER_TARGET_ID
};
