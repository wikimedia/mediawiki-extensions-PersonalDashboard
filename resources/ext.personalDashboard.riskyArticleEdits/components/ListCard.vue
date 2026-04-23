<template>
	<cdx-card
		class="personal-dashboard-review-changes__card"
		:url="diffUrl"
		target="_blank"
		role="button"
		:data-feedorigin="feedorigin">
		<template #description>
			<div class="personal-dashboard-review-changes__card__container">
				<div class="personal-dashboard-review-changes__card__header">
					<div class="personal-dashboard-review-changes__card__title">
						{{ title }}
					</div>

					<div class="personal-dashboard-review-changes__card__description">
						{{ description }}
					</div>
				</div>

				<div class="personal-dashboard-review-changes__card__subheader">
					<div class="personal-dashboard-review-changes__card__icon">
						<cdx-icon
							v-if="isMobile || !showUserInfoCard"
							:icon="userIcon"
							size="small">
						</cdx-icon>

						<user-info-button v-else :username="user"></user-info-button>
					</div>

					<div class="personal-dashboard-review-changes__card__username">
						{{ user }}
					</div>

					<div class="personal-dashboard-review-changes__card__separator">
						⋅
					</div>

					<div class="personal-dashboard-review-changes__card__timestamp">
						{{ timestampFormatted }}
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
	</cdx-card>
</template>

<script>
const { defineComponent, defineAsyncComponent, toRaw } = require( 'vue' );
const { CdxCard, CdxIcon } = require( '../codex.js' );
const { cdxIconUserAvatar, cdxIconUserTemporary } = require( '../icons.json' );
const { formatRelativeTimeOrDate } = require( 'mediawiki.DateFormatter' );

module.exports = defineComponent( {
	name: 'ListCard',
	components: {
		CdxCard,
		CdxIcon,
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
		pages: { type: Object, required: true },
		feedorigin: { type: String, required: true },
		isMobile: { type: Boolean, default: false }
	},
	setup() {
		return {
			showUserInfoCard: mw.user.options.get( 'checkuser-userinfocard-enable' ),
			missingCommentMessage: mw.msg( 'personal-dashboard-risky-article-edits-list-card-no-comment-message' )
		};
	},
	computed: {
		diffUrl() {
			return mw.util.getUrl( this.title, {
				curid: this.pageid,
				diff: this.revid,
				oldid: this.old_revid
			} );
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
	&.cdx-card {
		padding: @spacing-100;
		border-color: transparent;

		&:hover {
			border-color: @border-color-subtle;
		}

		&:visited {
			background-color: @background-color-neutral-subtle;
		}
	}

	.cdx-card__text {
		width: 100%;
	}

	.cdx-card__text__description {
		margin-top: 0;
	}

	&__container {
		display: flex;
		flex-direction: column;
		gap: @spacing-25;
		color: @color-emphasized;
		line-height: @line-height-x-small;
	}

	&__header {
		display: flex;
		align-items: center;
		gap: @spacing-35;
	}

	&__title {
		font-weight: @font-weight-bold;
	}

	&__title,
	&__description,
	&__username,
	&__timestamp,
	&__summary {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__subheader {
		display: flex;
		align-items: center;
		gap: @spacing-25;
	}

	&__icon .cdx-icon {
		color: @color-emphasized;
	}

	&__username a {
		font-weight: @font-weight-bold;

		&:hover,
		&:focus {
			text-decoration: none;
		}

		&:visited {
			color: @color-emphasized;
		}
	}

	&__summary--missing {
		font-style: italic;
	}

	&:visited &__container,
	&:visited &__icon .cdx-icon {
		color: @color-subtle;
	}
}
</style>
