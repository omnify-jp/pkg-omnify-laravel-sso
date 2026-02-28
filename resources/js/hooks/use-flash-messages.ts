import { usePage } from '@inertiajs/react';
import { App } from 'antd';
import { useEffect, useRef } from 'react';

export function useFlashMessages() {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;
    const { message } = App.useApp();
    const shownRef = useRef<string | null>(null);

    useEffect(() => {
        const key = JSON.stringify(flash);
        if (key === shownRef.current || key === '{}' || key === 'null' || key === 'undefined') return;
        shownRef.current = key;

        if (flash?.success) {
            message.success(flash.success);
        }
        if (flash?.error) {
            message.error(flash.error);
        }
    }, [flash, message]);
}
