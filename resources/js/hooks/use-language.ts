import { useContext } from 'react';

import { LanguageContext, type LanguageContextValue } from '@omnify-core/contexts/language-context';

export function useLanguage(): LanguageContextValue {
    const context = useContext(LanguageContext);

    if (!context) {
        throw new Error('useLanguage must be used within an <SsoProvider> or <LanguageProvider>');
    }

    return context;
}
