// WorkBrowser — train master/detail layout: train list + timetable grid.
// Ported from WorkBrowser.jsx.
import { useEffect, useState } from "react";

import { ApplyPatternDialog } from "./ApplyPatternDialog";
import { BBCodeField } from "./BBCodeEditor";
import { TimetableGrid } from "./TimetableGrid";

import type { AppliedRow } from "./ApplyPatternDialog";
import type { Strings } from "../i18n/strings";
import type {
	Color as EntityColor,
	Train as EntityTrain,
} from "../types/entities";
import type {
	Direction,
	Line,
	Station,
	StationOnLine,
	StopPattern,
	TimetableRow,
	Train,
	Work,
} from "../types/model";

type EntityTrainDraft = Omit<EntityTrain, "id" | "workId" | "createdAt">;
type EntityTrainUpdate = Pick<EntityTrain, "id"> &
	Omit<EntityTrain, "id" | "workId" | "createdAt">;

function modelTrainToEntityDraft(t: Train): EntityTrainDraft {
	return {
		description: "",
		trainNumber: t.trainNumber,
		direction: t.direction,
		dayCount: t.dayCount,
		maxSpeed: t.maxSpeed !== "" ? t.maxSpeed : undefined,
		speedType: t.speedType !== "" ? t.speedType : undefined,
		nominalTractiveCapacity:
			t.nominalTractiveCapacity !== "" ? t.nominalTractiveCapacity : undefined,
		carCount: t.carCount,
		destination: t.destination !== "" ? t.destination : undefined,
		beginRemarks: t.beginRemarks !== "" ? t.beginRemarks : undefined,
		afterRemarks: t.afterRemarks !== "" ? t.afterRemarks : undefined,
		remarks: t.remarks !== "" ? t.remarks : undefined,
		beforeDeparture: t.beforeDeparture !== "" ? t.beforeDeparture : undefined,
		afterArrive: t.afterArrive !== "" ? t.afterArrive : undefined,
		trainInfo: t.trainInfo !== "" ? t.trainInfo : undefined,
		isRideOnMoving: t.isRideOnMoving,
	};
}

type TextKey =
	| "maxSpeed"
	| "speedType"
	| "nominalTractiveCapacity"
	| "beforeDeparture"
	| "afterArrive"
	| "beginRemarks"
	| "afterRemarks"
	| "remarks"
	| "trainInfo";

type TrainInfoDialogProps = {
	readonly train: Train;
	readonly onSave: (t: Train) => void;
	readonly onDelete?: (id: string) => void;
	readonly onClose: () => void;
	readonly t: Strings;
};

