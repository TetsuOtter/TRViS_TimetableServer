# TRViS Timetable Server — Backend

[TRViS](https://github.com/TetsuOtter/TRViS) 用の時刻表データを保存・配信する REST API です。
PHP 8.2 / [Slim 4](https://www.slimframework.com/) + [PHP-DI](https://php-di.org/) で実装しています。

API 仕様の source-of-truth は [`../api_defs/`](../api_defs/README.md) の OpenAPI 定義です。
このディレクトリのコードは、その定義から OpenAPI Generator（`php-slim4`）で生成した雛形に、
手書きの実装を肉付けする構成になっています。

## ディレクトリ構成

| パス                  | 内容                                                                              | 生成 / 手書き |
| --------------------- | --------------------------------------------------------------------------------- | ------------- |
| `lib/`                | OpenAPI Generator が生成するモデル・抽象クラス・ルーティング登録                   | 生成（原則編集しない） |
| `src/trvis_backend/`  | 手書きの実装本体                                                                  | 手書き        |
| `public/index.php`    | エントリポイント（コンテナ構築・ミドルウェア・ルーティング起動）                  | 生成（ignore 済み） |
| `config/`             | 環境別設定（後述）                                                                | 手書き        |
| `tests/`              | PHPUnit テスト（`Api/` `Model/` `Integration/`）                                  | 手書き        |
| `scripts/`            | 補助スクリプト                                                                    | 手書き        |
| `logs/`               | アプリ／Apache ログ出力先                                                          | —             |
| `Dockerfile`          | `php:8.2-apache` ベース。ドキュメントルートは `public/`                            | 手書き        |

オートロード（`composer.json`）は PSR-4 で `dev_t0r\` を `lib/` と `src/` の **両方** に
マッピングしています。生成コードと手書きコードが同じ名前空間ツリーに同居し、
ルーティングはクラス名規約（`XxxApi` → `dev_t0r\trvis_backend\api\XxxApi`）で解決されます。

### 実装のレイヤ（`src/trvis_backend/`）

```
api/      … HTTP ハンドラ。リクエスト検証 → service 呼び出し → レスポンス整形
service/  … ユースケース／権限判定。トランザクション境界
repo/     … SQL（PDO）。1 テーブル ≒ 1 リポジトリ
validator/… 型・値バリデーションルール群
model/    … 列挙型・日時など手書きモデル補助
auth/     … MyAuthMiddleware（認証トークン検証）
```

## 認証・認可モデル

**「リクエストをゲートする」のではなく「権限でフィルタする」方式**です。

- `MyAuthMiddleware` は `Authorization: Bearer <token>` が **付いていれば** Firebase で検証する。
  - 不正なトークン → `400`
  - 期限切れトークン → `401`
- トークンが **無ければそのまま通し**、ハンドラには匿名 UID（`Constants::UID_ANONYMOUS`）が渡る。
- 参照系（`GET`）は匿名でも通るが、返るのは **その利用者に見える範囲だけ**。
  匿名の権限で見える行がゼロなら `200 []`（＝仕様どおりの正常応答であり、脆弱性ではない）。
- 作成・更新・削除は匿名を拒否（`401 "Token was not set"`）。

権限の最上位単位は **Project** です（旧 WorkGroup 単位の権限を置き換え）。
詳細は [`../api_defs/`](../api_defs/README.md) の OpenAPI 定義を参照してください。

## 設定（`config/`）

環境（`APP_ENV` で `dev` / `prod` を切替、未指定なら `prod`）ごとに 2〜3 ファイルの重ね合わせ:

| ファイル                                | VCS        | 役割                                                                 |
| --------------------------------------- | ---------- | -------------------------------------------------------------------- |
| `config/$env/default.inc.php`           | 追跡       | 機密を含まない既定値。**常に読み込まれる**                            |
| `config/$env/config.inc.php`            | gitignore  | 開発者ごとのローカル上書き。**存在すれば**読み込まれる（秘密はここ）  |
| `config/prod/config.docker.inc.php`     | 追跡       | Docker 用テンプレート。名前では読まれない。コピー元として使う        |

Docker Compose で起動する場合は、テンプレートを `config.inc.php` にコピーします:

```sh
cp config/prod/config.docker.inc.php config/prod/config.inc.php
```

`config.docker.inc.php` は DB を `webmon-db`、Firebase Auth Emulator を
`webmon-firebase:9099` に向けた固定値を持ちます（同一 compose ネットワーク前提）。
`config.inc.php` は Git 管理外なので、機密値はここに書きます。

## 起動方法

### ホスト PHP の組み込みサーバ（開発時）

リポジトリルートの補助スクリプトを使います:

```sh
./_php.sh        # backend/ で php -S localhost:8888 -t public
```

→ `http://localhost:8888/api/v1/`

### Docker Compose

リポジトリルートの [`README.md`](../README.md) のクイックスタート参照。
`php` コンテナは proxy 経由のほか `http://localhost:8080/api/v1/` で直接叩けます。

## テスト

PHPUnit 11（`vendor/` に同梱）。`backend/` 直下で実行します。

```sh
composer test          # 全テスト
composer test-apis     # phpunit --testsuite Apis（tests/Api）
composer test-models   # phpunit --testsuite Models（tests/Model）
composer phpcs         # コーディング規約チェック
composer phplint       # 構文チェック（vendor を除外）
```

`tests/Integration/` および一部の API テストは実 DB に接続します。
DB が無い環境では skip ガードされます。CI（`.github/workflows/backend-tests.yml`）は
PHP 8.2 + MySQL 8.0 で実 DB 統合テストまで含めて実行します。

## コード生成との関係（重要）

`lib/` や `public/index.php` は OpenAPI Generator の出力です。
**手で編集してはいけません**（次回生成で上書きされます）。

API を変えるときは `../api_defs/` の OpenAPI 定義を編集し、再生成します:

```sh
cd ../api_defs
./bundle.sh        # 仕様をバンドル（openapi.bundle.yml を生成）
./gen_php.sh       # backend/ を再生成
```

生成器が**上書きしてはいけない**ファイルは `.openapi-generator-ignore` に列挙しています
（`composer.json`、`config/`、`public/index.php`、DI/ミドルウェア登録、本 `README.md` など）。
手書きで残したいファイルを増やすときは、必ずこのファイルにも追記してください。
