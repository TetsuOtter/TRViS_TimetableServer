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
| `ONLY_FULL_GROUP_BY`（§0-2） | ENV | 暫定(コンテナ `SET PERSIST`、追跡未変更) | sql_mode 確認 |
| `EMULATE_PREPARES=false`（§0-3） | ENV | 暫定(gitignore `config.inc.php`、追跡テンプレ未変更) | curl create 通過 |
| エクスポート/インポート（§2-1〜2-3） | WRONG-SRC | **未対応(要設計/機能規模)** | E2E で死を確認 |
| 時刻表 行 model-only 永続/ドラッグ（§3） | UNIMPL等 | **要設計判断** | ソース確認 |

> **【最重要・オーナー判断】 §0-2/§0-3 — 本番でも create を壊している可能性**:
> 「設定緩和（masking）」か「クエリ/設定の正規修正」かは未決。現状その回避策は
> **追跡対象外**（MySQL は実行中コンテナの `SET PERSIST`＝データボリューム寿命、
> `EMULATE_PREPARES` は gitignore の `config.inc.php`）に置いている。
> このため **クリーンチェックアウト＋新規ボリュームでは E2E が setup(createProject)
> で全滅する**（＝スイートは現状このマシンでしか緑にならない）。
> 推奨する決着のいずれかをオーナーが選ぶ必要がある:
>   1. **dev/emulator スコープの追跡ファイルに回避策をコミット**して再現可能にする
>      — `mysql/conf.d/my.cnf` に `sql_mode`(ONLY_FULL_GROUP_BY 除外)、
>      `config.docker.inc.php` に `EMULATE_PREPARES=true`。いずれも **本 §0 への
>      コメント付き**で。本番は別の追跡対象外 `config.inc.php` を使うため本番非影響＝
>      「隠蔽」でなく「開示」。ただし本番クエリが壊れている疑いは §0-2/§0-3 のまま残す。
>   2. **クエリ/設定を正規修正**（GROUP BY 付与 9 repo / 同名プレースホルダ別名化）。
> どちらもコードレビュー済みの決定（L5 strict / MySQL strict 既定）に触れるため、
> **私の独断ではコミットせずオーナー判断に委ねている。**

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

2. **`ONLY_FULL_GROUP_BY` で privilege サブクエリが 1140 エラー**
   多数の repo の SELECT が `MAX(privilege_type)` を非集約列と併用しつつ GROUP BY を
   省く（例 `ProjectsRepo::selectProjectOne`）。MySQL 8 既定の `ONLY_FULL_GROUP_BY`
   で `SQLSTATE[42000] 1140` → 500。
   - **[要対応]** 暫定: 開発 DB で `sql_mode` から `ONLY_FULL_GROUP_BY` を除外
     （実行中コンテナに `SET PERSIST` 適用済み。追跡ファイル `mysql/conf.d/my.cnf`
     は**未変更**＝勝手な設定変更を避けるため）。
   - **恒久案**: 該当サブクエリ（9 repo に同パターン）へ `GROUP BY` を付与。
     どちらを採るか要判断（設定緩和=masking / クエリ修正≈9ファイル）。

3. **`ATTR_EMULATE_PREPARES=false` で同名プレースホルダ再利用が HY093**
   `selectProjectOne` 等が `:projects_id` を 1 クエリ内で 2 回使う。native prepare
   では同名プレースホルダ再利用不可 → `SQLSTATE[HY093] Invalid parameter number`。
   統合テストは PDO に `EMULATE_PREPARES` を**設定しない**（既定 true）ため、この
   ドリフトはテストで検出されない。つまり**コミット済み設定では create 系が本当に
   壊れている**。
   - **[要対応]** 暫定: ローカル `backend/config/prod/config.inc.php`（gitignore）で
     `EMULATE_PREPARES=true`。追跡テンプレ `config.docker.inc.php` は**未変更**。
   - **恒久案**: テンプレ／本番設定を `true` に揃える（テスト harness と一致）か、
     同名プレースホルダ再利用クエリを別名化。要判断。本番 config が `false` なら
     **本番でも create が壊れている可能性**があるので最重要。

> 2・3 はコード化された決定（L5 strict / MySQL strict 既定）を覆すため、追跡ファイル
> への変更は**勝手にコミットせず**ローカル／コンテナ内オーバーライドに留めている。
> 採用方針（設定緩和か正規修正か）はオーナー判断。

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
