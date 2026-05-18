# TRViS Timetable Server — Frontend

[TRViS](https://github.com/TetsuOtter/TRViS) 用の時刻表データを編集する Web フロントエンドです。
React 18 + TypeScript + [Vite](https://vitejs.dev/) で実装し、パッケージ管理は Yarn 4（Berry）です。

バックエンド API は [`../backend/`](../backend/README.md)、API 仕様は
[`../api_defs/`](../api_defs/README.md) を参照してください。

## 技術スタック

| 領域           | 採用                                                                       |
| -------------- | -------------------------------------------------------------------------- |
| UI             | React 18 + TypeScript（独自デザインバンドル）                              |
| ビルド         | Vite 5（`@vitejs/plugin-react-swc`、`vite-plugin-svgr`）                   |
| データ取得     | [TanStack Query](https://tanstack.com/query)（正規化エンティティ）         |
| API クライアント | `trvis-api`（OpenAPI から生成。`packages/trvis-api` をローカル参照）      |
| 認証           | Firebase Authentication（開発時は Auth Emulator）                          |
| 国際化         | i18next / react-i18next                                                     |
| パッケージ管理 | Yarn 4（`packageManager: yarn@4.13.0`、`nodeLinker: node-modules`）        |

> `package.json` には MUI / Redux / react-router の依存が残っていますが、
> 現在の `src/` はこれらを使っていません（独自デザインバンドルへ移行済み）。
> 実装の実態はこの README とソースが正で、`package.json` の依存一覧ではありません。

## ディレクトリ構成（`src/`）

```text
main.tsx       … エントリ。AppProviders → App をマウント
app/           … App / AppProviders / AuthContext / AuthGate / SettingsContext
api/           … client・adapters・instances・queryClient・queryKeys と hooks/
components/    … 画面コンポーネント（ProjectList, TimetableGrid 等）
data/          … サンプルデータ
firebase/      … Firebase 初期化
i18n/          … 翻訳リソース
lib/ utils/ types/ styles/ assets/
```

`packages/trvis-api/` は OpenAPI Generator（`typescript-fetch`）の出力です。
`package.json` で `trvis-api: link:./packages/trvis-api` として参照しているため、
**フロントをビルドする前に必ずこのパッケージをビルド**しておく必要があります（後述）。

## 環境変数

`.env.sample` をコピーして `.env` を作り、値を埋めます。

```sh
cp .env.sample .env
```

| 変数                              | 用途                                                        |
| --------------------------------- | ----------------------------------------------------------- |
| `VITE_FIREBASE_API_KEY`           | Firebase Web API キー                                       |
| `VITE_FIREBASE_MESSAGE_SENDER_ID` | Firebase Messaging Sender ID                                |
| `VITE_FIREBASE_APP_ID`            | Firebase App ID                                             |
| `VITE_API_BASE_URL`               | API ベース URL（既定 `/api/v1` ＝ nginx 経由の同一オリジン）|

`.env` は Git 管理外です。`Dockerfile` はビルド時に `./.env` をコンテナへコピーするため、
**Docker ビルド前にも `.env` を用意**しておく必要があります。

## 開発・ビルド

```sh
corepack enable                         # Yarn 4 を有効化
yarn install --immutable                # 依存をインストール

# trvis-api（生成クライアント）を先にビルド
( cd packages/trvis-api && npm install && npm run build )

yarn dev        # 開発サーバ（Vite）
yarn build      # tsc && vite build（本番ビルド → dist/）
yarn lint       # ESLint（--max-warnings 0）
yarn i18n       # i18next-parser で翻訳キー抽出
yarn preview    # ビルド成果物のプレビュー
```

`vite.config.ts` は `build.commonjsOptions.include` に `packages/trvis-api` を含めて
リンクされた生成パッケージを取り込みます。また本番ビルドでは `console` / `debugger` を除去します。
この設定は意図的なので変更しないでください。

## Docker

`Dockerfile` は 2 ステージ構成です。

1. `node:24.15.0-alpine`: `corepack enable && yarn install --frozen-lockfile` → `yarn build`
2. `httpd:2.4.58-alpine`: `public/` と `dist/` を Apache httpd で配信

Docker Compose で起動すると、proxy（nginx）経由で `http://localhost/` から配信されます。
詳細はリポジトリルートの [`README.md`](../README.md) を参照してください。

## CI

`.github/workflows/frontend-tests.yml` がマージゲートです。
`trvis-api` の生成クライアントをビルド → `tsc`（型検査）→ `vite build` を実行します。
テストランナーは無く、**型検査が正しさの基準**です。型エラーはマージをブロックします。
