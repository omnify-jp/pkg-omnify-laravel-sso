import { App, ConfigProvider } from 'antd';
import type { Locale as AntdLocale } from 'antd/es/locale';
import enUS from 'antd/locale/en_US';
import jaJP from 'antd/locale/ja_JP';
import viVN from 'antd/locale/vi_VN';
import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from 'react';
import { lightTheme, darkTheme } from '@omnify-core/lib/antd-theme';

// ─── Types ───────────────────────────────────────────────────────────────────

export type Theme = 'light' | 'dark' | 'system';
export type LocaleCode = string;
export type LocaleMap = Record<string, string>;

interface OmnifyContextValue {
    theme: Theme;
    setTheme: (t: Theme) => void;
    currentLocale: LocaleCode;
    setLocale: (locale: LocaleCode) => void;
    locales: LocaleMap;
    defaultLocale: LocaleCode;
    fallbackLocale: LocaleCode;
    timezone: string;
    setTimezone: (tz: string) => void;
}

const OmnifyContext = createContext<OmnifyContextValue | null>(null);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function loadSavedTheme(): Theme {
    if (typeof window === 'undefined') return 'system';
    const saved = localStorage.getItem('omnify_theme');
    if (saved === 'light' || saved === 'dark' || saved === 'system') return saved;
    return 'system';
}

