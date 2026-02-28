import type { ThemeConfig } from 'antd';
import { theme as antdTheme } from 'antd';

// ─────────────────────────────────────────────────────────────────────────────
// Japanese Enterprise SaaS Theme — Design Conventions
// ─────────────────────────────────────────────────────────────────────────────
//
// このテーマは日本市場向け業務用 SaaS アプリケーションに最適化されています。
//
// ■ フォント (Typography)
//   - 英字: "Helvetica Neue", Arial — クリーンで視認性が高い
//   - 日本語 (macOS/iOS): "Hiragino Kaku Gothic ProN", "Hiragino Sans"
//     → Apple デバイスにプリインストール済み。ウェイトバリエーション豊富
//   - 日本語 (Windows): "Noto Sans JP"
//     → 2025年4月より Windows 11 標準搭載。Google Fonts でも配布
//   - フォールバック: sans-serif
//   参考: https://ics.media/entry/200317/
//         https://www.bloomstreetjapan.com/best-japanese-font-setting-for-websites/
//
// ■ コンパクト密度 (Compact Density)
//   - 日本のユーザーは情報密度の高い UI を好む (nihonium.io)
//   - controlHeight を手動で縮小 (32→28px) してフォントサイズ 14px を維持
//     ※ compactAlgorithm は fontSize を 12px に縮小するため不使用
//   - 漢字は少ない文字数で多くの意味を伝えるため、余白を詰めても可読性を保てる
//   参考: https://nihonium.io/japanese-uiux-design-key-requirements-for-saas/
//
// ■ 角丸 (Border Radius)
//   - borderRadius: 4px — 日本の業務アプリはシャープで整然としたデザインが主流
//   - 過度な角丸 (8px+) はカジュアルな印象を与え、業務用途には不向き
//
// ■ フォントサイズ (Font Size)
//   - fontSize: 14px (Ant Design 標準) — 日本語テキストの可読性を確保
//   - compactAlgorithm を使わず手動で密度を高めることで 14px を維持
//   - 行間: 日本語テキストは line-height 1.8〜2.0 が推奨だが、
//     Ant Design コンポーネント内部では自動調整されるため token では設定しない
//
// ■ カラー (Colors)
//   - 青 (Blue): 信頼性・安心感 — 日本の業務アプリで最も多用される
//   - 赤 (Red): 警告・アクション — 日本文化で注意喚起の意味合いが強い
//   - 白 (White): 清潔感・シンプルさ
//   参考: https://nihonium.io/japanese-uiux-design-key-requirements-for-saas/
// ─────────────────────────────────────────────────────────────────────────────

/**
 * 日本語 Web フォントスタック
 * 英字フォント → macOS 日本語 → Windows 日本語 → フォールバック
 *
 * - "Helvetica Neue", Arial: 英字表示用（先に記述して英字を優先マッチ）
 * - "Hiragino Kaku Gothic ProN": macOS/iOS 標準ゴシック体
 * - "Hiragino Sans": macOS 10.11+ の新しいゴシック体（ウェイト豊富）
 * - "Noto Sans JP": Windows 11 標準搭載 (2025〜)、Android でも利用可能
 * - sans-serif: 最終フォールバック
 */
const FONT_FAMILY_JP = [
    '"Helvetica Neue"',
    'Arial',
    '"Hiragino Kaku Gothic ProN"',
    '"Hiragino Sans"',
    '"Noto Sans JP"',
    'sans-serif',
].join(', ');

// ─── 黄金比トークンシステム (Golden Ratio Token System) ─────────────────────
//
// base = 14px (本文フォントサイズ) を起点に、黄金比 φ = 1.618 を適用。
// 隣接する UI 要素が調和のとれたプロポーションを持つ。
// 参考: japanese-design-guideline.md §6
//
//  Token              │ Value │ Derivation            │ Ratio
// ────────────────────┼───────┼───────────────────────┼──────────
//  fontSize           │  14   │ base                  │
//  fontSizeSM (12)    │  12   │ 日本語最小可読サイズ    │
//  fontSizeHeading5   │  16   │ ≈ base × 1.14        │
//  fontSizeHeading4   │  20   │ ≈ base × √φ (17.8)   │ ~√φ
//  controlHeightSM    │  22   │ 14 × φ   = 22.65     │ ≈ φ
//  fontSizeHeading3   │  24   │ 14 × φ   = 22.7 → 24 │ ≈ φ
//  controlHeight      │  28   │ 14 × φ^1.5 = 28.8    │ ≈ φ^1.5
//  fontSizeHeading2   │  30   │ 24 × √φ  = 30.5      │ √φ from H3
//  controlHeightLG    │  36   │ 14 × φ²  = 36.6      │ ≈ φ²
//  Menu.itemHeight    │  36   │ 14 × φ²  = 36.6      │ ≈ φ²
//  fontSizeHeading1   │  38   │ 24 × φ   = 38.8      │ φ from H3
//  Layout.headerHeight│  48   │ 36 × √φ  = 45.7 → 48 │ ~√φ (8px grid)
// ────────────────────┴───────┴───────────────────────┴──────────

