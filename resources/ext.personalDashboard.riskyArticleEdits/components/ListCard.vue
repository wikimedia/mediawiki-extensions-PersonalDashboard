<template>
	<cdx-card
		class="ext-personal-dashboard-moderation-card"
		:url="diffUrl"
		target="_blank">
		<template #description>
			<div class="ext-personal-dashboard-moderation-card-info">
				<div class="ext-personal-dashboard-moderation-card-info-title-row">
					<span class="ext-personal-dashboard-moderation-card-info-title">
						{{ title }}
					</span>

					<span class="ext-personal-dashboard-moderation-card-info-description">
						{{ description }}
					</span>

					<span class="ext-personal-dashboard-moderation-card-info-title-row-additional">
						{{ timestampFormatted }}
					</span>
				</div>

				<div class="ext-personal-dashboard-moderation-card-info-title-row">
					<change-number :oldlen :newlen></change-number>
					<span class="ext-personal-dashboard-moderation-card-separator">∙</span>
					<span v-if="comment">
						{{ comment }}
					</span>
					<span
						v-else
						class="ext-personal-dashboard-moderation-card-missing-comment-message"
					>{{
						missingCommentMessage
					}}
					</span>
				</div>

				<div class="ext-personal-dashboard-moderation-card-info-title-row">
					<creator-byline
						:creator-name="user"
						:creator-is-temp-account="isTempUser"
					></creator-byline>
				</div>
			</div>
		</template>
	</cdx-card>
</template>

<script>
const { defineComponent } = require( 'vue' );
const { CdxCard } = require( '../codex.js' );
const ChangeNumber = require( './ChangeNumber.vue' );
const CreatorByline = require( './CreatorByline.vue' );
const { formatDate, formatTime } = require( 'mediawiki.DateFormatter' );

module.exports = defineComponent( {
	name: 'ListCard',
	components: { CdxCard, ChangeNumber, CreatorByline },
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
		comment: { type: String, required: true },
		// eslint-disable-next-line vue/no-unused-properties
		tags: { type: Array, required: true },
		timestamp: { type: String, default: '' },
		pages: { type: Object, required: true }
	},
	setup() {
		return {
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
		timestampFormatted() {
			const changeDateTimestamp = new Date( Date.parse( this.timestamp ) );
			const todayDate = new Date();
			todayDate.setHours( 0, 0, 0, 0 );
			if ( changeDateTimestamp >= todayDate ) {
				return mw.message( 'personal-dashboard-risky-article-edits-info-time-text-today',
					[ formatTime( changeDateTimestamp ) ] ).parse();
			} else {
				return `${ formatDate( changeDateTimestamp ) }`;
			}
		},
		description() {
			return ( this.pages && this.pages[ this.pageid ] &&
				this.pages[ this.pageid ].description ) ?
				this.pages[ this.pageid ].description : '';
		},
		isTempUser() {
			return mw.util.isTemporaryUser( this.user );
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.cdx-card.ext-personal-dashboard-moderation-card {
	border-left: 0;
	border-right: 0;
	border-radius: 0;
	border-bottom: 0;
	border-top: 1px solid @border-color-subtle;
	padding: 0.5rem;

	&:hover {
		background-color: @background-color-interactive;
	}

	.cdx-card__text {
		width: 100%;
	}

	&:last-child {
		border-bottom: 1px solid @border-color-subtle;
	}

	.ext-personal-dashboard-moderation-card-icon {
		color: @color-subtle;
	}

	.ext-personal-dashboard-moderation-card-info {
		display: flex;
		flex-direction: column;
		gap: 0.44rem;
	}

	.ext-personal-dashboard-moderation-card-info-title-row {
		display: flex;
		flex-flow: row wrap;

		.ext-personal-dashboard-moderation-card-info-title {
			font-weight: @font-weight-bold;
			line-height: @line-height-medium;
			color: @color-base;
		}
	}

	.ext-personal-dashboard-moderation-card-info-title-row-additional {
		margin-left: auto;
	}

	.ext-personal-dashboard-moderation-card-missing-comment-message {
		font-style: italic;
	}

	.ext-personal-dashboard-moderation-card-separator {
		padding: 0 0.25rem;
		font-weight: @font-weight-normal;
	}

	.ext-personal-dashboard-moderation-card-info-description {
		line-height: @line-height-medium;
		padding-left: 0.25rem;
	}

	.mw-tempuserlink {
		background-color: @background-color-interactive;
		outline: 2px solid @background-color-interactive;
		border-radius: @border-radius-base;
		white-space: nowrap;
		margin-left: 2px;
	}
}

</style>
