<template>
	<div class="personal-dashboard-policies-guidelines__container">
		<div class="personal-dashboard-policies-guidelines__list">
			<list-card
				v-for="( steps, name ) in cards"
				:key="name"
				:name
				:steps>
			</list-card>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const ListCard = require( './components/ListCard.vue' );

module.exports = defineComponent( {
	components: { ListCard },
	props: {
		// eslint-disable-next-line vue/no-unused-properties
		rendermode: {
			type: String,
			default: ''
		}
	},
	setup() {
		return {
			cards: {
				'neutral-point-of-view': [ 'error', 'success' ],
				'no-original-research': [ 'error', 'error' ],
				verifiability: [ 'warning', 'error', 'success' ],
				'assume-good-faith': [ 'error', 'success' ]
			}
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-policies-guidelines {
	&__list {
		.cdx-card {
			border-color: @border-color-subtle;
		}

		.cdx-card:not( :first-child ) {
			border-top-left-radius: @border-radius-sharp;
			border-top-right-radius: @border-radius-sharp;
		}

		.cdx-card:not( :last-child ) {
			margin-bottom: -1px;
			border-bottom-left-radius: @border-radius-sharp;
			border-bottom-right-radius: @border-radius-sharp;
		}
	}

	// Revert a:not([href]) text color from Minerva
	.skin-minerva &-examples-link {
		color: @color-link;
	}
}
</style>
