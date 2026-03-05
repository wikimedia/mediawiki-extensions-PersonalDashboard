<template>
	<div ref="moduleRef">
		<header-message
			v-if="isMobile && showHeaderMessage && rendermode !== 'mobile-summary'"
			class="ext-personal-dashboard-recent-activity-header-mobile"
			@dismissed="onHeaderMessageClose"></header-message>
		<teleport
			v-if="!isMobile && showHeaderMessage && rendermode !== 'mobile-summary'"
			to=".personal-dashboard-module-header"
		>
			<header-message
				@dismissed="onHeaderMessageClose"></header-message>
		</teleport>
		<div v-if="loading">
			Loading...
		</div>

		<div v-if="error">
			<p>Error: {{ error.message }}</p>
		</div>
		<div
			v-if="recentActivityResult &&
				recentActivityResult.query &&
				recentActivityResult.query.recentchanges &&
				recentActivityResult.query.pages
			">
			<template v-if="isMobile && rendermode === 'mobile-summary'">
				<list-card-mobile
					v-for="rc in recentActivityResult.query.recentchanges.slice( 0, 1 )"
					v-bind="rc"
					:key="`${rendermode}-${rc.rcid}`"
					:pages="recentActivityResult.query.pages">
				</list-card-mobile>
			</template>
			<template v-else>
				<list-card
					v-for="rc in recentActivityResult.query.recentchanges.slice( 0, limit )"
					v-bind="rc"
					:key="`${rendermode}-${rc.rcid}`"
					:pages="recentActivityResult.query.pages">
				</list-card>
			</template>
		</div>
		<template v-if="isMobile">
			<span
				v-if="rendermode === 'mobile-summary'"
				class="ext-personal-dashboard-recent-activity-footer personal-dashboard-module-footer"
			>
				<cdx-button
					:aria-label="buttonAriaLabel"
					action="progressive"
					weight="primary"
				>
					<span v-i18n-html:personal-dashboard-risky-article-edits-mobile-summary-footer-link-text></span>
				</cdx-button>
			</span>
			<span
				v-else-if="rendermode === 'mobile-details'"
				class="ext-personal-dashboard-recent-activity-footer personal-dashboard-module-footer"
			>
				<cdx-card>
					<template #description>
						<span
							id="personal-dashboard-go-to-recentchanges"
							v-i18n-html:personal-dashboard-risky-article-edits-footer-preamble
						></span>
					</template>
				</cdx-card>
			</span>
		</template>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxButton, CdxCard } = require( './codex.js' );
const { useFetchActivityResult } = require( 'ext.personalDashboard.common' );
const ListCard = require( './components/ListCard.vue' );
const ListCardMobile = require( './components/ListCardMobile.vue' );
const HeaderMessage = require( './components/HeaderMessage.vue' );
const api = new mw.Api();

module.exports = defineComponent( {
	components: { CdxButton, CdxCard, ListCard, ListCardMobile, HeaderMessage },
	props: {
		rendermode: {
			type: String,
			default: ''
		}
	},
	setup() {
		const moduleRef = ref();
		// eslint-disable-next-line compat/compat
		const observer = new IntersectionObserver( ( entries ) => {
			if ( entries[ 0 ].isIntersecting ) {
				mw.hook( 'personaldashboard.recentactivity.loaded' ).fire();
			}
		} );

		const isMobile = mw.config.get( 'wgMFMode' ) !== null;
		const limit = isMobile ? 10 : 5;
		const {
			recentActivityResult,
			loading,
			error,
			fetchRecentActivity
		} = useFetchActivityResult();

		return {
			moduleRef,
			observer,
			isMobile,
			limit,
			recentActivityResult,
			loading,
			error,
			fetchRecentActivity,
			buttonAriaLabel: mw.msg( 'personal-dashboard-risky-article-edits-mobile-summary-footer-link-text' ),
			showHeaderMessage: mw.user.options.get( 'personaldashboard-risky-articles-info', 0 )
		};
	},
	methods: {
		async onHeaderMessageClose() {
			await api.postWithEditToken( {
				action: 'options',
				optionname: 'personaldashboard-risky-articles-info',
				optionvalue: 0,
				formatversion: 2
			} );
		}
	},
	mounted() {
		this.observer.observe( this.moduleRef );
		this.fetchRecentActivity( this.limit );
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.ext-personal-dashboard-recent-activity-header {
	display: flex;
	justify-content: space-between;
}

.personal-dashboard-module-body {
	.ext-personal-dashboard-recent-activity-header-mobile {
		margin-bottom: 0.75em;
		margin-top: 0;
	}
}

.ext-personal-dashboard-recent-activity-footer {
	margin: auto;

	a.external {
		background-size: 0;
	}

	.cdx-button {
		width: 100%;
		max-width: none;
	}

	.cdx-card {
		border: 0;
	}

	@media all and ( max-width: @max-width-breakpoint-mobile ) {
		.cdx-button {
			width: 100%;
		}
	}
}
</style>
