# 未実装・誤配線・サーバ起因の機能一覧（E2E棚卸し）

`feature/all-button-e2e` で Playwright E2E を整備し、各画面のボタン／ボタン的な
クリック対象を「効果まで」検証した結果のリスト。分類:

- **UNIMPLEMENTED** … ハンドラ無し／no-op。何も起きない。
- **WRONG-SOURCE** … 動くが対象データが誤り（API ではなくメモリ上のサンプル
  `data` を読み書きする等）。見た目は反応するが実データに反映されない。
- **BACKEND-500** … フロントは正しく API を叩くがサーバが 500 を返す（実バグ）。
- **ENV/CONFIG** … docker/emulator 開発環境の設定不備でミューテーションが全滅して
  いた基盤問題（E2E の前提として要対応）。

各項目の末尾に **[状態]** を付す: `修正済` / `要対応` / `要設計判断`。

E2E ハーネスと実行手順は [`frontend/e2e/README.md`](frontend/e2e/README.md) 参照
（`docker compose up -d php mysql firebase` → `cd frontend && yarn playwright test`）。

## 実装・検証サマリ（このブランチで対応済み）

| 項目 | 種別 | 状態 | 検証 |
|---|---|---|---|
| 失効チェックが emulator で 401（§0-1） | ENV | **修正済(追跡)** `MyAuthMiddleware` | curl/UI で create 通過 |
| 停車パターン作成 500（§1-1） | BACKEND | **修正済(追跡)** `StopPatternsService` | curl 201 / UI / `StopPatternApiTest` 10件緑 |
| 施行日付き Work 作成 500（§1-2） | BACKEND | **修正済(追跡)** `WorksService` | curl 201 / `WorkApiTest` 緑 |
| ApplyPattern の路線がサンプル（§2-4） | WRONG-SRC | **修正済(追跡)** `App.tsx` を API データへ | work.spec の該当テストが緑転 |
| `ONLY_FULL_GROUP_BY`（§0-2） | ENV | **解決済(code-first で修正済を確認)** | 結合216件緑(MySQL8 既定 sql_mode) + クリーン E2E 緑 |
| `EMULATE_PREPARES=false`（§0-3） | ENV | **解決済(code-first で修正済を確認)** | 結合216件緑(`EMULATE_PREPARES=false`) + クリーン E2E 緑 |
| エクスポート/インポート（§2-1〜2-3） | WRONG-SRC | **未対応(要設計/機能規模)** | E2E で死を確認 |
| 時刻表 行 model-only 永続/ドラッグ（§3） | UNIMPL等 | **要設計判断** | ソース確認 |

> **【解決済】 §0-2/§0-3 — 本番クエリは壊れていなかった（code-first 化で修正済）**:
> 本 md 執筆時（`feature/all-button-e2e`）の暫定回避（`SET PERSIST` / gitignore
> `config.inc.php`）は不要になった。code-first バックエンドでは該当サブクエリは
> 既に `GROUP BY` 済み（例 `ProjectsRepo::selectProjectOne` / `getSelectPageQuery`）で、
> 同名プレースホルダ再利用も解消済み。検証:
>   - 結合テスト **216件 緑**。harness は **本番忠実**＝`EMULATE_PREPARES=false`
>     (`tests/Integration/IntegrationTestCase.php:57`) かつ MySQL 8 既定
>     `sql_mode`（`ONLY_FULL_GROUP_BY` を含むことを `@@GLOBAL.sql_mode` で確認）。
>     privilege サブクエリを持つ全 repo（Project/Line/Station/StationTrack/
>     StationOnLine/StopPattern/StopPatternRow/Work/WorkGroup）の `*ApiTest` が
>     `createProject`/`selectProjectOne`/`selectProjectPage` 等を網羅。
>   - **クリーン E2E 緑**: `docker-compose.e2e.yaml` を本番忠実設定
>     （`EMULATE_PREPARES=false` ＋ `ONLY_FULL_GROUP_BY` ON）で立て、まっさらな DB で
>     `smoke`+`invite-keys` 7/7 緑（createProject がフルスタックで通過）。
> したがって回避策の追跡コミットもクエリ書き換えも不要。E2E 設定は本番忠実のまま
> 回し、§0-2/§0-3 の回帰ガードを兼ねる（E2E 専用の上書きはレート制限緩和と
> ログ出力先のみ）。下記 §0 の項目2・3 の「[要対応]」も同様に解決済み。

