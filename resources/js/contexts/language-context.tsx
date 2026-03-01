import { createContext, useCallback, useMemo } from 'react';

import type { SsoLanguageData, SsoLocale } from '@omnify-core/types/sso';

export type LanguageContextValue = {
    locale: SsoLocale;
    locales: readonly SsoLocale[];
    localeNames: Record<SsoLocale, string>;
    setLocale: (locale: SsoLocale) => void;
};

export const LanguageContext = createContext<LanguageContextValue | null>(null);

type LanguageProviderProps = {
    data: SsoLanguageData;
    onLocaleChange?: (locale: SsoLocale) => void;
    children: React.ReactNode;
};

export function LanguageProvider({ data, onLocaleChange, children }: LanguageProviderProps) {
    const setLocale = useCallback(
        (locale: SsoLocale) => {
            document.cookie = `locale=${locale};path=/;max-age=31536000`;
            document.documentElement.lang = locale;
            onLocaleChange?.(locale);
        },
        [onLocaleChange],
    );

    const value = useMemo<LanguageContextValue>(
        () => ({
            locale: data.locale,
            locales: data.locales,
            localeNames: data.localeNames,
            setLocale,
        }),
        [data, setLocale],
    );

    return <LanguageContext value={value}>{children}</LanguageContext>;
}
