<template>
	<cdx-card
		class="ext-personal-dashboard-active-discussion-card"
		:url="discussionUrl"
		target="_blank">
		<template #description>
			<div class="ext-personal-dashboard-active-discussion-card-info">
				<div class="ext-personal-dashboard-active-discussion-card-info-title-row">
					<div class="ext-personal-dashboard-active-discussion-card-info-title">
						{{ discussionTitle }}
					</div>
					<div class="ext-personal-dashboard-active-discussions-icon-row">
						<div class="ext-personal-dashboard-active-discussion-card-info-details">
							<cdx-icon :icon="cdxIconUserActive"></cdx-icon> {{ authorCount }}
							<cdx-icon :icon="cdxIconSpeechBubble"></cdx-icon> {{ commentCount }}
						</div>
					</div>
				</div>
				<div class="ext-personal-dashboard-active-discussion-card-info-title-row">
					{{ discussionPage }}
				</div>
				<div class="ext-personal-dashboard-active-discussion-card-info-title-row">
					<span class="ext-personal-dashboard-active-discussion-card-info-title-row-additional">
						{{ latestComment }} <a @click="navigateToComment">{{ timestampFormatted }}</a>
					</span>
				</div>
			</div>
		</template>
	</cdx-card>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxCard, CdxIcon } = require( '../codex.js' );
const {
	cdxIconUserActive,
	cdxIconSpeechBubble
} = require( '../icons.json' );
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
		latestReplyId: { type: String, required: true }
	},
	setup() {
		return {
			latestComment: mw.msg( 'personal-dashboard-active-discussions-latest-comment' ),
			cdxIconUserActive,
			cdxIconSpeechBubble
		};
	},
	computed: {
		discussionUrl() {
			return mw.util.getUrl( this.discussionPage + '#' + this.discussionTitle );
		},
		commentUrl() {
			return mw.util.getUrl( this.discussionPage + '#' + this.latestReplyId );
		},
		timestampFormatted() {
			const latestReplyTimestamp = new Date( Date.parse( this.latestReply ) );
			return `${ formatRelativeTimeOrDate( latestReplyTimestamp ) }`;
		}
	},
	methods: {
		navigateToComment() {
			event.preventDefault();
			event.stopPropagation();
			window.open( this.commentUrl, '_blank', 'noopener noreferrer' );
		}
	},
	mounted() {
		mw.hook( 'personaldashboard.activediscussions.listcard.loaded' ).fire();
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.cdx-card.ext-personal-dashboard-active-discussion-card {
	border-left: 0;
	border-right: 0;
	border-radius: 0;
	border-bottom: 0;
	border-top: 1px solid @border-color-subtle;
	padding: 0.5rem;
	width: 100%;

	&:hover {
		background-color: @background-color-interactive;
	}

	.cdx-card__text {
		width: 100%;
	}

	&:last-child {
		border-bottom: 1px solid @border-color-subtle;
	}

	.ext-personal-dashboard-active-discussion-card-icon {
		color: @color-subtle;
	}

	.ext-personal-dashboard-active-discussion-card-info {
		display: flex;
		flex-direction: column;
		gap: 0.44rem;
	}

	.ext-personal-dashboard-active-discussion-card-info-title-row {
		display: flex;
		justify-content: space-between;
		flex-wrap: wrap;

		.ext-personal-dashboard-active-discussion-card-info-title {
			font-weight: @font-weight-bold;
			line-height: @line-height-medium;
			color: @color-base;
			width: 80%;
			overflow: hidden;
			text-overflow: ellipsis;
			text-wrap: nowrap;
		}

		.ext-personal-dashboard-active-discussion-card-info-details {
			line-height: @line-height-medium;
			padding-left: 0.75rem;
		}

		.ext-personal-dashboard-active-discussion-card-comment {
			overflow: hidden;
			text-overflow: ellipsis;
			text-wrap: nowrap;
		}
	}

	.ext-personal-dashboard-active-discussion-card-info-title-row-additional {
		a {
			.cdx-mixin-link-base();
			text-decoration: underline;
		}
		margin-right: auto;
	}

	.ext-personal-dashboard-active-discussion-card-missing-comment-message {
		font-style: italic;
	}

	.ext-personal-dashboard-active-discussion-card-separator {
		padding: 0 0.25rem;
		font-weight: @font-weight-normal;
	}
}

.skin-minerva {
	.cdx-card.ext-personal-dashboard-active-discussion-card {
		.ext-personal-dashboard-active-discussion-card-info {
			.ext-personal-dashboard-active-discussion-card-info-title-row {
				&:first-child {
					display: flex;
					flex-direction: column;
				}

				.ext-personal-dashboard-active-discussion-card-info-title {
					width: 100%;
				}
			}
		}
	}
}

</style>
