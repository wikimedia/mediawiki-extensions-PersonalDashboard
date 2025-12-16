<template>
	<a
		:href="userPageUrl"
		class="cdx-link"
		:class="[ userPageClass, userPageMarginClass ]"
		target="_blank">
		{{ creatorName }}
	</a>
	<a v-if="creatorIsTempAccount" class="cdx-link"></a>
</template>

<script>
const { defineComponent } = require( 'vue' );

module.exports = defineComponent( {
	name: 'CreatorByline',
	props: {
		creatorName: { type: String, required: true },
		creatorIsTempAccount: { type: Boolean, required: false }
	},
	computed: {
		userPageClass() {
			let name = 'mw-userlink';

			if ( this.creatorIsTempAccount ) {
				name += ' mw-tempuserlink';
			}

			return name;
		},
		userPageUrl() {
			return mw.util.getUrl( `User:${ this.creatorName }` );
		},
		userPageMarginClass() {
			return mw.config.get( 'skin' ) === 'minerva' ? 'ext-personal-dashboard-moderation-card-user-link-minerva' :
				'';
		}
	}
} );
</script>

<style lang="less">
@import 'mediawiki.skin.variables.less';

.cdx-link {
	// see: https://doc.wikimedia.org/codex/latest/components/mixins/link.html
	.cdx-mixin-link();
}

.ext-personal-dashboard-moderation-card-user-link-minerva {
	margin-top: 1px;
}

</style>
