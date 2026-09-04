// The dialog and the card share one component instance (teleported, not
// remounted), so a fixed fetch covers both: a module fetches this many items
// once, FeedPanel slices it down to the compact summary, with no re-fetch on
// the summary/full transition.
const FULL_LIMIT = 10;

module.exports = { FULL_LIMIT };