/** 共通の global token（light/dark で共有） */
const sharedTokens: ThemeConfig['token'] = {
    fontFamily: FONT_FAMILY_JP,

    // ── Typography Scale (φ progression from 14px) ──
    fontSize: 14,                   // base
    fontSizeHeading5: 16,           // small heading
    fontSizeHeading4: 20,           // ≈ base × √φ
    fontSizeHeading3: 24,           // ≈ base × φ  (22.7 → 24)
    fontSizeHeading2: 30,           // ≈ H3 × √φ  (30.5 → 30)
    fontSizeHeading1: 38,           // ≈ H3 × φ   (38.8 → 38)

    // ── Border Radius ── 業務用 SaaS はシャープなデザインが標準
    borderRadius: 4,
    borderRadiusLG: 6,
    borderRadiusSM: 2,

    // ── Compact Density (φ progression from 14px) ──
    // compactAlgorithm は fontSize を 12px に縮小するため不使用。
    // 代わりに controlHeight を φ 倍で手動算出して密度を確保。
    controlHeightSM: 22,            // 14 × φ     = 22.65 ≈ 22
    controlHeight: 28,              // 14 × φ^1.5 = 28.81 ≈ 28
    controlHeightLG: 36,            // 14 × φ²    = 36.65 ≈ 36

    // ── Spacing ──
    paddingXS: 4,
    paddingSM: 8,
    padding: 12,                    // 8 × φ = 12.9 ≈ 12
    paddingLG: 16,
};

/** 共通の Layout コンポーネントトークン */
const sharedLayoutTokens = {
    headerHeight: 48,
    headerPadding: '0 24px' as string,
};

/** 共通の Typography コンポーネントトークン — Title のデフォルトマージンを除去 */
const sharedTypographyTokens = {
    titleMarginTop: 0,
    titleMarginBottom: 0,
};

/** 共通の Breadcrumb コンポーネントトークン — パンくずリスト */
const sharedBreadcrumbTokens = {
    fontSize: 12,                   // title(20px) / φ = 12.4 ≈ 12 — 黄金比でタイトルとの階層を明確化
    separatorMargin: 8,
};

/** 共通の Form コンポーネントトークン — vertical レイアウト + 間隔は親 Flex で制御 */
const sharedFormTokens = {
    itemMarginBottom: 0,            // 親の Flex gap で制御するため Form.Item 自体のマージンは除去
    verticalLabelPadding: '0 0 4px',// ラベルと入力の間をコンパクトに (デフォルト 8px → 4px)
};

/** 共通の Menu コンポーネントトークン — サイドバー用 */
const sharedMenuTokens = {
    itemBg: 'transparent',
    subMenuItemBg: 'transparent',
    itemBorderRadius: 4,
    itemHeight: 36,
    iconSize: 16,
    collapsedIconSize: 16,
};

// ─── Light Theme ──────────────────────────────────────────────────────────────

export const lightTheme: ThemeConfig = {
    // defaultAlgorithm のみ使用 — compact は fontSize を 12px に縮小するため不使用
    // 代わりに controlHeight / padding を手動で縮小して密度を確保
    algorithm: antdTheme.defaultAlgorithm,
    cssVar: {},
    token: {
        ...sharedTokens,
        // 青: 信頼性を表す — 日本の業務アプリで最も一般的なプライマリカラー
        colorPrimary: '#1677ff',
    },
    components: {
        Layout: {
            ...sharedLayoutTokens,
            siderBg: '#ffffff',
            headerBg: '#ffffff',
            bodyBg: '#f5f5f5',
            // トリガー（サイドバー折りたたみボタン）: 控えめなグレー
            triggerBg: '#f0f0f0',
            triggerColor: '#595959',
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
            siderBg: '#141414',
            headerBg: '#141414',
            bodyBg: '#000000',
            triggerBg: '#1f1f1f',
            triggerColor: '#a6a6a6',
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

// ─── Branding Theme (Auth Pages) ──────────────────────────────────────────────

/** 認証ページ左パネル用ダークテーマ（ブランディング表示） */
export const brandingTheme: ThemeConfig = {
    algorithm: antdTheme.darkAlgorithm,
    cssVar: {},
    token: {
        fontFamily: FONT_FAMILY_JP,
        colorBgContainer: '#141414',
        colorBgLayout: '#141414',
    },
    components: {
        Layout: {
            bodyBg: '#141414',
        },
    },
};
