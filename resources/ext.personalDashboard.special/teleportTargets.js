/**
 * @file teleportTargets.js
 *
 * The DOM ids an island can teleport into when it stands in as the whole
 * focused view rather than a card: the narrow-viewport dialog, or the
 * wide-viewport in-page frame. Shared so each id lives in one place instead
 * of a literal string repeated in every component that mints or targets it.
 *
 * Deliberately two distinct ids, not one shared between the dialog and the
 * frame: crossing the viewport breakpoint while a module is open swaps which
 * of the two renders, and Vue's <teleport> only re-resolves its target when
 * the `to` value itself changes between renders. A shared id left that swap
 * invisible to <teleport>, so the island's DOM stayed parented to whichever
 * component had just torn down instead of moving to the one that replaced it.
 */

const DIALOG_TARGET_ID = 'personal-dashboard-teleport';
const FRAME_TARGET_ID = 'personal-dashboard-focused-frame-teleport';

module.exports = { DIALOG_TARGET_ID, FRAME_TARGET_ID };