const TrainInfoDialog = ({
	train,
	onSave,
	onDelete,
	onClose,
	t,
}: TrainInfoDialogProps) => {
	const [d, setD] = useState<Train>({ ...train });
	const set = <K extends keyof Train>(k: K, v: Train[K]) => {
		setD((p) => ({ ...p, [k]: v }));
	};

	// BBCode-aware textarea helper: ta(key, rows, placeholder, bbcode, labelText)
	const ta = (
		k: TextKey,
		rows?: number,
		ph?: string,
		bbcode?: boolean,
		labelText?: string
	) => {
		rows = rows || 2;
		ph = ph || "";
		bbcode = !!bbcode;
		labelText = labelText || "";
		if (bbcode) {
			return (
				<BBCodeField
					label={labelText}
					value={d[k] || ""}
					onChange={(v) => {
						set(k, v);
					}}
					placeholder={ph}
					multiline
					rows={rows}
				/>
			);
		}
		return (
			<div className="field">
				{labelText ? <label>{labelText}</label> : null}
				<textarea
					value={d[k] || ""}
					onChange={(e) => {
						set(k, e.target.value);
					}}
					rows={rows}
					placeholder={ph}
					style={{
						width: "100%",
						padding: 8,
						border: "1px solid var(--color-border)",
						borderRadius: "var(--radius)",
						background: "var(--color-content)",
						color: "var(--color-text)",
						fontFamily: "inherit",
						fontSize: 13,
						resize: "vertical",
						outline: "none",
						lineHeight: 1.5,
					}}
				/>
			</div>
		);
	};

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div
				className="modal"
				style={{ maxWidth: 760, width: "100%" }}>
				<div className="modal-header">
					<span className="modal-title">
						🚆 {train.trainNumber} — {t.trainInfo}
					</span>
					<button
						className="btn btn-ghost btn-sm"
						onClick={onClose}>
						✕
					</button>
				</div>
				<div className="modal-body">
					{/* 基本 */}
					<div
						className="section-title"
						style={{ marginBottom: 8 }}>
						基本情報
					</div>
					<div
						style={{
							display: "grid",
							gridTemplateColumns: "repeat(4,1fr)",
							gap: 10,
							marginBottom: 16,
						}}>
						<div className="field">
							<label>{t.trainNumber}</label>
							<input
								value={d.trainNumber || ""}
								onChange={(e) => {
									set("trainNumber", e.target.value);
								}}
								style={{
									fontFamily: "var(--font-mono)",
									fontWeight: 600,
								}}
							/>
						</div>
						<div className="field">
							<label>{t.direction}</label>
							<select
								value={d.direction}
								onChange={(e) => {
									set("direction", +e.target.value as Direction);
								}}>
								<option value={1}>{t.downDir}</option>
								<option value={-1}>{t.upDir}</option>
							</select>
						</div>
						<div className="field">
							<label>{t.destination}</label>
							<input
								value={d.destination || ""}
								onChange={(e) => {
									set("destination", e.target.value);
								}}
							/>
						</div>
						<div className="field">
							<label>{t.carCount}</label>
							<input
								type="number"
								min={1}
								value={d.carCount || 0}
								onChange={(e) => {
									set("carCount", +e.target.value);
								}}
							/>
						</div>
					</div>

					{/* 走行特性 (multiline) — BBCode対応 */}
					<div
						className="section-title"
						style={{ marginBottom: 8 }}>
						走行特性（複数行可）
					</div>
					<div
						style={{
							display: "grid",
							gridTemplateColumns: "1fr 1fr 1fr",
							gap: 10,
							marginBottom: 16,
						}}>
						<div className="field">
							<label>{t.maxSpeed}</label>
							{ta("maxSpeed", 2, "例: 120", true)}
						</div>
						<div className="field">
							<label>{t.speedType}</label>
							{ta("speedType", 2, "例: 特急 A", true)}
						</div>
						<div className="field">
							<label>牽引定数</label>
							{ta("nominalTractiveCapacity", 2, "例: 12", true)}
						</div>
					</div>

					{/* 運用情報 */}
					<div
						className="section-title"
						style={{ marginBottom: 8 }}>
						運用情報
					</div>
					<div
						style={{
							display: "grid",
							gridTemplateColumns: "1fr 1fr 120px 120px",
							gap: 10,
							marginBottom: 8,
						}}>
						<div className="field">
							<label>{t.workType}</label>
							<input
								value={d.workType || ""}
								onChange={(e) => {
									set("workType", e.target.value);
								}}
								placeholder="旅客 / 貨物 等"
							/>
						</div>
						<div className="field">
							<label>次の列車ID</label>
							<input
								value={d.nextTrainId || ""}
								onChange={(e) => {
									set("nextTrainId", e.target.value);
								}}
								placeholder="—"
							/>
						</div>
						<div className="field">
							<label>行路内日付カウント</label>
							<input
								type="number"
								value={d.dayCount || 0}
								onChange={(e) => {
									set("dayCount", +e.target.value);
								}}
							/>
						</div>
						<div
							className="field"
							style={{ justifyContent: "flex-end" }}>
							<label
								style={{
									display: "flex",
									alignItems: "center",
									gap: 6,
									cursor: "pointer",
									padding: "7px 0",
								}}>
								<input
									type="checkbox"
									checked={!!d.isRideOnMoving}
									onChange={(e) => {
										set("isRideOnMoving", e.target.checked);
									}}
									style={{ accentColor: "var(--color-accent)" }}
								/>
								<span style={{ fontSize: 13 }}>便乗</span>
							</label>
						</div>
					</div>

					{/* 作業 — BBCode対応 */}
					<div
						className="section-title"
						style={{ marginTop: 16, marginBottom: 8 }}>
						作業
					</div>
					<div
						style={{
							display: "grid",
							gridTemplateColumns: "1fr 1fr",
							gap: 10,
							marginBottom: 16,
						}}>
						{ta(
							"beforeDeparture",
							2,
							"発車前の準備作業など",
							true,
							"発前作業 (beforeDeparture)"
						)}
						{ta(
							"afterArrive",
							2,
							"到着後の入換・解結など",
							true,
							"着後作業 (afterArrive)"
						)}
					</div>

					{/* 注意事項 — BBCode対応 */}
					<div
						className="section-title"
						style={{ marginBottom: 8 }}>
						注意事項
					</div>
					<div
						style={{
							display: "grid",
							gridTemplateColumns: "1fr 1fr",
							gap: 10,
							marginBottom: 10,
						}}>
						{ta(
							"beginRemarks",
							3,
							"発車前に乗務員へ伝達する事項",
							true,
							"発車前注意 (beginRemarks)"
						)}
						{ta(
							"afterRemarks",
							3,
							"到着後の確認事項",
							true,
							"到着後注意 (afterRemarks)"
						)}
					</div>
					<div style={{ marginBottom: 12 }}>
						{ta("remarks", 2, "", true, `${t.remarks}（全般）`)}
					</div>
					<div>
						{ta(
							"trainInfo",
							3,
							"特記事項・編成情報・付記など",
							true,
							"列車情報 (trainInfo)"
						)}
					</div>
				</div>
				<div className="modal-footer">
					<button
						className="btn btn-danger btn-sm"
						style={{ marginRight: "auto" }}
						onClick={() => {
							if (
								onDelete &&
								confirm(`列車「${train.trainNumber}」を削除しますか？`)
							) {
								onDelete(train.id);
								onClose();
							}
						}}>
						🗑 {t.delete}
					</button>
					<button
						className="btn btn-secondary"
						onClick={onClose}>
						{t.cancel}
					</button>
					<button
						className="btn btn-primary"
						onClick={() => {
							onSave(d);
							onClose();
						}}>
						{t.save}
					</button>
				</div>
			</div>
		</div>
	);
};

