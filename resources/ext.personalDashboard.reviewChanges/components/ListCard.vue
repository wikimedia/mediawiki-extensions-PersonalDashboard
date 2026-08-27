<template>
	<feed-card
		class="personal-dashboard-review-changes__card"
		:url="diffUrl"
		:aria-label="ariaLabel"
		:data-feedorigin="feedorigin"
	>
		<template #header>
			<span
				v-if="isNarrow"
				class="personal-dashboard-review-changes__card__title">
				{{ title }}
			</span>
			<a
				v-else
				:href="titleUrl"
				target="_blank"
				class="personal-dashboard-review-changes__card__title">
				{{ title }}
			</a>

			<span
				v-if="description"
				class="personal-dashboard-review-changes__card__description">
				{{ description }}
			</span>
		</template>

		<template #meta>
			<span class="personal-dashboard-review-changes__card__user">
				<span class="personal-dashboard-review-changes__card__icon">
					<cdx-icon
						v-if="isNarrow || !showUserInfoCard"
						:icon="userIcon"
						size="small"
					></cdx-icon>

					<user-info-button v-else :username="user"></user-info-button>
				</span>

				<span
					v-if="isNarrow"
					class="personal-dashboard-review-changes__card__username">
					{{ user }}
				</span>

				<a
					v-else
					:href="userUrl"
					target="_blank"
					class="personal-dashboard-review-changes__card__username">
					{{ user }}
				</a>
			</span>

			<span class="personal-dashboard-review-changes__card__meta">
				<span class="personal-dashboard-review-changes__card__separator">
					&middot;
				</span>

				<span class="personal-dashboard-review-changes__card__timestamp">
					{{ timestampFormatted }}
				</span>
			</span>
		</template>

		<template #description>
			<span v-if="comment" class="personal-dashboard-review-changes__card__summary">
				{{ comment }}
			</span>
			<span v-else class="personal-dashboard-review-changes__card__summary--missing">
				{{ missingCommentMessage }}
			</span>
		</template>

		<template v-if="isMajorChange" #supporting-text>
			<cdx-info-chip :icon="noticeIcon">
				{{ $i18n( 'personal-dashboard-review-changes-major-changes-label' ) }}
			</cdx-info-chip>
		</template>
	</feed-card>
</template>

<script>
const { defineComponent, defineAsyncComponent, toRaw } = require( 'vue' );
const { CdxIcon, CdxInfoChip } = require( '../codex.js' );
const { FeedCard } = require( 'ext.personalDashboard.common' );
const { cdxIconNotice, cdxIconUserAvatar, cdxIconUserTemporary } = require( '../icons.json' );
const { formatRelativeTimeOrDate } = require( 'mediawiki.DateFormatter' );
const MAJOR_CHANGE_DELTA = 1000;

