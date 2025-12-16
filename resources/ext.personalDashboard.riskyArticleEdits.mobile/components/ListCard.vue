<template>
	<cdx-card class="ext-personal-dashboard-moderation-card-mobile-summary">
		<template #description>
			<div class="ext-personal-dashboard-moderation-card-info-title-row">
				<span class="ext-personal-dashboard-moderation-card-info-title">
					<cdx-icon :icon="cdxIconEdit"></cdx-icon>
					{{ userMessage }}
					<change-number :oldlen :newlen></change-number>
					{{ articleMessage }}
				</span>
			</div>
		</template>
	</cdx-card>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxCard, CdxIcon } = require( '../codex.js' );
const { cdxIconEdit } = require( '../icons.json' );
const ChangeNumber = require( './ChangeNumber.vue' );

module.exports = defineComponent( {
	name: 'ListCard',
	components: { CdxCard, CdxIcon, ChangeNumber },
	props: {
		title: { type: String, required: true },
		// eslint-disable-next-line vue/no-unused-properties
		type: { type: String, required: true },
		// eslint-disable-next-line vue/no-unused-properties
		ns: { type: Number, required: true },
		newlen: { type: Number, required: true },
		// eslint-disable-next-line camelcase, vue/prop-name-casing,vue/no-unused-properties
		old_revid: { type: Number, required: true },
		oldlen: { type: Number, required: true },
		// eslint-disable-next-line vue/no-unused-properties
		pageid: { type: Number, required: true },
		// eslint-disable-next-line vue/no-unused-properties
		rcid: { type: Number, required: true },
		// eslint-disable-next-line vue/no-unused-properties
		revid: { type: Number, required: true },
		// eslint-disable-next-line vue/no-unused-properties
		temp: { type: String, default: '' },
		user: { type: String, required: true },
		// eslint-disable-next-line vue/no-unused-properties
		comment: { type: String, required: true },
		// eslint-disable-next-line vue/no-unused-properties
		tags: { type: Array, required: true },
		// eslint-disable-next-line vue/no-unused-properties
		timestamp: { type: String, default: '' },
		// eslint-disable-next-line vue/no-unused-properties
		pages: { type: Object, required: true }
	},
	setup() {
		return { cdxIconEdit };
	},
	computed: {
		userMessage() {
			return mw.message( 'personal-dashboard-risky-article-edits-mobile-user-text', [ this.user ] );
		},
		articleMessage() {
			return mw.message( 'personal-dashboard-risky-article-edits-mobile-byte-text', [ this.title ] );
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
