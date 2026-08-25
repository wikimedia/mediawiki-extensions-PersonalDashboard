<template>
	<cdx-card
		class="personal-dashboard-review-changes__card"
		:class="{ 'personal-dashboard-review-changes__card--visited': hasVisited }"
		:data-feedorigin="feedorigin">
		<template #description>
			<div class="personal-dashboard-review-changes__card__container">
				<a
					class="personal-dashboard-review-changes__card__link"
					:href="diffUrl"
					target="_blank"
					:aria-label="ariaLabel"
					@click="onClick">
				</a>

				<div class="personal-dashboard-review-changes__card__header">
					<div
						v-if="isNarrow"
						class="personal-dashboard-review-changes__card__title">
						{{ title }}
					</div>

					<a
						v-else
						:href="titleUrl"
						target="_blank"
						class="personal-dashboard-review-changes__card__title">
						{{ title }}
					</a>

					<div
						v-if="description"
						class="personal-dashboard-review-changes__card__description">
						{{ description }}
					</div>
				</div>

				<div class="personal-dashboard-review-changes__card__subheader">
					<div class="personal-dashboard-review-changes__card__user">
						<div class="personal-dashboard-review-changes__card__icon">
							<cdx-icon
								v-if="isNarrow || !showUserInfoCard"
								:icon="userIcon"
								size="small">
							</cdx-icon>

							<user-info-button v-else :username="user"></user-info-button>
						</div>

						<div
							v-if="isNarrow"
							class="personal-dashboard-review-changes__card__username">
							{{ user }}
						</div>

						<a
							v-else
							:href="userUrl"
							target="_blank"
							class="personal-dashboard-review-changes__card__username">
							{{ user }}
						</a>
					</div>

					<div class="personal-dashboard-review-changes__card__meta">
						<div class="personal-dashboard-review-changes__card__separator">
							⋅
						</div>

						<div class="personal-dashboard-review-changes__card__timestamp">
							{{ timestampFormatted }}
						</div>
					</div>
				</div>

				<div v-if="comment" class="personal-dashboard-review-changes__card__summary">
					{{ comment }}
				</div>

				<div v-else class="personal-dashboard-review-changes__card__summary--missing">
					{{ missingCommentMessage }}
				</div>
			</div>
		</template>
		<template v-if="isMajorChange" #supporting-text>
			<cdx-info-chip :icon="noticeIcon">
				{{ $i18n( 'personal-dashboard-review-changes-major-changes-label' ) }}
			</cdx-info-chip>
		</template>
	</cdx-card>
</template>

<script>
const { defineComponent, defineAsyncComponent, ref, toRaw } = require( 'vue' );
const { CdxCard, CdxIcon, CdxInfoChip } = require( '../codex.js' );
const { cdxIconNotice, cdxIconUserAvatar, cdxIconUserTemporary } = require( '../icons.json' );
const { formatRelativeTimeOrDate } = require( 'mediawiki.DateFormatter' );
const MAJOR_CHANGE_DELTA = 1000;

module.exports = defineComponent( {
	name: 'ListCard',
	components: {
		CdxCard,
		CdxIcon,
		CdxInfoChip,
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
			hasVisited: ref( false ),
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
	methods: {
		onClick() {
			this.hasVisited = true;
		}
	},
	mounted() {
		mw.hook( 'personaldashboard.recentactivity.listcard.loaded' ).fire();
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-review-changes__card {
	.cdx-card& {
		position: relative;
		color: @color-emphasized;
		padding: @spacing-100;
		border-color: @border-color-transparent;

		a {
			color: inherit;
		}

		&:hover {
			border-color: @border-color-subtle;
		}

		&--visited {
			color: @color-subtle;
			background-color: @background-color-neutral-subtle;
		}
	}

	.cdx-card__text {
		width: @size-full;

		&__title,
		&__description {
			color: inherit;
		}

		&__description {
			margin-top: @spacing-0;
		}
	}

	&__container {
		display: flex;
		flex-direction: column;
		gap: @spacing-25;
		line-height: @line-height-x-small;
	}

	&__link {
		position: absolute;
		inset: 0;
		z-index: 0;
	}

	a&__title,
	a&__username,
	&__icon .cdx-button {
		position: relative;
		z-index: 1;
	}

	&__container &__icon .cdx-icon,
	.cdx-button:enabled {
		color: inherit;
	}

	&__header {
		display: flex;
		// The description stays beside the title while both fit. If they do not
		// fit, the description moves onto its own line and is cut short there.
		flex-wrap: wrap;
		align-items: baseline;
		// `row-gap column-gap`: the row gap only applies once the description has
		// moved down, so it never indents the description beside the title.
		gap: @spacing-25 @spacing-35;
	}

	&__title,
	&__username {
		font-weight: @font-weight-bold;
	}

	// The title is never cut short. A long title flows onto the next line, and a
	// word that is longer than the line breaks instead of overflowing the card.
	&__title {
		overflow-wrap: anywhere;
	}

	&__description,
	&__username,
	&__timestamp {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	// The summary keeps at most two lines, then ends with an ellipsis.
	&__summary {
		// Contrary to its name, the non-standard `-webkit-box` value works in all
		// major browsers. `-webkit-box-orient` is necessary to make the line clamp
		// below work.
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
		overflow: hidden;
		text-overflow: ellipsis;
		overflow-wrap: break-word;
	}

	&__subheader {
		display: flex;
		// A long username pushes the timestamp onto its own line before the
		// username itself is cut short.
		flex-wrap: wrap;
		align-items: center;
		gap: @spacing-25;
		min-height: 20px;
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

	&__summary--missing {
		font-style: italic;
	}

	&--visited &__container &__username {
		font-weight: @font-weight-normal;
	}
}
</style>