type TrainHeaderBarProps = {
	readonly train: Train;
	readonly onOpenDialog: () => void;
	readonly onApplyPattern: () => void;
	readonly canWrite: boolean;
	readonly t: Strings;
};

const TrainHeaderBar = ({
	train,
	onOpenDialog,
	onApplyPattern,
	canWrite,
	t,
}: TrainHeaderBarProps) => {
	const fl = (s?: string) => (s ? String(s).split("\n")[0] : "");
	return (
		<div
			style={{
				borderBottom: "1px solid var(--color-border)",
				background: "var(--color-content)",
			}}>
			<div
				style={{
					display: "flex",
					alignItems: "center",
					padding: "10px 16px",
					gap: 10,
				}}>
				<div
					style={{
						display: "flex",
						alignItems: "baseline",
						gap: 8,
					}}>
					<span
						style={{
							fontSize: 18,
							fontWeight: 700,
							fontFamily: "var(--font-mono)",
							color: "var(--color-text)",
						}}>
						{train.trainNumber}
					</span>
					<span className={`chip ${train.direction === 1 ? "green" : "amber"}`}>
						{train.direction === 1 ? t.downDir : t.upDir}
					</span>
					<span style={{ fontSize: 13, color: "var(--color-text-muted)" }}>
						→
					</span>
					<span style={{ fontSize: 14, fontWeight: 500 }}>
						{train.destination || "(行き先未設定)"}
					</span>
				</div>
				<div style={{ flex: 1 }} />
				<span
					style={{
						fontSize: 11,
						color: "var(--color-text-muted)",
						display: "flex",
						gap: 8,
						whiteSpace: "nowrap",
					}}>
					{fl(train.speedType) && <span>{fl(train.speedType)}</span>}
					{train.carCount > 0 && <span>· {train.carCount}両</span>}
					{fl(train.maxSpeed) && <span>· {fl(train.maxSpeed)} km/h</span>}
					{fl(train.nominalTractiveCapacity) && (
						<span>· 牽引{fl(train.nominalTractiveCapacity)}</span>
					)}
				</span>
				{canWrite && (
					<>
						<button
							className="btn btn-ghost btn-sm"
							onClick={onApplyPattern}
							title="停車パターンを適用">
							🧩 パターン適用
						</button>
						<button
							className="btn btn-secondary btn-sm"
							onClick={onOpenDialog}>
							⚙ {t.trainInfo}
						</button>
					</>
				)}
			</div>
		</div>
	);
};