function resolveIsDark(theme: Theme): boolean {
    if (theme === 'dark') return true;
    if (theme === 'light') return false;
    return typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function applyThemeClass(theme: Theme) {
    const root = document.documentElement;
    root.classList.toggle('dark', resolveIsDark(theme));
}

// ─── Antd locale loader ─────────────────────────────────────────────────────
// Static imports for common locales (zero latency). Others lazy-load on demand.
// Each import() MUST be a static string literal — Vite/Rollup needs it for code-splitting.

const STATIC_LOCALES: Record<string, AntdLocale> = { ja: jaJP, en: enUS, vi: viVN };

/* eslint-disable @typescript-eslint/promise-function-async */
type Loader = () => Promise<{ default: AntdLocale }>;
const L: Record<string, Loader> = {
    ar: () => import('antd/locale/ar_EG'), az: () => import('antd/locale/az_AZ'),
    bg: () => import('antd/locale/bg_BG'), bn: () => import('antd/locale/bn_BD'),
    by: () => import('antd/locale/by_BY'), ca: () => import('antd/locale/ca_ES'),
    cs: () => import('antd/locale/cs_CZ'), da: () => import('antd/locale/da_DK'),
    de: () => import('antd/locale/de_DE'), el: () => import('antd/locale/el_GR'),
    es: () => import('antd/locale/es_ES'), et: () => import('antd/locale/et_EE'),
    eu: () => import('antd/locale/eu_ES'), fa: () => import('antd/locale/fa_IR'),
    fi: () => import('antd/locale/fi_FI'), fr: () => import('antd/locale/fr_FR'),
    ga: () => import('antd/locale/ga_IE'), gl: () => import('antd/locale/gl_ES'),
    he: () => import('antd/locale/he_IL'), hi: () => import('antd/locale/hi_IN'),
    hr: () => import('antd/locale/hr_HR'), hu: () => import('antd/locale/hu_HU'),
    hy: () => import('antd/locale/hy_AM'), id: () => import('antd/locale/id_ID'),
    is: () => import('antd/locale/is_IS'), it: () => import('antd/locale/it_IT'),
    ka: () => import('antd/locale/ka_GE'), kk: () => import('antd/locale/kk_KZ'),
    km: () => import('antd/locale/km_KH'), kn: () => import('antd/locale/kn_IN'),
    ko: () => import('antd/locale/ko_KR'), ku: () => import('antd/locale/ku_IQ'),
    lt: () => import('antd/locale/lt_LT'), lv: () => import('antd/locale/lv_LV'),
    mk: () => import('antd/locale/mk_MK'), ml: () => import('antd/locale/ml_IN'),
    mn: () => import('antd/locale/mn_MN'), mr: () => import('antd/locale/mr_IN'),
    ms: () => import('antd/locale/ms_MY'), my: () => import('antd/locale/my_MM'),
    nb: () => import('antd/locale/nb_NO'), ne: () => import('antd/locale/ne_NP'),
    nl: () => import('antd/locale/nl_NL'), pl: () => import('antd/locale/pl_PL'),
    pt: () => import('antd/locale/pt_PT'), ro: () => import('antd/locale/ro_RO'),
    ru: () => import('antd/locale/ru_RU'), si: () => import('antd/locale/si_LK'),
    sk: () => import('antd/locale/sk_SK'), sl: () => import('antd/locale/sl_SI'),
    sr: () => import('antd/locale/sr_RS'), sv: () => import('antd/locale/sv_SE'),
    ta: () => import('antd/locale/ta_IN'), th: () => import('antd/locale/th_TH'),
    tk: () => import('antd/locale/tk_TK'), tr: () => import('antd/locale/tr_TR'),
    uk: () => import('antd/locale/uk_UA'), ur: () => import('antd/locale/ur_PK'),
    uz: () => import('antd/locale/uz_UZ'), zh: () => import('antd/locale/zh_CN'),
    // Regional variants
    'en-gb': () => import('antd/locale/en_GB'),
    'fr-be': () => import('antd/locale/fr_BE'), 'fr-ca': () => import('antd/locale/fr_CA'),
    'nl-be': () => import('antd/locale/nl_BE'), 'pt-br': () => import('antd/locale/pt_BR'),
    'zh-cn': () => import('antd/locale/zh_CN'), 'zh-hk': () => import('antd/locale/zh_HK'),
    'zh-tw': () => import('antd/locale/zh_TW'),
};
/* eslint-enable @typescript-eslint/promise-function-async */

function useAntdLocale(localeCode: string): AntdLocale {
    const [loaded, setLoaded] = useState<AntdLocale | null>(null);
    const prevCode = useRef<string | null>(null);

    const staticHit = STATIC_LOCALES[localeCode];

    useEffect(() => {
        if (staticHit) return;
        if (localeCode === prevCode.current) return;
        prevCode.current = localeCode;

        const loader = L[localeCode];
        if (!loader) return;

        let cancelled = false;
        loader().then((mod) => {
            if (!cancelled) setLoaded(mod.default);
        });
        return () => { cancelled = true; };
    }, [localeCode, staticHit]);

    if (staticHit) return staticHit;
    if (loaded) return loaded;
    return enUS;
}

// ─── Provider ────────────────────────────────────────────────────────────────

export interface OmnifyProviderProps {
    children: ReactNode;
    defaultTheme?: Theme;
    locales?: LocaleMap;
    defaultLocale?: LocaleCode;
    fallbackLocale?: LocaleCode;
    onLocaleChange?: (locale: LocaleCode) => void;
    timezone?: string;
    onTimezoneChange?: (timezone: string) => void;
}

export function OmnifyProvider({
    children,
    defaultTheme,
    locales = {},
    defaultLocale = '',
    fallbackLocale,
    onLocaleChange,
    timezone: timezoneProp,
    onTimezoneChange,
}: OmnifyProviderProps) {
    const [theme, setThemeState] = useState<Theme>(() => defaultTheme ?? loadSavedTheme());
    const setTheme = useCallback((t: Theme) => setThemeState(t), []);

    useEffect(() => {
        localStorage.setItem('omnify_theme', theme);
        applyThemeClass(theme);
    }, [theme]);

    useEffect(() => {
        if (theme !== 'system') return;
        const mq = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = () => applyThemeClass('system');
        mq.addEventListener('change', handler);
        return () => mq.removeEventListener('change', handler);
    }, [theme]);

    const [currentLocale, setCurrentLocale] = useState<LocaleCode>(() => defaultLocale);
    const setLocale = useCallback(
        (loc: LocaleCode) => {
            setCurrentLocale(loc);
            onLocaleChange?.(loc);
        },
        [onLocaleChange],
    );

    useEffect(() => {
        if (currentLocale) {
            document.documentElement.lang = currentLocale;
        }
    }, [currentLocale]);

    const [timezone, setTimezoneState] = useState<string>(
        () => timezoneProp ?? Intl.DateTimeFormat().resolvedOptions().timeZone,
    );
    const setTimezone = useCallback(
        (tz: string) => {
            setTimezoneState(tz);
            onTimezoneChange?.(tz);
        },
        [onTimezoneChange],
    );

    // Sync external prop → state during render (React-recommended pattern)
    const [prevTimezoneProp, setPrevTimezoneProp] = useState(timezoneProp);
    if (timezoneProp !== undefined && timezoneProp !== prevTimezoneProp) {
        setPrevTimezoneProp(timezoneProp);
        setTimezoneState(timezoneProp);
    }

    const isDark = resolveIsDark(theme);
    const antdTheme = isDark ? darkTheme : lightTheme;

    const antdLocale = useAntdLocale(currentLocale);

    return (
        <OmnifyContext.Provider
            value={{
                theme,
                setTheme,
                currentLocale,
                setLocale,
                locales,
                defaultLocale,
                fallbackLocale: fallbackLocale ?? defaultLocale,
                timezone,
                setTimezone,
            }}
        >
            <ConfigProvider theme={antdTheme} locale={antdLocale}>
                <App>{children}</App>
            </ConfigProvider>
        </OmnifyContext.Provider>
    );
}

// ─── Hooks ───────────────────────────────────────────────────────────────────

export function useTheme(): { theme: Theme; setTheme: (t: Theme) => void } {
    const ctx = useContext(OmnifyContext);
    if (!ctx) throw new Error('useTheme must be used within OmnifyProvider');
    return { theme: ctx.theme, setTheme: ctx.setTheme };
}

export function useLocale(): {
    currentLocale: LocaleCode;
    setLocale: (locale: LocaleCode) => void;
    locales: LocaleMap;
    defaultLocale: LocaleCode;
    fallbackLocale: LocaleCode;
} {
    const ctx = useContext(OmnifyContext);
    if (!ctx) throw new Error('useLocale must be used within OmnifyProvider');
    return {
        currentLocale: ctx.currentLocale,
        setLocale: ctx.setLocale,
        locales: ctx.locales,
        defaultLocale: ctx.defaultLocale,
        fallbackLocale: ctx.fallbackLocale,
    };
}

export function useTimezone(): {
    timezone: string;
    setTimezone: (tz: string) => void;
} {
    const ctx = useContext(OmnifyContext);
    if (!ctx) throw new Error('useTimezone must be used within OmnifyProvider');
    return { timezone: ctx.timezone, setTimezone: ctx.setTimezone };
}
