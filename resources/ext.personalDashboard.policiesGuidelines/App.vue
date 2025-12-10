<template>
	<div class="personal-dashboard-policies-guidelines__container">
		<div class="personal-dashboard-policies-guidelines__list">
			<list-card
				v-for="( pages, prefix ) in cards"
				:key="prefix"
				:prefix
				:pages>
			</list-card>
		</div>
	</div>
</template>

<script>
const { defineComponent } = require( 'vue' );
const ListCard = require( './components/ListCard.vue' );

module.exports = defineComponent( {
	name: 'PoliciesGuidelines',
	components: { ListCard },
	setup() {
		return {
			cards: {
				'neutral-point-of-view': [
					'error',
					'success'
				],
				'no-original-research': [
					'error',
					'error'
				],
				verifiability: [
					'warning',
					'error',
					'success'
				],
				'assume-good-faith': [
					'error',
					'success'
				]
			}
		};
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.personal-dashboard-policies-guidelines {
	&__container > p {
		margin-top: @spacing-0;
		margin-bottom: 1em;
		padding: 0;
	}

	&__list {
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
}
</style>
