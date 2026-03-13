<template>
	<cdx-card class="ext-personal-dashboard-moderation-card-mobile-summary">
		<template #description>
			<div class="ext-personal-dashboard-moderation-card-info-title-row">
				<span class="ext-personal-dashboard-moderation-card-info-title">
					<cdx-icon :icon="cdxIconEdit"></cdx-icon>
					{{ userMessage }}
				</span>
			</div>
		</template>
	</cdx-card>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxCard, CdxIcon } = require( '../codex.js' );
const { cdxIconEdit } = require( '../icons.json' );

module.exports = defineComponent( {
	name: 'ListCard',
	components: { CdxCard, CdxIcon },
	props: {
		title: { type: String, required: true },
		user: { type: String, required: true }
	},
	setup() {
		return { cdxIconEdit };
	},
	computed: {
		userMessage() {
			return mw.message( 'personal-dashboard-risky-article-edits-mobile-user-text', this.user, this.title );
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-personal-dashboard-moderation-card-mobile-summary {
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

.ext-personal-dashboard-moderation-card-info-title-row {
	display: flex;
	flex-flow: row wrap;

	.ext-personal-dashboard-moderation-card-info-title {
		font-weight: @font-weight-normal;
		line-height: @line-height-medium;
		color: @color-base;
	}
}
</style>
