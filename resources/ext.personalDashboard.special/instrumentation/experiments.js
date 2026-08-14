/**
 * Wraps a TestKitchen instrument so every send() call is tagged with the
 * module group this page load resolved to server-side and the assigned
 * variant of every experiment whose assignment took effect (ExperimentResolver
 * stays the single source of truth; nothing here recomputes it). Both go into
 * a single JSON-encoded action_context field, rather than splitting variants
 * into action_subtype: TK's SDKs already populate an experiment.assigned
 * field with the group/variant name for actual experiment events, so
 * action_subtype reads as that field to anyone analyzing the schema and
 * would be misleading for health metrics, which are separate
 * instrumentation. module_variants is a map since more than one experiment
 * can be enrolled at once; it's always present, empty when nothing is
 * enrolled, so the shape is stable for extraction queries. The tag is only
 * merged in when there's a module group to report: most requests don't hit
 * an experiment at all.
 *
 * @param {Object} instrument A TestKitchen instrument implementing send()
 * @return {Object} A send()-only object implementing the same call shape
 */
function withExperimentTagging( instrument ) {
	const moduleGroup = mw.config.get( 'wgPersonalDashboardModuleGroup', null );
	const experimentVariants = mw.config.get( 'wgPersonalDashboardExperimentVariants', {} );

	return {
		send( action, interactionData, contextualAttributes ) {
			const tags = {};
			if ( moduleGroup !== null ) {
				// eslint-disable-next-line camelcase
				tags.action_context = JSON.stringify( {
					// eslint-disable-next-line camelcase
					module_group: moduleGroup,
					// A stray [] from the wire (an empty PHP array without the
					// server-side object cast) still serializes as {} here.
					// eslint-disable-next-line camelcase
					module_variants: Object.assign( {}, experimentVariants )
				} );
			}
			instrument.send(
				action,
				Object.assign( {}, interactionData, tags ),
				contextualAttributes
			);
		}
	};
}

module.exports = { withExperimentTagging };
