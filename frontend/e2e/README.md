# E2E (Playwright)

全画面のボタン／ボタン的コントロールを「効果まで」検証する E2E スイート。
クリックで終わらず、作成/編集/削除は **リロード後に API 由来の描画で永続を確認**する
（メモリ上サンプルではなく実 API に反映されたかを見る）。

## 前提・起動

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

## 前提（重要・未決のオーナー判断）

作成系 API を通すには、**現状このマシン限定**の回避策が要る（追跡対象外のため
クリーンチェックアウト＋新規 DB ボリュームでは createProject が 500 になり全滅する）:

- MySQL `sql_mode` から `ONLY_FULL_GROUP_BY` を除外（実行中コンテナに `SET PERSIST`）。
- `config.inc.php` で `ATTR_EMULATE_PREPARES => true`（同名プレースホルダ再利用対策）。
- レート制限の緩和（同 `config.inc.php`）。

これらを追跡ファイルにコミットするか、クエリを正規修正するかは未決。詳細・推奨は
[`UNIMPLEMENTED.md` §0](../../UNIMPLEMENTED.md) の「最重要・オーナー判断」を参照。

## 注意

- 各テストは `uniqueName()` で自己シードし、**自分が作った実体のみ**を検証する
  （件数や他テストの実体には依存しない）。バックエンド DB は共有で、`workers:1` 直列。
- カバレッジは **部分的**（4/7 画面、うち work/timetable は一部赤）。ColorManager・
  StopPatternWizard 内部・AppShell（テーマ/言語）・認証ダイアログ群は**未走査**。
- 既知の未完: `work` / `timetable` の一部はセレクタ/リロード後ナビのスペック債務で赤
  （製品バグではない。work 作成の永続は DB で確認済み）。詳細と製品側の所見は
  リポジトリルート [`UNIMPLEMENTED.md`](../../UNIMPLEMENTED.md) を参照。
