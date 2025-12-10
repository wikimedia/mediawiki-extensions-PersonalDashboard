import { vi } from 'vitest';
import { ref } from 'vue';

export { default as MultiStepDialog } from '/resources/ext.personalDashboard.common/components/MultiStepDialog.vue';

export function useFetchActivityResult() {
	return {
		recentActivityResult: ref( null ),
		loading: ref( true ),
		error: ref( null ),
		fetchRecentActivity: vi.fn()
	};
}
