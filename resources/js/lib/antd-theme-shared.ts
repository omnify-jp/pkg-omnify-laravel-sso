import type { ThemeConfig } from 'antd';

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
//   - borderRadius: 2px — 極めてシャープで整然としたデザイン
//   - 日本の業務アプリは角丸を最小限に抑えるのが主流
//   - 過度な角丸 (6px+) はカジュアルな印象を与え、業務用途には不向き
//
// ■ フォントサイズ (Font Size)
//   - fontSize: 14px (Ant Design 標準) — 日本語テキストの可読性を確保
//   - compactAlgorithm を使わず手動で密度を高めることで 14px を維持
//   - 行間: 日本語テキストは line-height 1.8〜2.0 が推奨だが、
//     Ant Design コンポーネント内部では自動調整されるため token では設定しない
//
// ■ カラー (Colors)
//   - Indigo (#4f46e5): モダンで洗練された印象 — 信頼性と専門性を兼ね備える
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
export const FONT_FAMILY_JP = [
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
export const sharedTokens: ThemeConfig['token'] = {
    fontFamily: FONT_FAMILY_JP,
    borderRadius: 2,
};

/** Layout コンポーネントトークン（色以外 — light/dark 共通） */
export const sharedLayoutTokens = {
    headerPadding: '0 16px',
    headerHeight: 48,
};

/** Light 用 Layout カラー */
export const lightLayoutTokens = {
    headerBg: '#ffffff',
    siderBg: '#ffffff',
    lightSiderBg: '#ffffff',
    bodyBg: '#fafafa',
};

/** Dark 用 Layout カラー — darkAlgorithm のデフォルトを尊重 */
export const darkLayoutTokens = {
    // darkAlgorithm が自動生成するカラーを使用
    // headerBg, siderBg, bodyBg は指定しない
};

/** 共通の Typography コンポーネントトークン
 *  titleMarginTop / titleMarginBottom を 0 に設定 — 余白は親 Flex/Row/Col で制御
 *  各ページで style={{ margin: 0 }} を個別に指定する必要がなくなる */
export const sharedTypographyTokens = {
    titleMarginTop: 0,
    titleMarginBottom: 0,
};

/** 共通の Breadcrumb コンポーネントトークン */
export const sharedBreadcrumbTokens = {};

/** 共通の Form コンポーネントトークン */
export const sharedFormTokens = {};

/** 共通の Menu コンポーネントトークン */
export const sharedMenuTokens = {
    itemBorderRadius: 4,
};
