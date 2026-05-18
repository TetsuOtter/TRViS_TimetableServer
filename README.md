# TRViS Timetable Server

[TRViS](https://github.com/TetsuOtter/TRViS) 用の時刻表データを管理する Web アプリケーション一式です。
時刻表データを編集する Web フロントエンドと、それを保存・配信する REST API バックエンドを、
Docker Compose で一括起動できる形にまとめています。

## 構成

```text
ブラウザ ──▶ proxy (nginx :80)
                ├─ /      ──▶ front  (React + Vite を Apache httpd で配信)
                └─ /api/  ──▶ php    (PHP 8.2 / Slim 4 の REST API)
                                       ├─ mysql     (MySQL 8.0)
                                       └─ firebase  (Firebase Auth Emulator)
```

| サービス     | コンテナ名           | 役割                                   | 公開ポート          |
| ------------ | -------------------- | -------------------------------------- | ------------------- |
| `proxy`      | `webmon-proxy`       | nginx リバースプロキシ（入口）         | `80`                |
| `front`      | `webmon-front`       | フロントエンド（ビルド済み静的配信）   | （proxy 経由）      |
| `php`        | `webmon-php`         | バックエンド API                       | `8080`（直接アクセス用） |
| `mysql`      | `webmon-db`          | データベース                           | （内部ネットワーク） |
| `firebase`   | `webmon-firebase`    | 認証エミュレータ                       | `9099` / `4000`（UI） |
| `phpmyadmin` | `webmon-phpmyadmin`  | DB 管理 UI                             | `81`                |

通常のアクセスはすべて `proxy`（`http://localhost/`）経由です。
`php` の `8080` はプロキシを介さず API を直接叩きたいときのための補助ポートです。

## ディレクトリ構成

| パス          | 内容                                                                 |
| ------------- | -------------------------------------------------------------------- |
| `api_defs/`   | OpenAPI 仕様（API 契約の source-of-truth）。ここからコードを生成する  |
| `backend/`    | PHP / Slim 4 製の REST API。`api_defs` から雛形を生成し実装を肉付け   |
| `frontend/`   | React + Vite + TypeScript の時刻表エディタ                           |
| `mysql/`      | MySQL の初期スキーマ・設定・ログ                                      |
| `firebase/`   | Firebase Auth Emulator のイメージと設定                              |
| `proxy/`      | nginx リバースプロキシの設定                                          |
| `backend-logs/` | `php` コンテナの Apache ログのバインド先                            |

各ディレクトリの詳細は、それぞれの `README.md` を参照してください。

## クイックスタート（Docker Compose）

1. 環境ファイルを用意する

   ```sh
   # MySQL の認証情報
   cp mysql/.env.sample mysql/.env

   # フロントエンドの環境変数（Firebase 設定等）
   cp frontend/.env.sample frontend/.env

   # バックエンドの Docker 用設定（DB / Firebase Emulator のホスト名を含む）
   cp backend/config/prod/config.docker.inc.php backend/config/prod/config.inc.php
   ```

   `backend/config/prod/config.inc.php` は Git 管理外（開発者ごとのローカル設定）です。
   詳細は [`backend/README.md`](backend/README.md) を参照してください。

2. スタックを起動する

   ```sh
   docker compose up -d --build
   ```

3. アクセスする

   - アプリ本体: <http://localhost/>
   - API（直接）: <http://localhost:8080/api/v1/>
   - phpMyAdmin: <http://localhost:81/>
   - Firebase Emulator UI: <http://localhost:4000/>

特定のサービスだけ作り直したいときは `./_restart.sh <service名>`（例: `./_restart.sh php`）が使えます。

## 開発用補助スクリプト

| スクリプト       | 用途                                                                 |
| ---------------- | -------------------------------------------------------------------- |
| `_php.sh`        | `backend/` をホスト PHP の組み込みサーバ（`localhost:8888`）で起動    |
| `_mysql.sh`      | `webmon-db` コンテナ内の `mysql` クライアントへ接続                   |
| `_restart.sh <s>` | Compose サービス `<s>` を停止・再ビルド・再起動                      |

## API

- ベース URL: `/api/v1`（本番は `https://trvis.t0r.dev/api/v1`）
- 認証: Firebase が発行する ID トークンを `Authorization: Bearer <token>` で送る
- 認証は「リクエストをゲートする」のではなく「権限でフィルタする」方式です。
  未認証でも参照系は通り、その利用者が見える範囲（=匿名なら空）だけが返ります。
  作成・更新・削除はトークン必須です。詳細は [`backend/README.md`](backend/README.md)。

API 仕様は [`api_defs/`](api_defs/README.md) の OpenAPI 定義が正です。

## CI

GitHub Actions でマージゲートを構成しています。

- `.github/workflows/backend-tests.yml` — PHP 8.2 + MySQL 8.0 で PHPUnit（実 DB 統合テスト含む）
- `.github/workflows/frontend-tests.yml` — `trvis-api` 生成クライアントのビルド → `tsc` → `vite build`

## ライセンス

[MIT License](LICENSE)