## E2E スイート状態（`frontend/e2e/`）

- `smoke` / `projects` / `lines`: **安定**（効果＋リロード永続を検証）。`projects` の
  3 件赤は export/import の WRONG-SOURCE を意図的にエンコード（＝正しく死を検出）。
  `lines` は停車パターン作成が修正後グリーン化（残 1 件はカードセレクタ厳格性の軽微債務）。
- `work`: 13/22。緑の主力に加え、§2-4 修正で「サンプル路線を出さない」テストが緑転。
  残る赤はコンテキストメニュー/⚙ダイアログ/サイドバーのセレクタ債務と、意図的赤の
  export(WRONG-SOURCE)。**製品バグではないことを実測で確定**: ワーク作成は
  POST→即時 GET でサイドバーに出現し、DB(`works` テーブル)にも残る（診断スペックで
  確認済み）。`work.spec` の「リロード後永続」赤は、リロード後の再ナビが works フェッチを
  安定発火させない**スペック側の債務**で、作成・永続そのものは正常。
  併せて `createWorkGroup` ヘルパーの strict-mode 違反（「新規WG」2要素マッチ）を
  `.first()` で修正済み。
- `timetable`: セットアップ連鎖（Work 作成→列車→グリッド到達）にスペック側の未完があり
  不安定。**担当エージェントがセッション上限で中断**したため要仕上げ。本 md の §3 所見は
  ソース（`modelRowToEntityDraft`）から確定済みで、スペック赤に依存しない。

---

## 0. ENV/CONFIG — 開発スタックがミューテーション全滅だった基盤問題

E2E を回す前提として、docker/emulator 構成では **あらゆる作成系 API が失敗**して
いた。GET（匿名許容＝privilege フィルタ）は 200 を返すため UI 上は「読み込めるのに
保存できない」状態だった。1 エンドポイント（createProject）で 3 つの独立した失敗が
連鎖していた。

1. **Firebase 失効チェックが emulator で必ず失敗**
   `MyAuthMiddleware` は `createProject/deleteProject/deleteWorkGroup/createInviteKey`
   で `verifyIdToken($token, checkIfRevoked=true)` を呼ぶ。失効チェックは SA 鍵で
   Firebase に署名リクエストを投げる実装で、emulator 用 SA は意図的なプレースホルダ
   鍵のため OpenSSL 署名段階で必ず例外 → 401「Invalid authentication token」。
   - **[修正済]** `backend/src/trvis_backend/auth/MyAuthMiddleware.php`:
     `FIREBASE_AUTH_EMULATOR_HOST` 設定時のみ失効チェックを無効化（本番は不変）。
     これは追跡ファイルへのコミット対象（emulator 限定・本番安全）。

2. **`ONLY_FULL_GROUP_BY` で privilege サブクエリが 1140 エラー（旧症状）**
   `MAX(privilege_type)` を非集約列と併用するサブクエリ。MySQL 8 既定の
   `ONLY_FULL_GROUP_BY` だと `SQLSTATE[42000] 1140` → 500 になり得た。
   - **[解決済]** code-first バックエンドでは該当サブクエリは既に `GROUP BY` 済み
     （例 `ProjectsRepo::selectProjectOne` L74 / `getSelectPageQuery` L163）。
     privilege サブクエリを持つ全 repo の `*ApiTest` を含む結合テスト 216 件が、
     MySQL 8 既定 `sql_mode`（`@@GLOBAL.sql_mode` に `ONLY_FULL_GROUP_BY` 在を確認）
     下で緑。`SET PERSIST` も `mysql/conf.d` 変更も不要。

3. **`ATTR_EMULATE_PREPARES=false` で同名プレースホルダ再利用が HY093（旧症状）**
   native prepare は 1 クエリ内の同名プレースホルダ再利用を許さず
   `SQLSTATE[HY093]` になり得た。
   - **[解決済]** 現コードでは再利用を解消済み（例 `selectProjectOne` は
     `:projects_id` を 1 回のみ使用）。さらに **結合テスト harness が本番忠実に更新済み**:
     `tests/Integration/IntegrationTestCase.php:57` が `EMULATE_PREPARES=false` を
     明示設定し（コメントに「§0-3 ドリフトを捕捉するため」と明記）、216 件緑。
     よって本 md 旧記述「統合テストは設定しない（既定 true）」は**陳腐化**。
     クリーン E2E（`docker-compose.e2e.yaml` を本番忠実設定）でも createProject 通過。

