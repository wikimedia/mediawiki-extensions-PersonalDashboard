<template>
	<feed-card
		class="personal-dashboard-active-discussions__card"
		:url="discussionUrl"
		:aria-label="discussionTitleFormatted"
	>
		<template #header>
			<span class="personal-dashboard-active-discussions__card__title">
				{{ discussionTitleFormatted }}
			</span>

			<span class="personal-dashboard-active-discussions__card__icons">
				<span class="personal-dashboard-active-discussions__card__comments">
					<cdx-icon :icon="cdxIconSpeechBubble" size="small"></cdx-icon>
					{{ commentCount }}
				</span>

				<span class="personal-dashboard-active-discussions__card__authors">
					<cdx-icon :icon="cdxIconUserAvatar" size="small"></cdx-icon>
					{{ authorCount }}
				</span>
			</span>
		</template>

		<template #meta>
			<span class="personal-dashboard-active-discussions__card__subheader">
				{{ discussionPageFormatted }}
			</span>
		</template>

		<template #description>
			{{ latestComment }}

			<span v-if="isNarrow">
				{{ timestampFormatted }}
			</span>
			<a
				v-else
				:href="commentUrl"
				target="_blank"
			>
				{{ timestampFormatted }}
			</a>
		</template>
	</feed-card>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxIcon } = require( '../codex.js' );
const { FeedCard, utils } = require( 'ext.personalDashboard.common' );
const { formatTimestamp, stripMarkup } = utils;
const { cdxIconUserAvatar, cdxIconSpeechBubble } = require( '../icons.json' );

module.exports = defineComponent( {
	name: 'ListCard',
	components: { CdxIcon, FeedCard },
	props: {
		discussionTitle: { type: String, required: true },
		discussionPage: { type: String, required: true },
		commentCount: { type: Number, required: true },
		authorCount: { type: Number, required: true },
		latestReply: { type: String, required: true },
		latestReplyId: { type: String, required: true },
		isNarrow: { type: Boolean, default: false }
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
			return stripMarkup( this.discussionTitle );
		},
		discussionPageFormatted() {
			return stripMarkup( this.discussionPage );
		},
		discussionUrl() {
			return mw.util.getUrl( this.discussionPageFormatted + '#' + this.discussionTitleFormatted );
		},
		commentUrl() {
			return mw.util.getUrl( this.discussionPageFormatted + '#' + this.latestReplyId );
		},
		timestampFormatted() {
			return formatTimestamp( this.latestReply );
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

// The card chrome (the whole-card link, the visited state and the row layout)
// lives in FeedCard; only what is specific to an Active Discussions item is here.
.personal-dashboard-active-discussions__card {
	// FeedCard lays the header out as a row; this card puts its counts at the
	// far end of that row, and stacks them under the title on Minerva.
	.personal-dashboard-feed__card__header {
		justify-content: space-between;

		.skin-minerva & {
			flex-direction: column;
			align-items: start;
		}
	}

	&__icons {
		align-items: center;
		display: flex;
		font-weight: @font-weight-normal;
		gap: @spacing-50;
		white-space: nowrap;
	}
}
</style>
