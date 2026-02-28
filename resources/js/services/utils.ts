import type { FormInstance } from 'antd';
import { isAxiosError } from 'axios';

type LaravelValidationError = {
    message: string;
    errors: Record<string, string[]>;
};

/**
 * Handle axios 422 validation errors by setting them on an antd Form instance.
 * Call this in useMutation's onError callback.
 */
export function handleValidationErrors(error: unknown, form: FormInstance): boolean {
    if (isAxiosError<LaravelValidationError>(error) && error.response?.status === 422) {
        const serverErrors = error.response.data.errors;
        form.setFields(
            Object.entries(serverErrors).map(([name, messages]) => ({
                name,
                errors: messages,
            })),
        );
        return true;
    }
    return false;
}