> （旧注記は解決済みのため撤回）§0-2/§0-3 はコード化された決定（L5 strict / MySQL
> strict 既定）を**覆さずに**修正されている＝クエリ側が strict に準拠した。回帰は
> 結合テスト（本番忠実 PDO）＋クリーン E2E（本番忠実 compose）で二重にガードされる。

---

## 1. BACKEND-500 — フロントは正しいがサーバが落ちる実バグ（環境非依存）

### 1-1. 停車パターン作成が必ず 500
`POST /api/v1/projects/{id}/stop_patterns` →
`StopPatternsService.php:138` が `lines_id` を**文字列のまま**
`StopPatternsRepo::insertStopPattern()`（`UuidInterface` を要求）に渡す。
`from_project_stations_id` / `to_project_stations_id`（`?UuidInterface`）も同様。
ログ: `TypeError: Argument #5 ($linesId) must be of type Ramsey\Uuid\UuidInterface, string given`。
UI: 路線・駅管理 → 停車パターン → ウィザード「完了」で 500。編集／複製／削除も
作成不能のため到達不可。
- **[修正済]** `StopPatternsService` に `asUuid()/asUuidOrNull()` を追加し、
  `lines_id` / `from`/`to` を string→Uuid 変換（UuidInterface はそのまま通す）。
  curl で 201、UI でパターンカード表示を確認。`StopPatternApiTest`(10件)グリーン維持。

### 1-2. ワーク作成が施行日(affect_date)付きで 500
`POST .../work_groups/{id}/works` →
`WorksService.php:139` が `affect_date` を**文字列のまま**
`WorksRepo::insertWork()`（`?DateTimeInterface` を要求）に渡す。
ログ: `TypeError: Argument #6 ($affectDate) must be of type ?DateTimeInterface, string given`。
WorkDialog は既定で当日日付を入れるため、**通常操作のワーク作成が常に 500**
（日付を空にすると成功する、という罠）。
- **[修正済]** `WorksService` の create/update 両経路で
  `Utils::fromJsonDateOnlyStrToDateTime()` により string→DateTime 変換
  （DateTimeInterface はそのまま通す）。curl で 201 + `affect_date` 永続確認。
  `WorkApiTest` グリーン維持。

> 1-1/1-2 は同系統（service 層で request 文字列→型付きオブジェクトの変換漏れ）。
> 他 create（Project / Line / Station / StationOnLine / Color / 番線 / Train /
> 時刻表行）は E2E で作成成功を確認済み。

---

## 2. WRONG-SOURCE — メモリ上サンプル `data` を読み書きして実データに繋がらない

App.tsx は API（TanStack Query）から描画する一方、`data = useState(createInitialData())`
という**サンプルの初期データ**が別に存在し、一部機能がそちらを参照している。

### 2-1. プロジェクト一覧「📤 エクスポート」(ツールバー)
`exportAll()`（App.tsx:1257）が `data`（サンプル）を JSON 化。作成した実プロジェクト
ではなく常に同じサンプル（「東海道本線 ダイヤ2024」等）を吐く。E2E でダウンロード
内容を検証して確認済み。
- **[修正対象]** API（`apiProjects` ほか）から組み立てる。

### 2-2. プロジェクトカードメニュー「JSONとしてエクスポート」/ サイドバー footer「📤 エクスポート(JSON)」
`exportProject(pid)`（App.tsx:1242）が `data.projects.find(id===pid)` を引くが、実 ID は
バックエンド UUID、`data` にはサンプル ID `p1/p2` しか無い → `if(!p) return` で
**ダウンロードが一切発生しない**。
- **[修正対象]** API から対象プロジェクトを取得して出力。

### 2-3. プロジェクト一覧「📥 インポート」
`importJson()`（App.tsx:1273）が `setData(...)` でメモリ状態に書くが、一覧は
`apiProjects` を描画するため**取り込んでも画面に出ない**（無効果）。
- **[要設計判断]** API へ POST して取り込むか、`data` を描画ソースにするか。