type TrainListPanelProps = {
	readonly trains: Train[];
	readonly isLoadingTrains: boolean;
	readonly selectedId: string | null;
	readonly onSelect: (id: string) => void;
	readonly onAdd: () => void;
	readonly onAddViaPattern: () => void;
	readonly canWrite: boolean;
	readonly t: Strings;
};

const TrainListPanel = ({
	trains,
	isLoadingTrains,
	selectedId,
	onSelect,
	onAdd,
	onAddViaPattern,
	canWrite,
	t,
}: TrainListPanelProps) => {
	return (
		<div
			style={{
				display: "flex",
				flexDirection: "column",
				height: "100%",
				background: "var(--color-content)",
				borderRight: "1px solid var(--color-border)",
			}}>
			<div
				style={{
					padding: "10px 12px",
					borderBottom: "1px solid var(--color-border)",
					display: "flex",
					gap: 6,
					alignItems: "center",
				}}>
				<strong style={{ fontSize: 13 }}>{t.trains}</strong>
				<span style={{ fontSize: 11, color: "var(--color-text-muted)" }}>
					({trains.length})
				</span>
				<div style={{ flex: 1 }} />
				{canWrite && (
					<>
						<button
							className="btn btn-ghost btn-xs"
							onClick={onAddViaPattern}
							title={t.newStopPattern}>
							🧩
						</button>
						<button
							className="btn btn-primary btn-xs"
							onClick={onAdd}>
							＋ {t.newTrain}
						</button>
					</>
				)}
			</div>
			<div style={{ flex: 1, overflow: "auto" }}>
				{isLoadingTrains && trains.length === 0 ? (
					<div className="loading-center">
						<span className="spinner" />
						読み込み中...
					</div>
				) : trains.length === 0 ? (
					<div
						className="empty-state"
						style={{ padding: "40px 16px" }}>
						<svg
							width="40"
							height="40"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							strokeWidth="1.5">
							<rect
								x="4"
								y="6"
								width="16"
								height="11"
								rx="2"
							/>
							<circle
								cx="8"
								cy="18"
								r="1.5"
							/>
							<circle
								cx="16"
								cy="18"
								r="1.5"
							/>
						</svg>
						<p style={{ fontSize: 12 }}>{t.noTrains}</p>
					</div>
				) : (
					trains.map((tr) => (
						<div
							key={tr.id}
							onClick={() => {
								onSelect(tr.id);
							}}
							style={{
								padding: "10px 12px",
								cursor: "pointer",
								borderBottom: "1px solid var(--color-border)",
								background:
									selectedId === tr.id
										? "var(--color-accent-bg)"
										: "transparent",
								borderLeft:
									selectedId === tr.id
										? "3px solid var(--color-accent)"
										: "3px solid transparent",
							}}>
							<div
								style={{
									display: "flex",
									alignItems: "baseline",
									gap: 6,
								}}>
								<span
									style={{
										fontFamily: "var(--font-mono)",
										fontWeight: 600,
										fontSize: 13,
										color:
											selectedId === tr.id
												? "var(--color-accent)"
												: "var(--color-text)",
									}}>
									{tr.trainNumber}
								</span>
								<span
									className={`chip ${tr.direction === 1 ? "green" : "amber"}`}
									style={{ fontSize: 9, padding: "1px 5px" }}>
									{tr.direction === 1 ? "↓" : "↑"}
								</span>
							</div>
							<div
								style={{
									fontSize: 12,
									color: "var(--color-text-muted)",
									marginTop: 2,
								}}>
								→ {tr.destination}
							</div>
							<div
								style={{
									fontSize: 10,
									color: "var(--color-text-muted)",
									marginTop: 3,
									display: "flex",
									gap: 6,
									flexWrap: "wrap",
								}}>
								<span>{tr.timetableRows?.length || 0}駅</span>
								<span>·</span>
								<span
									style={{
										whiteSpace: "nowrap",
										overflow: "hidden",
										textOverflow: "ellipsis",
									}}>
									{(tr.remarks || "").split("\n")[0]}
								</span>
							</div>
						</div>
					))
				)}
			</div>
		</div>
	);
};