module.exports = defineComponent( {
	name: 'ListCard',
	components: {
		CdxIcon,
		CdxInfoChip,
		FeedCard,
		UserInfoButton: defineAsyncComponent( {
			loader: () => new Promise( ( resolve ) => {
				mw.loader.using( 'ext.checkUser.userInfoCard', ( require ) => {
					resolve( require( 'ext.checkUser.userInfoCard' ).UserCardButton );
				} );
			} ),
			onError() {}
		} )
	},
	props: {
		title: { type: String, required: true },
		// eslint-disable-next-line camelcase, vue/prop-name-casing
		old_revid: { type: Number, required: true },
		pageid: { type: Number, required: true },
		revid: { type: Number, required: true },
		user: { type: String, required: true },
		parsedcomment: { type: String, required: true },
		timestamp: { type: String, default: '' },
		newlen: { type: Number, required: true },
		oldlen: { type: Number, required: true },
		pages: { type: Object, required: true },
		feedorigin: { type: String, required: true },
		isNarrow: { type: Boolean, default: false }
	},
	setup() {
		return {
			showUserInfoCard: mw.user.options.get( 'checkuser-userinfocard-enable' ),
			missingCommentMessage: mw.msg( 'personal-dashboard-risky-article-edits-list-card-no-comment-message' ),
			noticeIcon: cdxIconNotice
		};
	},
	computed: {
		diffUrl() {
			return new mw.Title( this.title ).getUrl( {
				curid: this.pageid,
				diff: this.revid,
				oldid: this.old_revid
			} );
		},
		ariaLabel() {
			return mw.msg( 'personal-dashboard-risky-article-edits-list-card-aria-label', this.title );
		},
		titleUrl() {
			return new mw.Title( this.title ).getUrl();
		},
		userUrl() {
			return new mw.Title( this.user, 2 ).getUrl();
		},
		comment() {
			if ( !this.parsedcomment ) {
				return null;
			}

			const temp = document.createElement( 'div' );
			temp.innerHTML = this.parsedcomment;

			return temp.innerText;
		},
		timestampFormatted() {
			const changeDateTimestamp = new Date( Date.parse( this.timestamp ) );
			return `${ formatRelativeTimeOrDate( changeDateTimestamp ) }`;
		},
		description() {
			const pages = toRaw( this.pages );

			const page = ( pages && pages[ 0 ] ) ?
				pages.find( ( obj ) => obj.pageid === this.pageid && obj.description ) :
				undefined;

			return ( page && page.description ) ? page.description : '';
		},
		userIcon() {
			return mw.util.isTemporaryUser( this.user ) ?
				cdxIconUserTemporary :
				cdxIconUserAvatar;
		},
		isMajorChange() {
			return Math.abs( this.newlen - this.oldlen ) > MAJOR_CHANGE_DELTA;
		}
	},
	mounted() {
		mw.hook( 'personaldashboard.recentactivity.listcard.loaded' ).fire();
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

// The card chrome (the whole-card link, the visited state and the row layout)
// lives in FeedCard; only what is specific to a Review Changes item is here.
.personal-dashboard-review-changes__card {
	.personal-dashboard-feed__card__container &__icon .cdx-icon,
	.cdx-button:enabled {
		color: inherit;
	}

	&__username {
		font-weight: @font-weight-bold;
	}

	// The title is never cut short. A long title flows onto the next line, and a
	// word that is longer than the line breaks instead of overflowing the card.
	&__title {
		overflow-wrap: anywhere;
	}

	&__username,
	&__timestamp {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	// `min-width: 0` lets the group shrink below the width of the username, so
	// the username can show an ellipsis once the timestamp has moved down.
	&__user {
		display: flex;
		align-items: center;
		gap: @spacing-25;
		min-width: 0;
	}

	&__meta {
		display: flex;
		flex-shrink: 0;
		align-items: center;
		gap: @spacing-25;
	}

	&__icon {
		flex-shrink: 0;
	}

	&__icon .cdx-button:enabled {
		margin-left: -6px;
	}

	// FeedCard lays the header out as a centred row. The description stays
	// beside the title while both fit, and moves onto its own line when they do
	// not, so this card aligns them on the baseline and lets the row wrap.
	.personal-dashboard-feed__card__header {
		flex-wrap: wrap;
		align-items: baseline;
		// `row-gap column-gap`: the row gap only applies once the description has
		// moved down, so it never indents the description beside the title.
		gap: @spacing-25 @spacing-35;
	}

	// The page description keeps to a single line; a long one is cut short. The
	// weight is reset because FeedCard emboldens the header for the title.
	&__description {
		font-weight: @font-weight-normal;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	// The edit summary keeps at most two lines, then ends with an ellipsis.
	// -webkit-box is block-level, so this also puts the summary on its own line
	// below the description.
	&__summary,
	&__summary--missing {
		display: -webkit-box;
		line-clamp: 2;
		overflow: hidden;
		-webkit-line-clamp: 2;
		// Non-standard but still implemented by browsers.
		// Without this, line-clamp does not work.
		-webkit-box-orient: vertical;
	}

	&__summary--missing {
		font-style: italic;
	}

	// The visited modifier is FeedCard's, and sits on this same element.
	&.personal-dashboard-feed__card--visited &__username {
		font-weight: @font-weight-normal;
	}
}
</style>
