<template>
	<cdx-icon :icon="cdxIconInfo" @click="open = true">
	</cdx-icon>
	<cdx-dialog
		v-model:open="open"
		:title="title"
		:use-close-button="true"
		:default-action="defaultAction"
		@default="open = false"
	>
		<p>{{ subtitle }}</p>
		<cdx-icon
			:icon="cdxIconArticle"
			size="medium">
		</cdx-icon>
		{{ articleText }}
		<br>
		<cdx-icon
			:icon="cdxIconUserActive"
			size="medium">
		</cdx-icon>
		{{ authorText }}
		<br>
		<cdx-icon
			:icon="cdxIconTextSummary"
			size="medium">
		</cdx-icon>
		{{ editSummaryText }}
		<br>
		{{ byteText }}
		<br>
		<cdx-info-chip>{{ chipText }}</cdx-info-chip>
		{{ tagsText }}
	</cdx-dialog>
</template>

<script>
const { defineComponent, ref } = require( 'vue' );
const {
	CdxDialog,
	CdxIcon,
	CdxInfoChip
} = require( '@wikimedia/codex' );
const { cdxIconArticle, cdxIconUserActive, cdxIconTextSummary, cdxIconInfo } = require( '../icons.json' );
module.exports = defineComponent( {
	name: 'InfoPopover',
	components: {
		CdxDialog,
		CdxIcon,
		CdxInfoChip
	},
	setup() {
		const open = ref( false );
		const defaultAction = {
			label: 'Cancel'
		};
		const title = mw.message( 'personal-dashboard-risky-article-edits-info-title' ).text();
		const subtitle = mw.message( 'personal-dashboard-risky-article-edits-info-subtitle' ).text();
		const articleText = mw.message( 'personal-dashboard-risky-article-edits-info-article-title' ).text();
		const authorText = mw.message( 'personal-dashboard-risky-article-edits-info-author' ).text();
		const editSummaryText = mw.message( 'personal-dashboard-risky-article-edits-edit-summary' ).text();
		const byteText = mw.message( 'personal-dashboard-risky-article-edits-bytes' ).text();
		const chipText = mw.message( 'personal-dashboard-risky-article-edits-tag-text' ).text();
		const tagsText = mw.message( 'personal-dashboard-risky-article-edits-tags' ).text();
		return {
			title,
			subtitle,
			articleText,
			authorText,
			byteText,
			tagsText,
			chipText,
			editSummaryText,
			open,
			defaultAction,
			cdxIconArticle,
			cdxIconTextSummary,
			cdxIconUserActive,
			cdxIconInfo
		};
	}
} );
</script>
