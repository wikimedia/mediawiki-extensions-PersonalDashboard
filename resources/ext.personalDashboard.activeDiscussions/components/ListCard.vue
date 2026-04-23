<template>
	<cdx-card
		class="personal-dashboard-active-discussions__card"
		:url="discussionUrl"
		target="_blank"
		role="button">
		<template #description>
			<div class="personal-dashboard-active-discussions__card__container">
				<div class="personal-dashboard-active-discussions__card__header">
					<div class="personal-dashboard-active-discussions__card__title">
						{{ discussionTitleFormatted }}
					</div>

					<div class="personal-dashboard-active-discussions__card__icons">
						<div class="personal-dashboard-active-discussions__card__comments">
							<cdx-icon :icon="cdxIconSpeechBubble" size="small"></cdx-icon>
							{{ commentCount }}
						</div>

						<div class="personal-dashboard-active-discussions__card__authors">
							<cdx-icon :icon="cdxIconUserAvatar" size="small"></cdx-icon>
							{{ authorCount }}
						</div>
					</div>
				</div>

				<div class="personal-dashboard-active-discussions__card__subheader">
					{{ discussionPageFormatted }}
				</div>

				<div class="personal-dashboard-active-discussions__card__latest">
					{{ latestComment }}
					<span v-if="isMobile">{{ timestampFormatted }}</span>
					<a
						v-else
						:href="commentUrl"
						target="_blank">
						{{ timestampFormatted }}
					</a>
				</div>
			</div>
		</template>
	</cdx-card>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxCard, CdxIcon } = require( '../codex.js' );
const { cdxIconUserAvatar, cdxIconSpeechBubble } = require( '../icons.json' );
const { formatRelativeTimeOrDate } = require( 'mediawiki.DateFormatter' );

module.exports = defineComponent( {
	name: 'ListCard',
	components: { CdxCard, CdxIcon },
	props: {
		discussionTitle: { type: String, required: true },
		discussionPage: { type: String, required: true },
		commentCount: { type: Number, required: true },
		authorCount: { type: Number, required: true },
		latestReply: { type: String, required: true },
		latestReplyId: { type: String, required: true },
		isMobile: { type: Boolean, default: false }
	},
	setup() {
		return {
			latestComment: mw.msg( 'personal-dashboard-active-discussions-latest-comment' ),
			cdxIconUserAvatar,
			cdxIconSpeechBubble
		};
	},
	computed: {
		discussionTitleFormatted() {
			if ( !this.discussionTitle ) {
				return '';
			}

			const temp = document.createElement( 'div' );
			temp.innerHTML = this.discussionTitle;

			return temp.innerText;
		},
		discussionPageFormatted() {
			if ( !this.discussionPage ) {
				return '';
			}

			const temp = document.createElement( 'div' );
			temp.innerHTML = this.discussionPage;

			return temp.innerText;
		},
		discussionUrl() {
			return mw.util.getUrl( this.discussionPageFormatted + '#' + this.discussionTitleFormatted );
		},
		commentUrl() {
			return mw.util.getUrl( this.discussionPageFormatted + '#' + this.latestReplyId );
		},
		timestampFormatted() {
			const latestReplyTimestamp = new Date( Date.parse( this.latestReply ) );
			return `${ formatRelativeTimeOrDate( latestReplyTimestamp ) }`;
		}
	},
	mounted() {
		mw.hook( 'personaldashboard.activediscussions.listcard.loaded' ).fire();
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-active-discussions__card {
	&.cdx-card {
		padding: @spacing-100;
		border-color: transparent;

		&:hover {
			border-color: @border-color-subtle;
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
		color: @color-subtle;
		line-height: @line-height-x-small;
	}

	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: @spacing-35;

		.skin-minerva & {
			flex-direction: column;
			align-items: start;
		}
	}

	&__title {
		color: @color-base;
		font-weight: @font-weight-bold;
	}

	&__subheader {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__icons {
		display: flex;
		align-items: center;
		gap: @spacing-50;
		white-space: nowrap;
	}
}
</style>
