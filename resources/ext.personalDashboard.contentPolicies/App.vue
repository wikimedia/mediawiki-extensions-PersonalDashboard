<template>
	<div class="content-policies-container">
		<p>{{ $i18n( 'content-policies-description' ).text() }}</p>

		<div class="content-policies-grid">
			<cdx-toggle-button ref="card1" v-model="showCard1">
				<div>
					<h4>①</h4>
					<h4>{{ $i18n( 'content-policies-card-1-title' ).text() }}</h4>
				</div>
			</cdx-toggle-button>

			<cdx-toggle-button ref="card2" v-model="showCard2">
				<div>
					<h4>②</h4>
					<h4>{{ $i18n( 'content-policies-card-2-title' ).text() }}</h4>
				</div>
			</cdx-toggle-button>

			<cdx-toggle-button ref="card3" v-model="showCard3">
				<div>
					<h4>③</h4>
					<h4>{{ $i18n( 'content-policies-card-3-title' ).text() }}</h4>
				</div>
			</cdx-toggle-button>

			<cdx-toggle-button ref="card4" v-model="showCard4">
				<div>
					<h4>④</h4>
					<h4>{{ $i18n( 'content-policies-card-4-title' ).text() }}</h4>
				</div>
			</cdx-toggle-button>
		</div>

		<cdx-popover
			v-model:open="showCard1"
			:anchor="card1"
			:title="$i18n( 'content-policies-card-1-title' ).text()"
			:icon="cdxIconLightbulb"
			:use-close-button="true"
			:render-in-place="true">
			{{ $i18n( 'content-policies-card-1-description' ).text() }}
		</cdx-popover>

		<cdx-popover
			v-model:open="showCard2"
			:anchor="card2"
			:title="$i18n( 'content-policies-card-2-title' ).text()"
			:icon="cdxIconLightbulb"
			:use-close-button="true"
			:render-in-place="true">
			{{ $i18n( 'content-policies-card-2-description' ).text() }}
		</cdx-popover>

		<cdx-popover
			v-model:open="showCard3"
			:anchor="card3"
			:title="$i18n( 'content-policies-card-3-title' ).text()"
			:icon="cdxIconLightbulb"
			:use-close-button="true"
			:render-in-place="true">
			{{ $i18n( 'content-policies-card-3-description' ).text() }}
		</cdx-popover>

		<cdx-popover
			v-model:open="showCard4"
			:anchor="card4"
			:title="$i18n( 'content-policies-card-4-title' ).text()"
			:icon="cdxIconLightbulb"
			:use-close-button="true"
			:render-in-place="true">
			{{ $i18n( 'content-policies-card-4-description' ).text() }}
		</cdx-popover>
	</div>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const { CdxPopover, CdxToggleButton } = require( './codex.js' );
const { cdxIconLightbulb } = require( './icons.json' );

module.exports = defineComponent( {
	name: 'ContentPolicies',
	components: { CdxToggleButton, CdxPopover },
	setup() {
		return {
			card1: ref(),
			card2: ref(),
			card3: ref(),
			card4: ref(),
			showCard1: ref( false ),
			showCard2: ref( false ),
			showCard3: ref( false ),
			showCard4: ref( false ),
			cdxIconLightbulb
		};
	}
} );
</script>

<style lang="less">
.content-policies-container {
	overflow-wrap: break-word;

	p {
		margin-top: 0;
		margin-bottom: 1em;
		padding-bottom: 0;
	}

	.cdx-popover__header__title {
		overflow: hidden;
		overflow-wrap: break-word;
	}
}

.content-policies-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	grid-template-rows: auto auto;
	gap: 16px;

	.cdx-toggle-button {
		max-width: none;
		padding: 16px;
		white-space: normal;

		h4 {
			margin: 0;
			padding: 0;
		}
	}
}
</style>
