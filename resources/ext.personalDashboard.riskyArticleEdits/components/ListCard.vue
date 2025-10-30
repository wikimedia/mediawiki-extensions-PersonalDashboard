<template>
	<a
		:href="getUrl( title, { curid: pageid, diff: revid, oldid: old_revid } )"
		target="_blank"
		rel="noopener noreferrer">
		<cdx-card
			class="ext-personal-dashboard-moderation-card"
		>
			<template #description>
				<div class="ext-personal-dashboard-moderation-card-info">
					<div class="ext-personal-dashboard-moderation-card-info-title-row">
						<span>
							<cdx-icon
								class="ext-personal-dashboard-moderation-card-icon"
								:icon="cdxIconArticle"
								size="medium">
							</cdx-icon>
							{{ title }}
						</span>
						<span class="ext-personal-dashboard-moderation-card-info-title-row-additional">
							<change-number :oldlen :newlen></change-number>
						</span>
					</div>
					<div class="ext-personal-dashboard-moderation-card-info-title-row">
						<span>
							<cdx-icon
								class="ext-personal-dashboard-moderation-card-icon"
								:icon="cdxIconUserActive"
								size="medium"
							></cdx-icon>
							{{ user }}</span>
						<span class="ext-personal-dashboard-moderation-card-info-title-row-additional">
							<cdx-info-chip
								v-for="tag in tags"
								:key="tag">{{ getTag( tag ) }}
							</cdx-info-chip>
						</span>
					</div>
					<div class="ext-personal-dashboard-moderation-card-info-title-row">
						<span>
							<cdx-icon
								class="ext-personal-dashboard-moderation-card-icon"
								:icon="cdxIconTextSummary"
								size="medium"></cdx-icon>
							<!-- eslint-disable-next-line vue/no-v-html -->
							<span v-html="parsedcomment"></span></span>
					</div>
				</div>
			</template>
		</cdx-card>
	</a>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxCard, CdxIcon, CdxInfoChip } = require( '@wikimedia/codex' );
const { cdxIconArticle, cdxIconUserActive, cdxIconTextSummary } = require( '../icons.json' );
const ChangeNumber = require( './ChangeNumber.vue' );
module.exports = defineComponent( {
	name: 'ListCard',
	components: {
		CdxCard,
		CdxIcon,
		CdxInfoChip,
		ChangeNumber
	},
	props: {
		title: { type: String, required: true },
		// eslint-disable-next-line vue/no-unused-properties
		type: { type: String, required: true },
		// eslint-disable-next-line vue/no-unused-properties
		ns: { type: Number, required: true },
		newlen: { type: Number, required: true },
		// eslint-disable-next-line camelcase, vue/prop-name-casing
		old_revid: { type: Number, required: true },
		oldlen: { type: Number, required: true },
		pageid: { type: Number, required: true },
		// eslint-disable-next-line vue/no-unused-properties
		rcid: { type: Number, required: true },
		revid: { type: Number, required: true },
		// eslint-disable-next-line vue/no-unused-properties
		temp: { type: String, default: '' },
		user: { type: String, required: true },
		parsedcomment: { type: String, required: true },
		tags: { type: Array, required: true }
	},
	setup() {
		return {
			cdxIconArticle,
			cdxIconUserActive,
			cdxIconTextSummary
		};
	},
	methods: {
		// just a local alias for mw.util function
		getUrl: function ( title, params ) {
			return mw.util.getUrl( title, params );
		},
		// return the tag translation, else the tag string
		getTag: function ( tag ) {
			// eslint-disable-next-line mediawiki/msg-doc
			const msg = mw.message( `tag-${ tag }` );
			return msg.exists() ? msg.text() : tag;
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-personal-dashboard-moderation-card {
	margin-bottom: 1em;
	margin-top: 1em;

	.cdx-card__text {
		width: 100%;
	}
}

.ext-personal-dashboard-moderation-card-icon {
	color: @color-subtle;
}

.ext-personal-dashboard-moderation-card-info {
	display: flex;
	flex-direction: column;
}

.ext-personal-dashboard-moderation-card-info-title-row {
	display: flex;
	flex-direction: row;
}

.ext-personal-dashboard-moderation-card-info-title-row-additional {
	margin-left: auto;
}
</style>
