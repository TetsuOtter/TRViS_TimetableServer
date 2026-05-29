# migrations/ — 既存 DB へ手動適用するスキーマ差分

## 概要

| ディレクトリ  | 適用タイミング                                           |
| ------------- | -------------------------------------------------------- |
| `init_sql/`   | 初回起動時のみ（コンテナ初期化 = 新規 DB 構築時に自動実行） |
| `migrations/` | 既存 DB へ手動で適用するスキーマ差分（マイグレーションツール無し） |

`init_sql/0_create_db.sql` は Docker Compose が **初めて起動するとき** にのみ自動実行されます。
すでに稼働中の DB には反映されないため、このディレクトリのファイルを手動で適用してください。

## ファイルの命名規則と適用順

ファイル名は `NNNN_<説明>.sql`（4 桁ゼロ埋め数値プレフィックス）です。
**数値の昇順** に適用してください（例: `0001_...` → `0002_...` → …）。

## 適用方法

```sh
mysql -h <host> -P <port> -u <user> -p <database> \
  < mysql/migrations/0001_add_privileges_covering_index.sql
```

Docker Compose 環境では補助スクリプト (`./_mysql.sh`) でコンテナ内に入り、
そこからパイプ入力するか `SOURCE` コマンドで適用することもできます。

## 適用確認

インデックスが追加されたかどうかは `SHOW INDEX` で確認できます:

```sql
SHOW INDEX FROM projects_privileges;
SHOW INDEX FROM work_groups_privileges;
```

`Key_name` 列に目的のインデックス名が表示されれば適用済みです。

## 冪等性についての注意

MySQL 8.0 は `ADD INDEX IF NOT EXISTS` をサポートしていません。
**すでに適用済みの DB に再実行すると**、以下のエラーで安全に失敗します:

```
ERROR 1061 (42000): Duplicate key name 'priv_lookup_covering_idx'
```

これは「すでに適用済み」を示す安全なシグナルです。エラーが出た場合は
`SHOW INDEX FROM <table>` でインデックスの存在を確認してください。
