# E2E (Playwright)

全画面のボタン／ボタン的コントロールを「効果まで」検証する E2E スイート。
クリックで終わらず、作成/編集/削除は **リロード後に API 由来の描画で永続を確認**する
（メモリ上サンプルではなく実 API に反映されたかを見る）。

## 前提・起動

### 推奨: クリーンな E2E スタック（CI と同一・再現可能）

`docker-compose.e2e.yaml` が API + MySQL + Firebase Auth Emulator を**クリーンな
状態から・本番忠実な設定で**立ち上げる。手作業のコンテナ設定や gitignore な
`config.inc.php` 編集は不要:

- `backend/config/prod/config.e2e.inc.php`（追跡）を compose が `config.inc.php`
  として bind-mount。**PDO は本番忠実**（`config.docker.inc.php` を継承＝
  `EMULATE_PREPARES=false`）。E2E 専用の上書きは**レート制限緩和とログ出力先のみ**。
- mysql の `sql_mode` は MySQL 8 既定（`ONLY_FULL_GROUP_BY` を含む）のまま＝本番忠実。

> かつて clean DB では createProject が 500 で全滅する旨を記していたが、`§0-2`
> (`ONLY_FULL_GROUP_BY`)/`§0-3`(`EMULATE_PREPARES`) は code-first 化で修正済み
> （結合テスト 216 件＋クリーン E2E で確認）。本スタックは本番忠実なので回帰ガードを
> 兼ねる。詳細は [`UNIMPLEMENTED.md` §0](../../UNIMPLEMENTED.md)。

```sh
# 1) E2E バックエンドを起動（毎回まっさら。開発 DB ボリュームには触れない）
docker compose -f docker-compose.e2e.yaml up -d --build --wait

# 2) E2E 実行（vite dev は playwright が自動起動/再利用）
cd frontend
yarn install --immutable
yarn playwright test e2e/smoke.spec.ts e2e/invite-keys.spec.ts   # CI と同じ緑セット
yarn playwright test                                              # 全件（既知の赤あり, 下記）

# 3) 後始末
docker compose -f docker-compose.e2e.yaml down -v
```

> 注意: 開発スタック (`docker compose up`) と E2E スタックは **8080/9099/4000 を
> 取り合う**。E2E スタックを使う前に開発スタックを `down` すること。

CI (`.github/workflows/e2e-tests.yml`) はこのスタックで `smoke` + `invite-keys` を
回す（クリーン DB で緑が実測済みのセット）。

### 旧来: 共有開発スタック（手作業の回避策が必要）

```sh
# 1) バックエンドスタック（API + MySQL + Firebase Auth Emulator）
docker compose up -d php mysql firebase

# 2) 初回のみ: 開発設定を実体化（README ルート手順と同じ）
cp backend/config/prod/config.docker.inc.php backend/config/prod/config.inc.php

# 3) E2E 実行（vite dev は playwright が自動起動/再利用）
cd frontend
yarn playwright test                 # 全件
yarn playwright test e2e/projects.spec.ts   # 個別
yarn playwright test --ui            # UI モード
```

- vite dev(`:5173`) は `/api` を `:8080`(php) へプロキシ。`localhost` 判定で
  Firebase は Auth Emulator(`:9099`) に接続。
- `global-setup.ts` が E2E ユーザ（`e2e-user@example.com` / `Passw0rd!` ほか）を
  emulator に冪等プロビジョニング。シード `0000` はフォームのパスワード規則を満たさず
  UI ログインできないため、規則を満たすユーザを別途用意している。

## 構成

- `fixtures.ts` … `test`(`appPage`=ログイン済みページ) と `login()`。
- `helpers.ts` … プロジェクト/WG/ワーク作成・画面遷移・`uniqueName()` など共有ヘルパー。
- `credentials.ts` / `global-setup.ts` … 認証ユーザ。
- `AGENT_BRIEF.md` … スペック作成方針（効果アサート徹底、サンプル `data` 罠 等）。
- `*.spec.ts` … 画面別スペック。

## E2E 専用の上書き（本番忠実・回避策ではない）

`config.e2e.inc.php` が本番設定（`config.docker.inc.php`）から変えるのは**テスト都合の
2 点だけ**で、クエリ挙動は本番忠実:

- **レート制限の緩和** … スイートは 1 IP（localhost）から多数リクエストを撃つため、
  60/min 既定や `useInviteKey` 10/min に当たって 429 が出る（SUT 無関係のフレーク）。
- **ログ出力先を `/tmp` に** … CI は `/var/log/apache2` を host bind しないため、
  www-data がログを書ける world-writable な場所へ。

> ✅ **旧 §0-2/§0-3 は解決済み**: 以前ここには「`ONLY_FULL_GROUP_BY` 除外＋
> `EMULATE_PREPARES=true` が無いと createProject が 500」と書いていたが、これらは
> code-first 化で**修正済み**（該当サブクエリは GROUP BY 済み・同名プレースホルダ
> 解消済み）。本スタックは `EMULATE_PREPARES=false` ＋ `ONLY_FULL_GROUP_BY` ON の
> **本番忠実**で回り、結合テスト 216 件＋クリーン E2E 7/7 で確認済み。回帰ガードを
> 兼ねる。詳細は [`UNIMPLEMENTED.md` §0](../../UNIMPLEMENTED.md)。

## 注意

- 各テストは `uniqueName()` で自己シードし、**自分が作った実体のみ**を検証する
  （件数や他テストの実体には依存しない）。バックエンド DB は共有で、`workers:1` 直列。
- カバレッジは **部分的**（4/7 画面、うち work/timetable は一部赤）。ColorManager・
  StopPatternWizard 内部・AppShell（テーマ/言語）・認証ダイアログ群は**未走査**。
- 既知の未完: `work` / `timetable` の一部はセレクタ/リロード後ナビのスペック債務で赤
  （製品バグではない。work 作成の永続は DB で確認済み）。詳細と製品側の所見は
  リポジトリルート [`UNIMPLEMENTED.md`](../../UNIMPLEMENTED.md) を参照。
