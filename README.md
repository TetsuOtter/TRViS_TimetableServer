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
| `backend/`    | PHP / Slim 4 製の REST API。swagger-php が OpenAPI の source-of-truth (`backend/openapi.json` を生成) |
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

API 仕様は **コードファースト**です。`backend/src/` の PHP `#[OA\*]` 属性から swagger-php が `backend/openapi.json` を生成します。フロントエンドの型は `backend/gen_ts.sh` を実行すると `backend/openapi.json` から `frontend/src/api/schema.ts` が再生成されます（生成物ですがコミット対象）。

## テスト

バックエンドの PHPUnit には実 DB に接続する統合テストが含まれます（`tests/Api/` は実際の MySQL に対して SQL を実行します）。本番/開発スタックの `webmon-db` には一切触れない、使い捨ての専用 compose を用意しています。

```sh
# 全テストを実行（test-runner の終了コードがそのまま伝播するのでゲートに使える）
docker compose -f docker-compose.test.yaml up --build \
  --abort-on-container-exit --exit-code-from test-runner

# 後始末（使い捨て DB と dev 依存ボリュームを破棄）
docker compose -f docker-compose.test.yaml down -v

# 一部のテストだけ実行（引数はそのまま phpunit に渡る）
docker compose -f docker-compose.test.yaml run --rm test-runner \
  vendor/bin/phpunit --filter testTombstone
```

- CI（`backend-tests.yml`）と同じ手順で動きます: MySQL 8.0 を `mysql/init_sql` で seed → スキーマ投入のスモークチェック → `composer test`（**PHP 8.2** = 正準ランタイム）。
- 専用のプロジェクト名・ネットワーク・コンテナ名を使い、`test-db` のデータは tmpfs（永続ボリュームなし）なので本番の `webmon-db` と混ざりません。dev 依存もランナー専用ボリュームに入り、ホストの `vendor/` を上書きしません。
- スモークチェックがスキーマ未投入を検出して必ず落とすため、「DB 未到達で全テストが静かにスキップ → 嘘の緑」を防ぎます。

> Docker を使わずローカルの PHP で回す場合は、別途 MySQL を用意して `mysql/init_sql/0_create_db.sql` を `test` データベースへ投入し、`TEST_DB_DSN` / `TEST_DB_USER` / `TEST_DB_PASS` を設定したうえで `backend/` で `composer test` を実行します（DB 未接続時は統合テストがスキップされます）。具体的な接続値は `.github/workflows/backend-tests.yml` を参照してください。

## CI

GitHub Actions でマージゲートを構成しています。

- `.github/workflows/backend-tests.yml` — PHP 8.2 + MySQL 8.0 で PHPUnit（実 DB 統合テスト含む）
- `.github/workflows/frontend-tests.yml` — `yarn install --immutable` → `tsc` → `vite build`

## ライセンス

[MIT License](LICENSE)
