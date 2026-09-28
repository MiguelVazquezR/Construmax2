import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';

/**
 * Show the server flash messages ("success" / "error") as toasts.
 */
export function useFlashMessages() {
    const page = usePage();

    watch(
        () => page.props.flash?.success,
        (value) => {
            if (value) ElMessage.success(value);
        }
    );

    watch(
        () => page.props.flash?.error,
        (value) => {
            if (value) ElMessage.error(value);
        }
    );
}
