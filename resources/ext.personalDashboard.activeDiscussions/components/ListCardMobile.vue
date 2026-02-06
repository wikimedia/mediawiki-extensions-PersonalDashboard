<template>
	<cdx-card
		class="ext-personal-dashboard-active-discussion-mobile-summary">
		<template #description>
			<div class="ext-personal-dashboard-active-discussions-card-info-title-row">
				<div class="ext-personal-dashboard-active-discussions-page">
					<cdx-icon :icon="cdxIconSpeechBubbles"></cdx-icon> {{ discussionTitle }}
				</div>
				<div class="ext-personal-dashboard-active-discussions-latest-comment">
					{{ latestComment }}
					<a @click="navigateToComment">{{ timestampFormatted }}</a>
				</div>
			</div>
		</template>
	</cdx-card>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxCard, CdxIcon } = require( '../codex.js' );
const { cdxIconSpeechBubbles } = require( '../icons.json' );
const { formatRelativeTimeOrDate } = require( 'mediawiki.DateFormatter' );

module.exports = defineComponent( {
	name: 'ListCard',
	components: { CdxCard, CdxIcon },
	props: {
		discussionTitle: { type: String, required: true },
		discussionPage: { type: String, required: true },
		latestReply: { type: String, required: true },
		latestReplyId: { type: String, required: true }
	},
	setup() {
		return {
			latestComment: mw.msg( 'personal-dashboard-active-discussions-mobile-latest-comment' ),
			cdxIconSpeechBubbles
		};
	},
	computed: {
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

.cdx-card.ext-personal-dashboard-active-discussion-mobile-summary {
	border: unset;
	border-bottom: unset;

	&.cdx-card {
		padding: 0 0 1rem 0;
	}

	&:hover {
		background-color: unset;
	}

	&.cdx-button {
		font-size: @font-size-medium;
	}
}

.ext-personal-dashboard-active-discussions-card-info-title-row {
	display: grid;

	.ext-personal-dashboard-moderation-card-info-title {
		font-weight: @font-weight-normal;
		line-height: @line-height-medium;
		color: @color-base;
		overflow: hidden;
		text-overflow: ellipsis;
		text-wrap: nowrap;
	}

	.ext-personal-dashboard-active-discussions-page {
		font-style: italic;
		overflow: hidden;
		text-overflow: ellipsis;
		text-wrap: nowrap;
	}

	.ext-personal-dashboard-active-discussions-latest-comment {
		a {
			.cdx-mixin-link-base();
			text-decoration: underline;
		}
	}
}

.open-true {
	.personal-dashboard-module-route-activeDiscussions {
		.ext-personal-dashboard-active-discussions-footer {
			display: none;
		}
	}
}
</style>
