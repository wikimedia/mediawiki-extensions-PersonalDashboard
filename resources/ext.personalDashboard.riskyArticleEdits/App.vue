<template>
	<div ref="moduleRef">
		<template v-if="showHeaderMessage && rendermode !== 'mobile-summary'">
			<header-message
				v-if="isMobile"
				:is-mobile="isMobile"
				@dismissed="onHeaderMessageClose">
			</header-message>

			<teleport v-else to=".personal-dashboard-module-subheader">
				<header-message @dismissed="onHeaderMessageClose"></header-message>
			</teleport>
		</template>

		<div v-if="loading">
			<cdx-progress-bar inline :aria-label="progressBarAriaLabel"></cdx-progress-bar>
		</div>

		<div v-if="error">
			<p>Error: {{ error.message }}</p>
		</div>

		<template
			v-if="recentActivityResult &&
				recentActivityResult.feed &&
				recentActivityResult.pages">
			<div
				v-if="rendermode === 'mobile-summary'"
				class="personal-dashboard-review-changes__container--mobile">
				<list-card-mobile
					v-for="rc in recentActivityResult.feed.slice( 0, 1 )"
					v-bind="rc"
					:key="`${rendermode}-${rc.feedorigin}-${rc.revid}`"
					:pages="recentActivityResult.pages">
				</list-card-mobile>
			</div>

			<div
				v-else
				class="personal-dashboard-review-changes__container">
				<list-card
					v-for="rc in recentActivityResult.feed"
					v-bind="rc"
					:key="`${rendermode}-${rc.feedorigin}-${rc.revid}`"
					:pages="recentActivityResult.pages"
					:is-mobile="isMobile">
				</list-card>
			</div>
		</template>

		<!-- eslint-disable max-len -->
		<div v-if="rendermode === 'mobile-summary'">
			<cdx-button
				:aria-label="buttonAriaLabel"
				action="progressive"
				weight="primary">
				<span v-i18n-html:personal-dashboard-risky-article-edits-mobile-summary-footer-link-text></span>
			</cdx-button>
		</div>

		<div
			v-else-if="rendermode === 'mobile-details'"
			class="personal-dashboard-module-footer">
			<span
				id="personal-dashboard-go-to-recentchanges"
				v-i18n-html:personal-dashboard-risky-article-edits-footer-preamble>
			</span>
		</div>
		<!-- eslint-enable max-len -->
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxButton, CdxProgressBar } = require( './codex.js' );
const useFetchActivityResult = require( './composables/useFetchActivityResult.js' );
const ListCard = require( './components/ListCard.vue' );
const ListCardMobile = require( './components/ListCardMobile.vue' );
const HeaderMessage = require( './components/HeaderMessage.vue' );
const api = new mw.Api();

module.exports = defineComponent( {
	components: {
		CdxButton,
		CdxProgressBar,
		HeaderMessage,
		ListCard,
		ListCardMobile
	},
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
		const isMenuLinkVisible = mw.config.get( 'wgPersonalDashboardMenuVisible' );
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
			isMenuLinkVisible,
			recentActivityResult,
			loading,
			error,
			fetchRecentActivity,
			buttonAriaLabel: mw.msg( 'personal-dashboard-risky-article-edits-mobile-summary-footer-link-text' ),
			progressBarAriaLabel: mw.msg( 'personal-dashboard-risky-article-edits-progress-bar-aria-label' ),
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
		},
		async markPersonalDashboardVisited() {
			await api.postWithEditToken( {
				action: 'options',
				optionname: 'personaldashboard-visited',
				optionvalue: '1',
				formatversion: 2
			} );
		},
		async markPersonalDashboardEligible() {
			await api.postWithEditToken( {
				action: 'options',
				optionname: 'personaldashboard-eligible',
				optionvalue: '1',
				formatversion: 2
			} );
		}
	},
	mounted() {
		this.observer.observe( this.moduleRef );
		this.fetchRecentActivity( this.limit );
		this.markPersonalDashboardVisited();
		if ( this.isMenuLinkVisible ) {
			this.markPersonalDashboardEligible();
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard {
	&-module {
		&-riskyArticleEdits&-desktop & {
			&-header {
				border-bottom: @border-subtle;
			}

			&-footer {
				border-top: @border-subtle;
			}

			&-header,
			&-footer {
				padding: @spacing-100;
			}

			&-header,
			&-subheader,
			&-body,
			&-footer {
				margin: 0;
			}
		}

		&-riskyArticleEdits &-body .cdx-button {
			width: 100%;
			max-width: none;
		}
	}

	&-review-changes {
		&__container {
			display: flex;
			flex-direction: column;
			gap: @spacing-25;
			background: @background-color-neutral;
			padding: @spacing-25;
		}
	}

	@media screen and ( max-width: @max-width-breakpoint-mobile ) {
		&-module-route-riskyArticleEdits &-review-changes {
			&__message,
			&__container {
				margin-left: -@spacing-100;
				margin-right: -@spacing-100;
			}
		}
	}
}
</style>
