import type { ThemeConfig } from 'antd';
import {
    sharedLayoutTokens,
    sharedMenuTokens,
    sharedTypographyTokens,
    lightLayoutTokens,
} from './antd-theme-shared';

// ─── Admin Color Palette ─────────────────────────────────────────────────────
//
// 管理者 (GOD MODE) — Deep Crimson + Neutral Dark
//
// デザイン原則:
//   1. Primary は彩度の高い深紅 — 権威・信頼・プロフェッショナル
//   2. 背景は完全ニュートラル — 色味なし（ピンクやワイン色のティントは NG）
//   3. テキストはニュートラルグレー — ティントなしでクリーンな印象
//
// コントラスト比 (WCAG 2.1):
//   text (#E5E5E5) on bg (#141414)     → 15.3:1 (AAA) ✓
//   textMuted effective on bg           →  4.6:1 (AA)  ✓
//   #fff on primary (#B91C1C)           →  5.7:1 (AA)  ✓
//
//  Color       │ Value                    │ Usage
// ─────────────┼──────────────────────────┼──────────────────
//  primary     │ #B91C1C (crimson-700)    │ アクセント・選択状態
//  bg          │ #141414 (neutral black)  │ サイドバー / パネル背景
//  text        │ #E5E5E5 (neutral gray)   │ ダーク面の主テキスト
//  textMuted   │ rgba(229,229,229,0.55)   │ ダーク面の補助テキスト
// ─────────────┴──────────────────────────┴──────────────────

/** ダーク面の inline style 用パレット定数 */
export const adminColors = {
    primary: '#B91C1C',
    bg: '#141414',
    text: '#E5E5E5',
    textMuted: 'rgba(229, 229, 229, 0.55)',
} as const;

// ─── Admin Theme (AdminAppLayout — single ConfigProvider) ────────────────────

/** 管理者画面統一テーマ — light ベース + admin accent
 *
 *  サイドバーは adminColors.bg (#141414) の neutral dark。
 *  Menu は theme="dark" で使うため dark* トークンで adminColors に統一。
 *  ヘッダー・コンテンツは light のまま。
 *
 *  Dark Menu Token      │ Value                        │ Purpose
 * ──────────────────────┼──────────────────────────────┼─────────────────
 *  darkItemBg           │ adminColors.bg               │ メニュー背景
 *  darkSubMenuItemBg    │ adminColors.bg               │ サブメニュー背景
 *  darkItemColor        │ adminColors.textMuted        │ 通常テキスト
 *  darkItemHoverColor   │ adminColors.text             │ ホバー時テキスト
 *  darkItemHoverBg      │ rgba(185,28,28,0.12)         │ ホバー時背景
 *  darkItemSelectedBg   │ adminColors.primary          │ 選択時背景
 *  darkItemSelectedColor│ #fff                         │ 選択時テキスト
 *  darkPopupBg          │ adminColors.bg               │ ポップアップ背景
 * ──────────────────────┴──────────────────────────────┴─────────────────
 */
export const adminTheme: ThemeConfig = {
    token: {
        colorPrimary: adminColors.primary,
    },
    components: {
        Menu: {
            ...sharedMenuTokens,
            darkItemBg: adminColors.bg,
            darkSubMenuItemBg: adminColors.bg,
            darkItemColor: adminColors.textMuted,
            darkItemHoverColor: adminColors.text,
            darkItemHoverBg: 'rgba(185, 28, 28, 0.12)',
            darkItemSelectedBg: adminColors.primary,
            darkItemSelectedColor: '#ffffff',
            darkPopupBg: adminColors.bg,
        },
        Layout: {
            ...sharedLayoutTokens,
            ...lightLayoutTokens,
            siderBg: adminColors.bg,
        },
        Typography: {
            ...sharedTypographyTokens,
        },
    },
};

// ─── Admin Auth Theme (AdminAuthLayout — single ConfigProvider) ──────────────

/** 管理者認証ページ統一テーマ — light ベース + admin accent + auth form spacing */
export const adminAuthTheme: ThemeConfig = {
    token: {
        colorPrimary: adminColors.primary,
    },
    components: {
        Form: {
            itemMarginBottom: 24,
            verticalLabelPadding: '0 0 8px',
        },
        Typography: {
            ...sharedTypographyTokens,
        },
    },
};
