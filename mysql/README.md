# TRViS Timetable Server — Database（MySQL）

[TRViS](https://github.com/TetsuOtter/TRViS) 時刻表データを格納する MySQL 8.0 です。
Docker Compose では `mysql`（コンテナ名 `webmon-db`）として起動し、
バックエンド（`php`）からのみ内部ネットワーク経由でアクセスされます。

## ディレクトリ構成

| パス             | 内容                                                                       |
| ---------------- | -------------------------------------------------------------------------- |
| `init_sql/`      | 初回起動時に実行される初期化 SQL                                           |
| `conf.d/my.cnf`  | MySQL 設定（`utf8mb4`、スロークエリログ等）                                |
| `logs/`          | エラーログ・スロークエリログのバインド先（ログ本体は Git 管理外）          |
| `.env.sample`    | 認証情報のテンプレート                                                      |
| `.env`           | 実際の認証情報（`.env.sample` からコピー。**Git 管理外**）                 |

## スキーマ（正は SQL ファイル）

**スキーマの source-of-truth は [`init_sql/0_create_db.sql`](init_sql/0_create_db.sql)** です。
このファイルに定義された 17 テーブルが初回起動時に作成されます:

```text
projects                  … 権限の最上位単位（旧 WorkGroup 権限を置き換える privilege root）
work_groups               … projects 配下（projects_id は NOT NULL）
project_lines             … 路線（後述の予約語回避）
project_stations          … Project 共通の駅
stations_on_line          … 路線上の駅
stop_patterns             … 停車パターン
stop_pattern_rows         … 停車パターンの行
works / trains / timetable_rows / stations / station_tracks / colors
api_keys / invite_keys
work_groups_privileges / projects_privileges
```

FK のルート連鎖は `projects` を起点に
`work_groups` / `project_lines` / `project_stations` / `stop_patterns` へ伸びます。

### 設計上の注意

- **`lines` は MySQL の予約語**のため、DB 上のテーブル名・カラム名は
  `project_lines` / `project_lines_id` を使います。OpenAPI 定義では `Line` / `lines_id`
  のままで、バックエンドの repo 層でエイリアスして吸収しています。
- UUID は `BINARY(16)` で格納します。
- 削除は **論理削除**（`deleted_at` を設定）が基本です。

旧版で手書きしていたデータモデル仕様（旧 `work_groups` 権限ベース）は廃止しました。
現行スキーマは必ず SQL ファイルを参照してください。
API としての見え方は [`../api_defs/README.md`](../api_defs/README.md) を参照。

## 認証情報

```sh
cp .env.sample .env
```

| 変数                  | 既定値 | 用途                       |
| --------------------- | ------ | -------------------------- |
| `MYSQL_USER`          | `test` | アプリ用ユーザ             |
| `MYSQL_PASSWORD`      | `test` | 同パスワード               |
| `MYSQL_DATABASE`      | `test` | データベース名             |
| `MYSQL_ROOT_PASSWORD` | `test` | root パスワード            |
| `PMA_PASSWORD`        | `test` | phpMyAdmin 自動ログイン用（root と同値） |

バックエンドの接続先（Docker 構成）は
`backend/config/prod/config.docker.inc.php` の DSN（`host=webmon-db;dbname=test`）と
一致させます。詳細は [`../backend/README.md`](../backend/README.md) を参照してください。

## 接続（開発時）

リポジトリルートの補助スクリプトでコンテナ内 `mysql` クライアントへ接続できます:

```sh
./_mysql.sh
```

DB 管理 UI が必要なときは phpMyAdmin（`http://localhost:81/`）も利用できます。