type WorkBrowserProps = {
	readonly work: Work;
	readonly isLoadingTrains: boolean;
	readonly onCreateTrain: (draft: EntityTrainDraft) => void;
	readonly onUpdateTrain: (vars: EntityTrainUpdate) => void;
	readonly onDeleteTrain: (id: string) => void;
	readonly onSelectTrain: (id: string | null) => void;
	readonly onOpenStopPatternWizard: () => void;
	readonly onApplyPattern: (args: {
		rows: AppliedRow[];
		direction: Direction;
		destination: string;
		existingTrain: Train | null;
	}) => void;
	readonly stopPatterns: StopPattern[];
	readonly stations: Station[];
	readonly colors: EntityColor[];
	readonly stationsOnLine: StationOnLine[];
	readonly lines: Line[];
	readonly onCreateRow: (trainId: string, row: TimetableRow) => void;
	readonly onUpdateRow: (
		trainId: string,
		rowId: string,
		row: TimetableRow
	) => void;
	readonly onDeleteRow: (trainId: string, rowId: string) => void;
	readonly canWrite?: boolean;
	readonly t: Strings;
};

export const WorkBrowser = ({
	work,
	isLoadingTrains,
	onCreateTrain,
	onUpdateTrain,
	onDeleteTrain,
	onSelectTrain,
	onApplyPattern,
	stopPatterns,
	stations,
	colors,
	stationsOnLine,
	lines,
	onCreateRow,
	onUpdateRow,
	onDeleteRow,
	canWrite = true,
	t,
}: WorkBrowserProps) => {
	const [selectedTrainId, setSelectedTrainId] = useState<string | null>(
		work.trains.length > 0 ? (work.trains[0]?.id ?? null) : null
	);
	const [showInfo, setShowInfo] = useState(false);
	const [showApplyPattern, setShowApplyPattern] = useState(false);
	const [applyTargetTrain, setApplyTargetTrain] = useState<Train | null>(null);
	const selectedTrain = work.trains.find((tr) => tr.id === selectedTrainId);

	const selectTrain = (id: string | null) => {
		setSelectedTrainId(id);
		onSelectTrain(id);
	};

	// Auto-select the first train so the timetable grid mounts. The useState
	// initializer above only runs once at mount, when work.trains is usually
	// still empty (trains load async via useTrains, and a freshly created train
	// arrives after mount). Re-select whenever the current selection is missing
	// (null, or no longer in the list) and trains are available. Mirrors the
	// WG/work auto-select effect in App.tsx. Must call selectTrain (not just
	// setSelectedTrainId) so App.tsx's currentTrain is set too, otherwise
	// useTimetableRows(currentTrain) stays empty and rows never load.
	const firstTrainId = work.trains[0]?.id ?? null;
	const selectionValid =
		selectedTrainId != null &&
		work.trains.some((tr) => tr.id === selectedTrainId);
	useEffect(() => {
		if (!selectionValid && firstTrainId) {
			selectTrain(firstTrainId);
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [selectionValid, firstTrainId]);

	const updateTrain = (updated: Train) => {
		onUpdateTrain({ id: updated.id, ...modelTrainToEntityDraft(updated) });
	};
	const addTrain = () => {
		const draft: EntityTrainDraft = {
			description: "",
			trainNumber: "0000M",
			direction: 1,
			destination: undefined,
			maxSpeed: "100",
			speedType: "近郊型",
			nominalTractiveCapacity: undefined,
			carCount: 10,
			dayCount: 0,
			isRideOnMoving: false,
			beginRemarks: undefined,
			afterRemarks: undefined,
			remarks: undefined,
			beforeDeparture: undefined,
			afterArrive: undefined,
			trainInfo: undefined,
		};
		onCreateTrain(draft);
	};

	return (
		<div
			style={{
				display: "grid",
				gridTemplateColumns: "240px 1fr",
				height: "100%",
				overflow: "hidden",
			}}>
			<TrainListPanel
				trains={work.trains}
				isLoadingTrains={isLoadingTrains}
				selectedId={selectedTrainId}
				onSelect={selectTrain}
				onAdd={addTrain}
				onAddViaPattern={() => {
					setShowApplyPattern(true);
				}}
				canWrite={canWrite}
				t={t}
			/>
			<div
				style={{
					display: "flex",
					flexDirection: "column",
					overflow: "hidden",
					background: "var(--color-content)",
				}}>
				{selectedTrain ? (
					<>
						<TrainHeaderBar
							train={selectedTrain}
							onOpenDialog={() => {
								setShowInfo(true);
							}}
							onApplyPattern={() => {
								setApplyTargetTrain(selectedTrain);
								setShowApplyPattern(true);
							}}
							canWrite={canWrite}
							t={t}
						/>
						<div style={{ flex: 1, overflow: "hidden" }}>
							<TimetableGrid
								train={selectedTrain}
								stations={stations}
								colors={colors}
								onCreateRow={(r) => {
									onCreateRow(selectedTrain.id, r);
								}}
								onUpdateRow={(rowId, r) => {
									onUpdateRow(selectedTrain.id, rowId, r);
								}}
								onDeleteRow={(rowId) => {
									onDeleteRow(selectedTrain.id, rowId);
								}}
								canWrite={canWrite}
								t={t}
							/>
						</div>
						{showInfo ? (
							<TrainInfoDialog
								train={selectedTrain}
								onSave={updateTrain}
								onDelete={onDeleteTrain}
								onClose={() => {
									setShowInfo(false);
								}}
								t={t}
							/>
						) : null}
					</>
				) : (
					<div
						className="empty-state"
						style={{ flex: 1, justifyContent: "center" }}>
						<svg
							width="64"
							height="64"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							strokeWidth="1">
							<rect
								x="3"
								y="6"
								width="18"
								height="12"
								rx="2"
							/>
							<path d="M3 10h18M9 14h.01M15 14h.01" />
						</svg>
						<p>{t.noTrains}</p>
						{canWrite && (
							<div style={{ display: "flex", gap: 8, marginTop: 4 }}>
								<button
									className="btn btn-secondary btn-sm"
									onClick={() => {
										setShowApplyPattern(true);
									}}>
									🧩 パターンから作成
								</button>
								<button
									className="btn btn-primary btn-sm"
									onClick={addTrain}>
									＋ {t.newTrain}
								</button>
							</div>
						)}
					</div>
				)}
			</div>

			{showApplyPattern ? (
				<ApplyPatternDialog
					stopPatterns={stopPatterns || []}
					stations={stations || []}
					stationsOnLine={stationsOnLine || []}
					lines={lines || []}
					t={t}
					existingTrain={applyTargetTrain}
					onApply={({ rows, direction, destination }) => {
						onApplyPattern({
							rows,
							direction,
							destination,
							existingTrain: applyTargetTrain,
						});
					}}
					onClose={() => {
						setShowApplyPattern(false);
						setApplyTargetTrain(null);
					}}
				/>
			) : null}
		</div>
	);
};
