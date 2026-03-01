/**
 * Barrel re-export — 既存の import パスとの互換性を維持
 *
 * テーマ定義は以下の 3 ファイルに分割:
 *   antd-theme-shared.ts  — 共通トークン（フォント、レイアウト、コンポーネント）
 *   antd-theme-admin.ts   — 管理者テーマ（GOD MODE カラーパレット）
 *   antd-theme-brand.ts   — ユーザー向けテーマ（light/dark/branding/auth）
 */

export { adminColors, adminTheme, adminAuthTheme } from './antd-theme-admin';
export { lightTheme, darkTheme, brandingTheme, authTheme } from './antd-theme-brand';
