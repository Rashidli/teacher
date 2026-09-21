import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Backend-dən (HandleInertiaRequests → features) gələn feature flag-lər
export function useFeatures() {
    const page = usePage();

    return {
        teachersEnabled: computed(() => Boolean(page.props.features?.teachers)),
    };
}