### 2-4. WorkBrowser / ApplyPatternDialog の路線・経由駅がサンプル
App.tsx:1490-1491 が WorkBrowser に `stationsOnLine={data.stationsOnLine}` /
`lines={data.lines}`（サンプル）を渡す。結果、列車のパターン適用ダイアログの路線
ドロップダウンに実プロジェクトの路線でなくサンプル（l1/l2）が出る。
- **[修正済]** App.tsx の `stationsOnLine` を全ライン分に拡張。
  `useAllStationsOnLine(allLineIds)` を追加し、WorkBrowser (ApplyPatternDialog) と
  StopPatternWizard に `modelAllStationsOnLine` を渡す。各ラインのデータは同一
  queryKey でキャッシュ共有されるため重複リクエストなし。LineManager は引き続き
  `modelStationsOnLine`（選択中ライン）を使用（CRUD スコープが currentLine 単位）。

---

## 3. UNIMPLEMENTED / 永続しない（時刻表グリッド）

> timetable/work の E2E スペックは担当エージェントがセッション上限で中断し、
> セレクタ（`新規列車` が 2 要素にマッチ等）が未修正の失敗が混在する。以下は
> ソース・ログで裏取りした項目のみ。スペック安定化は別途実施。

### 3-1. 行の詳細「車両到着時刻(alwaysShowHH)」「着/発の非表示」が保存されない
`modelRowToEntityDraft`（App.tsx:931）に `alwaysShowHh` / `arriveHidden` /
`departureHidden` 相当が含まれず、編集してもリロードで戻る。
- **[要設計判断]** バックエンド `timetable_rows` に該当列があるか確認の上、
  draft に追加（列が無ければ「表示のみ・非永続」が仕様＝バグではない）。

### 3-2. 時刻表行のドラッグ並べ替え
`.drag-handle` の CSS はあるが `draggable` 等が描画されず並べ替え不可。
- **[要設計判断]** 並べ替え UI を実装するか、ハンドル表示を削るか。

### 3-3. 行の詳細「作業種別(workType)」が保存されない（実装準備中）
詳細モーダルに作業種別の自由入力テキストがあるが、リロードで戻る（永続しない）。
バックエンド側が未実装のため: DB 列 `work_type` は `TINYINT UNSIGNED`、enum
`WorkAtStationType` は `case none = 0` のみ、OpenAPI も `作業種別 (実装準備中)`
と明記。文字列（例「荷役」）を TINYINT enum に格納できないため永続不可。
- **[実装準備中]** バックエンドの enum 拡張または列型変更が必要。フロントは
  表示のみ（非永続）＝現状はバグではなく未実装。
  対応 E2E: timetable.spec.ts「detail modal workType is NOT persisted」。
- **graceful degradation:** `work_type` を送ると backend が
  `Unknown WorkAtStationType` を返し、**行全体の保存が失敗**して同時編集した
  他フィールドも失われていた。そのため `toApiTimetableRow` の送信ペイロードから
  `work_type` を除外し、showHH/arriveHidden と同様に「黙って破棄」する挙動に統一。
  保存自体は成功する。
- **残課題（要判断）:** 入力欄は依然編集可能だが値は破棄される UX ワート。
  enum 実装までは入力欄を無効化／非表示にするか要検討。

---

## 4. 確認済み・正常 (OK)

E2E で効果（多くはリロード後の永続）まで確認:
プロジェクト 作成/編集/削除/開く/カードメニュー・右クリック、サイドバー WG/ワーク
作成・編集・削除・コンテキストメニュー、画面遷移（路線・駅／色マーカー）、列車 作成
（日付罠を回避すれば）・編集・削除・選択、路線 追加/編集/削除、駅 追加/編集/削除、
経由駅 追加/編集/削除/**ドラッグ並べ替え**、番線 追加/編集/削除、色マーカー（未走査）、
時刻表行 追加/各セル編集/通過・備考トグル/削除/色（概ね）。

## 5. 未走査（カバレッジの穴）
担当エージェント未起動／中断のため未検証。要追加 E2E:
- 色マーカー管理（ColorManager）の全操作
- 停車パターンウィザード内部（ステップ next/back/完了、行構築）※作成は 1-1 で 500
- ApplyPatternDialog 内部（パターン適用の最終効果＝列車＋時刻表行生成）
- AppShell（🌙 テーマ / JP・EN 言語切替 / パンくず）
- 認証まわり（AccountSettingDialog サインアウト・表示名、EMailVerifyDialog 再送、
  パスワード再設定、新規登録、パスワード表示切替）
