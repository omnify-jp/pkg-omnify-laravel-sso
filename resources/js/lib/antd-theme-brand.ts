import type { ThemeConfig } from 'antd';
import { theme as antdTheme } from 'antd';
import {
    FONT_FAMILY_JP,
    sharedTokens,
    sharedLayoutTokens,
    sharedMenuTokens,
    sharedTypographyTokens,
    sharedBreadcrumbTokens,
    sharedFormTokens,
    lightLayoutTokens,
    darkLayoutTokens,
} from './antd-theme-shared';

// ─── Light Theme ──────────────────────────────────────────────────────────────

export const lightTheme: ThemeConfig = {
    // defaultAlgorithm のみ使用 — compact は fontSize を 12px に縮小するため不使用
    // 代わりに controlHeight / padding を手動で縮小して密度を確保
    algorithm: antdTheme.defaultAlgorithm,
    cssVar: {},
    token: {
        ...sharedTokens,
        // Indigo: モダンで洗練された印象 — 信頼性と専門性を兼ね備えたカラー
        colorPrimary: '#4f46e5',
    },
    components: {
        Layout: {
            ...sharedLayoutTokens,
            ...lightLayoutTokens,
        },
        Menu: {
            ...sharedMenuTokens,
        },
        Typography: {
            ...sharedTypographyTokens,
        },
        Breadcrumb: {
            ...sharedBreadcrumbTokens,
        },
        Form: {
            ...sharedFormTokens,
        },
    },
};

// ─── Dark Theme ───────────────────────────────────────────────────────────────

export const darkTheme: ThemeConfig = {
    // darkAlgorithm のみ — compact 不使用（sharedTokens で密度を手動制御）
    algorithm: antdTheme.darkAlgorithm,
    cssVar: {},
    token: {
        ...sharedTokens,
    },
    components: {
        Layout: {
            ...sharedLayoutTokens,
            ...darkLayoutTokens,
        },
        Menu: {
            ...sharedMenuTokens,
        },
        Typography: {
            ...sharedTypographyTokens,
        },
        Breadcrumb: {
            ...sharedBreadcrumbTokens,
        },
        Form: {
            ...sharedFormTokens,
        },
    },
};

// ─── Branding Theme (Auth Pages — Left Panel) ────────────────────────────────

/** 認証ページ左パネル用ダークテーマ（ブランディング表示）
 *  常にダーク — アプリの light/dark モード に依存しない
 *  ネストされた ConfigProvider は親の component token override を継承するため
 *  bodyBg / siderBg を明示的に指定する必要がある */
export const brandingTheme: ThemeConfig = {
    algorithm: antdTheme.darkAlgorithm,
    cssVar: {},
    token: {
        fontFamily: FONT_FAMILY_JP,
    },
    components: {
        Layout: {
            ...sharedLayoutTokens,
            bodyBg: '#141414',
            siderBg: '#141414',
            headerBg: '#141414',
        },
    },
};

// ─── Auth Theme (Auth Pages — Form Panel) ─────────────────────────────────────
//
// 認証ページ（ログイン・登録・パスワードリセット）専用テーマ。
// OmnifyProvider のグローバルテーマを継承し、Form 間隔のみオーバーライド。
//
// Admin ページは itemMarginBottom: 0（Flex gap で制御）だが、
// Auth ページは children として Form を受け取るため、テーマトークンで間隔を確保。
//
//  Token                   │ Admin │ Auth │ Derivation
// ─────────────────────────┼───────┼──────┼────────────────────
//  Form.itemMarginBottom   │   0   │  24  │ 14 × φ ≈ 23 → 24
//  Form.verticalLabelPadding│ 4px  │ 8px  │ label-input 間のゆとり
// ─────────────────────────┴───────┴──────┴────────────────────

/** 認証ページ専用テーマ — フォーム間隔を広げてゆったりした認証 UI を実現 */
export const authTheme: ThemeConfig = {
    components: {
        Form: {
            itemMarginBottom: 24,              // 14 × φ ≈ 23 → 24 (admin: 0)
            verticalLabelPadding: '0 0 8px',   // ラベルと入力の間にゆとり (admin: 4px)
        },
    },
};
