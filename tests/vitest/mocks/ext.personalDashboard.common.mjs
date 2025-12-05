import { vi } from 'vitest';
import { ref } from 'vue';

export function useFetchActivityResult() {
	return {
		recentActivityResult: ref( null ),
		loading: ref( true ),
		error: ref( null ),
		fetchRecentActivity: vi.fn()
	};
}

module.exports = {
	useFetchActivityResult
};
