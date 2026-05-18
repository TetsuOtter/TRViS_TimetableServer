# TRViS Timetable Server — API 定義（OpenAPI）

[TRViS](https://github.com/TetsuOtter/TRViS) 時刻表管理 API の **仕様の source-of-truth** です。
ここの OpenAPI 定義からバックエンド（PHP/Slim 4）とフロントエンドの API クライアントを
コード生成します。API を変えるときは、まずここを編集します。

## 構想

面倒なので、テーブルごとに API を作る。
配列形式で入力させることで、複数のデータを一度に登録できるようにする。

この方針は現在も有効です。各エンティティ（Project / WorkGroup / Line / Station /
StopPattern / Work / Train / TimetableRow など）に対し CRUD エンドポイントを用意し、
作成・更新系は配列を受け取って一括処理できる形にしています。

## ファイル構成

| パス                  | 内容                                                                        |
| --------------------- | --------------------------------------------------------------------------- |
| `api_root.yaml`       | ルート定義（info / servers / tags、各 path・object への参照）               |
| `paths/`              | エンドポイント定義（エンティティごとにディレクトリ）                        |
| `objects/`            | スキーマ定義（`Project.yaml`, `Line.yaml`, `StopPattern.yaml` …）           |
| `parameters/`         | 共通パラメータ定義                                                          |
| `response_objs/`      | 共通レスポンス定義                                                          |
| `openapi.bundle.yml`  | 上記を 1 ファイルに束ねた生成物（**Git 管理外**。`bundle.sh` で再生成）     |
| `php-slim4.config.yaml` | バックエンド生成設定（`php-slim4` ジェネレータ）                          |
| `ts-fetch.config.yaml`  | フロントエンド生成設定（`typescript-fetch` ジェネレータ）                 |

サーバ定義は 3 つ: ローカル Docker（`localhost:8080/api/v1`）、
ローカルホスト実行（`localhost:8888/api/v1`）、本番（`https://trvis.t0r.dev/api/v1`）。

## コード生成ワークフロー

分割した定義を 1 ファイルにバンドルしてから、各言語のクライアント／サーバを生成します。

```sh
./bundle.sh        # redocly bundle api_root.yaml -o openapi.bundle.yml
./gen_php.sh       # → ../backend     (php-slim4)
./gen_ts.sh        # → ../frontend/packages/trvis-api (typescript-fetch)
```

- `bundle.sh` には [Redocly CLI](https://redocly.com/docs/cli/)、
  `gen_*.sh` には [OpenAPI Generator CLI](https://openapi-generator.tech/) が必要です。
- 生成器のバージョンはリポジトリルートの `openapitools.json` で **7.2.0** に固定しています
  （生成済みコード側の `.openapi-generator/VERSION` も 7.2.0）。
  こちらが正です。`api_defs/openapitools.json` には別の値が書かれていますが、
  ルートの設定が優先されます（この不整合の修正はこのドキュメントの対象外）。

### 生成先で手書きを残すには

生成は出力先ディレクトリを上書きします。手書きで残したいファイルは、各生成先の
`.openapi-generator-ignore` に列挙してください（バックエンドの `src/`・`config/`・
`composer.json`・`README.md` などは ignore 済み）。詳細は
[`../backend/README.md`](../backend/README.md) を参照。

データモデル（テーブル）側の詳細は [`../mysql/README.md`](../mysql/README.md)、
全体構成はリポジトリルートの [`README.md`](../README.md) を参照してください。
