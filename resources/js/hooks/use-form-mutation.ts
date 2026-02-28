import { router } from '@inertiajs/react';
import type { FormInstance } from 'antd';
import { useMutation } from '@tanstack/react-query';
import { handleValidationErrors } from '@omnify-core/services/utils';

interface UseFormMutationOptions<TData, TResult = unknown> {
    form: FormInstance;
    mutationFn: (data: TData) => Promise<TResult>;
    redirectTo?: string;
    onSuccess?: (data: TResult) => void;
    onError?: (error: unknown) => void;
}

/**
 * Form mutation hook — wraps TanStack Query useMutation with:
 *   - Automatic Laravel 422 → antd form.setFields() error mapping
 *   - Optional Inertia redirect on success
 */
export function useFormMutation<TData, TResult = unknown>({
    form,
    mutationFn,
    redirectTo,
    onSuccess,
    onError,
}: UseFormMutationOptions<TData, TResult>) {
    return useMutation({
        mutationFn,
        onSuccess: (data) => {
            onSuccess?.(data);
            if (redirectTo) router.visit(redirectTo);
        },
        onError: (error) => {
            handleValidationErrors(error, form);
            onError?.(error);
        },
    });
}
