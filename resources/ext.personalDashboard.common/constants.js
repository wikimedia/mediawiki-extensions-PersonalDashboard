// One page of a feed, and what the load-more control adds to the full list.
// FeedPanel slices this page down for the compact summary, so moving between
// the summary and the full list costs no re-fetch.
const FULL_LIMIT = 10;

module.exports = { FULL_LIMIT };
