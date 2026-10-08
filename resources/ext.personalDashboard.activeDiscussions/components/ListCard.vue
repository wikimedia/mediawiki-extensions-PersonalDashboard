<template>
	<feed-card
		class="personal-dashboard-active-discussions__card"
		:url="discussionUrl"
		:aria-label="ariaLabel"
	>
		<template #header>
			<span class="personal-dashboard-active-discussions__card__title">
				{{ section }}
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
				{{ metaText }}
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
const { formatTimestamp } = utils;
const { cdxIconUserAvatar, cdxIconSpeechBubble } = require( '../icons.json' );

module.exports = defineComponent( {
	name: 'ListCard',
	components: { CdxIcon, FeedCard },
	// See the Review Changes card: the feed item carries fields this card does
	// not declare, and they would otherwise land in the DOM.
	inheritAttrs: false,
	props: {
		title: { type: String, required: true },
		section: { type: String, required: true },
		// Empty where the heading has no id to link to.
		anchor: { type: String, default: '' },
		// The top-level discussion a sub-thread sits under; null on one that
		// already is.
		topic: { type: String, default: null },
		commentCount: { type: Number, required: true },
		authorCount: { type: Number, required: true },
		timestamp: { type: String, required: true },
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
		metaText() {
			return this.topic ?
				mw.msg( 'personal-dashboard-active-discussions-in-topic', this.title, this.topic ) :
				this.title;
		},
		ariaLabel() {
			return this.section + mw.msg( 'comma-separator' ) + this.metaText;
		},
		discussionUrl() {
			return mw.util.getUrl( this.title ) + ( this.anchor ? '#' + this.anchor : '' );
		},
		commentUrl() {
			return mw.util.getUrl( this.title ) + '#' + this.latestReplyId;
		},
		timestampFormatted() {
			return formatTimestamp( this.timestamp );
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

	// The page and topic keep to one line; a long one ends in an ellipsis.
	// `min-width: 0` lets the flex item shrink below its text.
	&__subheader {
		max-width: 100%;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
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
