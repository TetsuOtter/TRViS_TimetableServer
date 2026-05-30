// TimetableGrid — spreadsheet-style timetable row editor. Ported from TimetableGrid.jsx.
import { useCallback, useEffect, useMemo, useRef, useState } from "react";

import { useStationTracks } from "../api/hooks/useStationTracks";
import { TRViSTime } from "../lib/timeUtils";

import {
	BBCodeEditButton,
	BBCodeField,
} from "./BBCodeEditor";

import type { RowFormat } from "../lib/timeUtils";
import type { Strings } from "../i18n/strings";
import type { Color as EntityColor } from "../types/entities";
import type { ShowHH, Station, TimetableRow, Train } from "../types/model";
import type { CSSProperties } from "react";

interface ParsedTime {
	hh: string;
	mm: string;
	ss: string;
}

// Parse a TRViS-formatted time string like 'HH:MM:SS', ':MM:SS', 'HH:MM:', ':MM:'
function parseFormattedTime(str: string | null | undefined): ParsedTime | null {
	if (!str) return null;
	const parts = str.split(":");
	if (parts.length !== 3) return null;
	return { hh: parts[0]!, mm: parts[1]!, ss: parts[2]! };
}

interface AlignedTimeProps {
	formatted: string | null;
	rawValue?: string;
	muted?: boolean;
}

// Render a formatted time string as fixed-width monospace spans so that
// omitted HH/SS still occupy the correct horizontal space.
function AlignedTime({ formatted, rawValue, muted }: AlignedTimeProps) {
	const p = parseFormattedTime(formatted);
	if (!p) {
		return (
			<span
				style={{
					fontFamily: "var(--font-mono)",
					fontSize: 12,
					opacity: muted ? 0.18 : 1,
				}}>
				{formatted}
			</span>
		);
	}
	const raw = rawValue
		? parseFormattedTime(TRViSTime.display(rawValue, true))
		: null;
	const dimHH = raw ? raw.hh : "??";
	const dimSS = raw ? raw.ss : "??";

	const dim: CSSProperties = { opacity: 0.18, userSelect: "none" };
	const sep: CSSProperties = { opacity: 0.5 };
	return (
		<span
			style={{
				display: "inline-flex",
				alignItems: "baseline",
				fontFamily: "var(--font-mono)",
				fontSize: 12,
				letterSpacing: 0,
			}}>
			<span
				style={{
					display: "inline-block",
					width: "2ch",
					textAlign: "right",
					...(muted || p.hh === "" ? dim : {}),
				}}>
				{p.hh === "" ? dimHH : p.hh}
			</span>
			<span style={muted ? dim : sep}>:</span>
			<span
				style={{
					display: "inline-block",
					width: "2ch",
					textAlign: "left",
					...(muted ? dim : {}),
				}}>
				{p.mm}
			</span>
			<span style={muted ? dim : sep}>:</span>
			<span
				style={{
					display: "inline-block",
					width: "2ch",
					textAlign: "left",
					...(muted || p.ss === "" ? dim : {}),
				}}>
				{p.ss === "" ? dimSS : p.ss}
			</span>
		</span>
	);
}

interface TimeCellProps {
	value?: string;
	displayText?: string;
	onChange: (v: string) => void;
	onChangeText?: (v: string) => void;
	onKeyDown?: (e: React.KeyboardEvent<HTMLInputElement>) => void;
	inputRef?: React.RefObject<HTMLInputElement>;
	placeholder?: string;
	muted?: boolean;
	formattedValue?: string | null;
}

// TimeCell stores/emits HH:MM:SS internally (or free-text via onChangeText).
function TimeCell({
	value,
	displayText,
	onChange,
	onChangeText,
	onKeyDown,
	inputRef,
	placeholder = "──:──",
	muted,
	formattedValue,
}: TimeCellProps) {
	const [editing, setEditing] = useState(false);
	const [draft, setDraft] = useState("");
	const iRef = useRef<HTMLInputElement>(null);
	const ref = inputRef || iRef;

	const editDraft =
		displayText != null
			? displayText
			: value
				? TRViSTime.display(value, true)
				: "";

	const commit = () => {
		setEditing(false);
		const trimmed = draft.trim();
		// Emit exactly ONE change per commit. Previously the valid-time branch
		// called onChange(value) AND onChangeText("") — two separate updateRow()
		// calls that each spread the SAME stale rows[idx], so the second
		// (text="") clobbered the first's value back to empty and fired a racing
		// null PUT (which aborted). Each consumer handler now sets BOTH the time
		// and its display-text in a single update, so one call suffices here.
		if (trimmed === "") {
			onChange("");
			return;
		}
		const normalized = TRViSTime.normalize(trimmed);
		const isTime = /^\d{1,3}:\d{2}:\d{2}$/.test(normalized);
		if (isTime) {
			onChange(normalized);
		} else if (onChangeText) {
			onChangeText(trimmed);
		} else {
			onChange("");
		}
	};

	const startEditing = (initialDraft: string) => {
		setDraft(initialDraft);
		setEditing(true);
	};

	useEffect(() => {
		if (editing) ref.current?.select();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [editing]);

	if (editing) {
		return (
			<input
				ref={ref}
				className="time-input"
				value={draft}
				onChange={(e) => setDraft(e.target.value)}
				onBlur={commit}
				onKeyDown={(e) => {
					if (e.key === "Enter" || e.key === "Tab") {
						e.preventDefault();
						commit();
						onKeyDown && onKeyDown(e);
					}
					if (e.key === "Escape") {
						setEditing(false);
					}
				}}
				placeholder="00:00:00 または ↓ 等"
				style={{
					fontFamily: "var(--font-mono)",
					fontSize: "inherit",
					width: "100%",
					border: "none",
					outline: "none",
					background: "transparent",
					textAlign: "center",
					color: "inherit",
				}}
			/>
		);
	}

	if (displayText) {
		return (
			<span
				className="time-display"
				onClick={() => startEditing(displayText)}
				title={`表示文字列: ${displayText} — クリックして編集`}
				style={{ textAlign: "center", justifyContent: "center" }}>
				<span
					style={{
						fontFamily: "var(--font-mono)",
						fontSize: 12,
						opacity: muted ? 0.18 : 1,
					}}>
					{displayText}
				</span>
			</span>
		);
	}

	const hasFormatted = formattedValue !== undefined;
	const rawDisplay = value ? TRViSTime.display(value, true) : "";

	return (
		<span
			className="time-display"
			onClick={() => startEditing(editDraft)}
			title={value ? `${value} — クリックして編集` : "クリックして編集"}
			style={{ textAlign: "center", justifyContent: "center" }}>
			{hasFormatted ? (
				formattedValue ? (
					<AlignedTime
						formatted={formattedValue}
						rawValue={value}
						muted={muted}
					/>
				) : (
					<span
						style={{
							opacity: 0.25,
							fontFamily: "var(--font-mono)",
							fontSize: 12,
						}}>
						{placeholder}
					</span>
				)
			) : rawDisplay ? (
				<AlignedTime formatted={rawDisplay} rawValue={value} muted={muted} />
			) : (
				<span
					style={{
						opacity: 0.25,
						fontFamily: "var(--font-mono)",
						fontSize: 12,
					}}>
					{placeholder}
				</span>
			)}
		</span>
	);
}

interface NumLimitProps {
	value: number | "";
	onChange: (v: number | "") => void;
}

function NumLimit({ value, onChange }: NumLimitProps) {
	const v = value === "" || value == null ? "" : value;
	return (
		<input
			type="number"
			min={0}
			max={999}
			value={v}
			onChange={(e) => {
				const s = e.target.value;
				if (s === "") return onChange("");
				const n = Math.max(0, Math.min(999, +s));
				onChange(Number.isFinite(n) ? n : "");
			}}
			placeholder="—"
			style={{
				width: "100%",
				padding: "6px 8px",
				border: "1px solid var(--color-border)",
				borderRadius: "var(--radius)",
				fontFamily: "var(--font-mono)",
				fontSize: 13,
				background: "var(--color-content)",
				color: "var(--color-text)",
			}}
		/>
	);
}

interface RowDetailModalProps {
	row: TimetableRow;
	isFirstRow: boolean;
	isLastIdx: boolean;
	allRows: TimetableRow[];
	t: Strings;
	onSave: (r: TimetableRow) => void;
	onClose: () => void;
}

function RowDetailModal({
	row,
	isFirstRow,
	isLastIdx,
	allRows,
	t,
	onSave,
	onClose,
}: RowDetailModalProps) {
	const [data, setData] = useState<TimetableRow>({ ...row });
	const set = <K extends keyof TimetableRow>(k: K, v: TimetableRow[K]) =>
		setData((d) => ({ ...d, [k]: v }));

	const lastRowExclude = isLastIdx && data.isLastStop === false;

	const { arrivePhPlaceholder, departurePhPlaceholder } = useMemo(() => {
		if (!allRows)
			return {
				arrivePhPlaceholder: "HH:MM:SS",
				departurePhPlaceholder: "HH:MM:SS",
			};
		let lastHH: number | null = null;
		const rowIdx = allRows.findIndex((r) => r.id === data.id);
		for (let i = 0; i < rowIdx; i++) {
			const r = allRows[i];
			if (!r) continue;
			const forceShow = r.showHH === true;
			if (!r.isPass && r.arrive && !r.arriveDisplayText) {
				const res = TRViSTime.formatOne(r.arrive, forceShow, lastHH);
				if (res.formatted !== null) lastHH = res.newHH;
			}
			if (r.departure && !r.departureDisplayText) {
				const res = TRViSTime.formatOne(r.departure, forceShow, lastHH);
				if (res.formatted !== null) lastHH = res.newHH;
			}
		}

		const makePh = (
			timeStr: string,
			forceShow: boolean,
			lhh: number | null
		): string => {
			if (!timeStr) return "HH:MM:SS";
			const res = TRViSTime.formatOne(timeStr, forceShow, lhh);
			if (!res.formatted) return "HH:MM:SS";
			return res.formatted;
		};

		const forceShow = data.showHH === true;
		const arrivePh = makePh(data.arrive, forceShow, lastHH);

		let lastHHAfterArrive = lastHH;
		if (!data.isPass && data.arrive && !data.arriveDisplayText) {
			const res = TRViSTime.formatOne(data.arrive, forceShow, lastHH);
			if (res.formatted !== null) lastHHAfterArrive = res.newHH;
		}
		const depPh = makePh(data.departure, forceShow, lastHHAfterArrive);

		return { arrivePhPlaceholder: arrivePh, departurePhPlaceholder: depPh };
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [
		data.id,
		data.arrive,
		data.departure,
		data.showHH,
		data.isPass,
		data.arriveDisplayText,
		allRows,
	]);

	const normalizeTimeField = (val: string): string => {
		if (!val || !val.trim()) return "";
		return TRViSTime.normalize(val.trim());
	};

	const boolFlags: Array<["isPass" | "isOperationOnlyStop", string, string]> = [
		["isPass", t.pass, "通過"],
		["isOperationOnlyStop", t.opStop, "運転停車（客扱いなし）"],
	];

	const showHHOptions: Array<[ShowHH, string, string]> = [
		[undefined, "自動", "HHが前の表示時刻と同じ場合は省略"],
		[true, "常に表示", "この駅の時刻は常にHHを表示する"],
		[false, "常に省略", "この駅の時刻は常にHHを省略する"],
	];

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div className="modal" style={{ maxWidth: 620 }}>
				<div className="modal-header">
					<span className="modal-title">
						⚙ {row.stationName || "(未設定)"} — {t.detail}
					</span>
					<button className="btn btn-ghost btn-sm" onClick={onClose}>
						✕
					</button>
				</div>
				<div className="modal-body">
					<>
						{/* 駅名表示（読み取り専用） */}
						<div className="field-row" style={{ marginBottom: 12 }}>
							<div className="field" style={{ flex: 2 }}>
								<label>{t.stationName}</label>
								<span
									style={{
										padding: "6px 0",
										display: "block",
										fontWeight: 500,
										...(data.stationDeleted
											? { color: "var(--color-danger)", textDecoration: "line-through" }
											: {}),
									}}>
									{data.stationName || "(未設定)"}
									{data.stationDeleted && (
										<span
											style={{ marginLeft: 6, fontSize: 11, textDecoration: "none" }}>
											(削除済み)
										</span>
									)}
								</span>
							</div>
							<div className="field" style={{ flex: 3 }}>
								<label>駅名フルネーム</label>
								<span style={{ padding: "6px 0", display: "block" }}>
									{data.fullName || "—"}
								</span>
							</div>
						</div>

							{/* 着時刻・発時刻・運転時分 */}
							<div className="field-row" style={{ marginBottom: 12 }}>
								<div className="field">
									<label
										style={{
											display: "flex",
											alignItems: "center",
											gap: 6,
										}}>
										着時刻
										{!data.isPass && (
											<label
												style={{
													display: "flex",
													alignItems: "center",
													gap: 3,
													fontWeight: 400,
													fontSize: 11,
													color: "var(--color-text-muted)",
													cursor: "pointer",
													marginLeft: "auto",
													whiteSpace: "nowrap",
												}}>
												<input
													type="checkbox"
													checked={!!data.arriveHidden}
													onChange={(e) =>
														set("arriveHidden", e.target.checked)
													}
													style={{ accentColor: "var(--color-accent)" }}
												/>
												非表示
											</label>
										)}
									</label>
									<input
										value={data.arrive || ""}
										onChange={(e) => set("arrive", e.target.value)}
										onBlur={(e) => {
											const n = normalizeTimeField(e.target.value);
											set("arrive", n);
										}}
										placeholder="HH:MM:SS"
										disabled={!!data.isPass}
										style={{
											fontFamily: "var(--font-mono)",
											opacity: data.isPass ? 0.4 : 1,
										}}
									/>
								</div>
								<div className="field">
									<label
										style={{
											display: "flex",
											alignItems: "center",
											gap: 6,
										}}>
										発時刻
										<label
											style={{
												display: "flex",
												alignItems: "center",
												gap: 3,
												fontWeight: 400,
												fontSize: 11,
												color: "var(--color-text-muted)",
												cursor: "pointer",
												marginLeft: "auto",
												whiteSpace: "nowrap",
											}}>
											<input
												type="checkbox"
												checked={!!data.departureHidden}
												onChange={(e) =>
													set("departureHidden", e.target.checked)
												}
												style={{ accentColor: "var(--color-accent)" }}
											/>
											非表示
										</label>
									</label>
									<input
										value={data.departure || ""}
										onChange={(e) => set("departure", e.target.value)}
										onBlur={(e) => {
											const n = normalizeTimeField(e.target.value);
											set("departure", n);
										}}
										placeholder="HH:MM:SS"
										style={{ fontFamily: "var(--font-mono)" }}
									/>
								</div>
								<div className="field" style={{ flex: "0 0 160px" }}>
									<label>{t.driveTime}</label>
									<div
										style={{
											display: "flex",
											gap: 4,
											alignItems: "center",
										}}>
										<input
											type="number"
											min={0}
											value={data.driveTime_MM || 0}
											onChange={(e) =>
												set("driveTime_MM", +e.target.value)
											}
											style={{
												width: "100%",
												padding: "6px 4px",
												textAlign: "center",
												border: "1px solid var(--color-border)",
												borderRadius: 4,
												background: "var(--color-content)",
												color: "var(--color-text)",
												fontFamily: "var(--font-mono)",
											}}
										/>
										<span style={{ opacity: 0.5, fontSize: 11 }}>分</span>
										<input
											type="number"
											min={0}
											max={59}
											value={data.driveTime_SS || 0}
											onChange={(e) =>
												set("driveTime_SS", +e.target.value)
											}
											style={{
												width: "100%",
												padding: "6px 4px",
												textAlign: "center",
												border: "1px solid var(--color-border)",
												borderRadius: 4,
												background: "var(--color-content)",
												color: "var(--color-text)",
												fontFamily: "var(--font-mono)",
											}}
										/>
										<span style={{ opacity: 0.5, fontSize: 11 }}>秒</span>
									</div>
								</div>
							</div>

							{/* 到着・発車時刻欄に表示する文字列 */}
							<div
								style={{
									padding: "10px 12px",
									background: "var(--color-bg)",
									borderRadius: "var(--radius)",
									marginBottom: 12,
									border: "1px solid var(--color-border)",
								}}>
								<div
									style={{
										fontSize: 12,
										fontWeight: 600,
										color: "var(--color-text-muted)",
										marginBottom: 8,
									}}>
									到着・発車時刻欄に表示する文字列{" "}
									<span style={{ fontWeight: 400, opacity: 0.7 }}>
										（時刻の代わりに表示する場合）
									</span>
								</div>
								<div className="field-row">
									<div className="field">
										<label style={{ fontSize: 11 }}>着欄の表示文字列</label>
										<input
											value={data.arriveDisplayText || ""}
											onChange={(e) =>
												set("arriveDisplayText", e.target.value)
											}
											placeholder={arrivePhPlaceholder}
											style={{
												fontFamily: "var(--font-mono)",
												fontSize: 13,
											}}
										/>
										<span
											style={{
												fontSize: 10,
												color: "var(--color-text-muted)",
												marginTop: 2,
												display: "block",
											}}>
											空欄なら着時刻を表示
										</span>
									</div>
									<div className="field">
										<label style={{ fontSize: 11 }}>発欄の表示文字列</label>
										<input
											value={data.departureDisplayText || ""}
											onChange={(e) =>
												set("departureDisplayText", e.target.value)
											}
											placeholder={departurePhPlaceholder}
											style={{
												fontFamily: "var(--font-mono)",
												fontSize: 13,
											}}
										/>
										<span
											style={{
												fontSize: 10,
												color: "var(--color-text-muted)",
												marginTop: 2,
												display: "block",
											}}>
											空欄なら発時刻を表示
										</span>
									</div>
								</div>
							</div>

							{/* フラグ群 */}
							<div
								style={{
									display: "grid",
									gridTemplateColumns: "1fr 1fr",
									gap: 6,
									marginBottom: 12,
									padding: "10px 12px",
									background: "var(--color-bg)",
									borderRadius: "var(--radius)",
								}}>
								{boolFlags.map(([k, lbl, desc]) => (
									<label
										key={k}
										style={{
											display: "flex",
											alignItems: "center",
											gap: 6,
											fontSize: 13,
											cursor: "pointer",
										}}
										title={desc}>
										<input
											type="checkbox"
											checked={!!data[k]}
											onChange={(e) => set(k, e.target.checked)}
											style={{ accentColor: "var(--color-accent)" }}
										/>
										{lbl}
									</label>
								))}
								{isFirstRow && (
									<label
										style={{
											display: "flex",
											alignItems: "center",
											gap: 6,
											fontSize: 13,
											cursor: "pointer",
										}}
										title="始発駅で車両到着時刻を表示">
										<input
											type="checkbox"
											checked={!!data.hasBracket}
											onChange={(e) =>
												set("hasBracket", e.target.checked)
											}
											style={{ accentColor: "var(--color-accent)" }}
										/>
										{t.bracketTime}
									</label>
								)}
								{/* HH表示設定 */}
								<div
									style={{
										gridColumn: "span 2",
										padding: "8px 10px",
										background: "var(--color-content)",
										borderRadius: 4,
										border: "1px solid var(--color-border)",
									}}>
									<div
										style={{
											fontSize: 12,
											fontWeight: 600,
											color: "var(--color-text-muted)",
											marginBottom: 6,
										}}>
										HH（時）表示
									</div>
									<div style={{ display: "flex", gap: 8 }}>
										{showHHOptions.map(([val, lbl, desc]) => (
											<label
												key={String(val)}
												title={desc}
												style={{
													display: "flex",
													alignItems: "center",
													gap: 4,
													fontSize: 12,
													cursor: "pointer",
													padding: "3px 8px",
													borderRadius: 4,
													background:
														data.showHH === val
															? "var(--color-accent-bg)"
															: "transparent",
													border: `1px solid ${data.showHH === val ? "var(--color-accent)" : "var(--color-border)"}`,
												}}>
												<input
													type="radio"
													name="showHH"
													checked={data.showHH === val}
													onChange={() => set("showHH", val)}
													style={{ accentColor: "var(--color-accent)" }}
												/>
												{lbl}
											</label>
										))}
									</div>
								</div>
								{isLastIdx && (
									<label
										style={{
											display: "flex",
											alignItems: "center",
											gap: 6,
											fontSize: 13,
											cursor: "pointer",
											gridColumn: "span 2",
											padding: "6px 8px",
											background: "var(--color-content)",
											borderRadius: 4,
											border: "1px solid var(--color-border)",
										}}>
										<input
											type="checkbox"
											checked={lastRowExclude}
											onChange={(e) =>
												set("isLastStop", !e.target.checked)
											}
											style={{ accentColor: "var(--color-accent)" }}
										/>
										<span>
											この行を<strong>終着駅にしない</strong>
										</span>
										<span
											style={{
												fontSize: 11,
												color: "var(--color-text-muted)",
												marginLeft: "auto",
											}}>
											（既定: 最後の行 = 終着）
										</span>
									</label>
								)}
							</div>

							{/* 制限・作業 */}
							<div className="field-row" style={{ marginBottom: 12 }}>
								<div className="field">
									<label>進入制限 (0–999)</label>
									<NumLimit
										value={data.runInLimit}
										onChange={(v) => set("runInLimit", v)}
									/>
								</div>
								<div className="field">
									<label>進出制限 (0–999)</label>
									<NumLimit
										value={data.runOutLimit}
										onChange={(v) => set("runOutLimit", v)}
									/>
								</div>
								<div className="field" style={{ flex: "0 0 160px" }}>
									<label>駅作業タイプ</label>
									<input
										value={data.workType || ""}
										onChange={(e) => set("workType", e.target.value)}
										placeholder="—"
									/>
								</div>
							</div>

							<BBCodeField
								label={t.remarks}
								value={data.remarks || ""}
								onChange={(v) => set("remarks", v)}
								multiline
								rows={2}
							/>
					</>
				</div>
				<div className="modal-footer">
					<button className="btn btn-secondary" onClick={onClose}>
						{t.cancel}
					</button>
					<button
						className="btn btn-primary"
						onClick={() => {
							onSave(isFirstRow ? data : { ...data, hasBracket: false });
							onClose();
						}}>
						{t.save}
					</button>
				</div>
			</div>
		</div>
	);
}

// Per-row remarks input with local draft to avoid per-keystroke API writes.
// Track picker for one row, scoped to the row's station (station_tracks belong
// to a station). The available-track list is fetched ON DEMAND — only when the
// picker is opened — so opening a timetable doesn't fan out a tracks query per
// station. Display uses the backend-embedded row.trackName; a soft-deleted
// track keeps its id selected and surfaces as a "(削除済み)" tombstone.
function TrackCell({
	row,
	onChange,
	t,
}: {
	row: TimetableRow;
	onChange: (id: string | undefined) => void;
	t: Strings;
}) {
	const [open, setOpen] = useState(false);
	const stationId = row.stationId ?? "";
	const { data: tracks } = useStationTracks(stationId, open && stationId !== "");
	const deleted = !!row.trackDeleted;

	if (!open) {
		return (
			<button
				type="button"
				className="track-pick"
				disabled={stationId === ""}
				onClick={() => setOpen(true)}
				title={
					deleted
						? `${row.trackName}（${t.deleted}）`
						: row.trackName || t.none
				}
				style={
					deleted
						? {
								color: "var(--color-danger)",
								textDecoration: "line-through",
							}
						: undefined
				}>
				{row.trackName ? (
					row.trackName
				) : (
					<span style={{ opacity: 0.4 }}>—</span>
				)}
				{deleted && (
					<span style={{ marginLeft: 3, textDecoration: "none" }}>⚠</span>
				)}
			</button>
		);
	}
	return (
		<select
			className="color-select"
			autoFocus
			value={row.stationTrackId ?? ""}
			style={deleted ? { color: "var(--color-danger)" } : undefined}
			onChange={(e) => {
				onChange(e.target.value === "" ? undefined : e.target.value);
				setOpen(false);
			}}
			onBlur={() => setOpen(false)}>
			<option value="">— {t.none} —</option>
			{deleted && row.stationTrackId !== undefined && (
				<option value={row.stationTrackId}>
					⚠ {row.trackName}（{t.deleted}）
				</option>
			)}
			{(tracks ?? []).map((tr) => (
				<option key={tr.id} value={tr.id}>
					{tr.name}
				</option>
			))}
		</select>
	);
}

// Marker-color picker + swatch for one row. Resolves the swatch from the
// project color list; when the referenced color is soft-deleted it keeps the
// id selected and surfaces it as a "(削除済み)" tombstone option (the name
// comes from the backend-embedded row.colorName).
function ColorCell({
	row,
	colors,
	onChange,
	t,
}: {
	row: TimetableRow;
	colors: EntityColor[];
	onChange: (id: string | undefined) => void;
	t: Strings;
}) {
	const selected = row.colorIdMarker
		? colors.find((c) => c.id === row.colorIdMarker)
		: undefined;
	const deleted = !!row.colorDeleted;
	const swatch = selected
		? `rgb(${selected.red}, ${selected.green}, ${selected.blue})`
		: undefined;
	return (
		<div style={{ display: "flex", alignItems: "center", gap: 4 }}>
			<span
				title={
					deleted
						? `${row.colorName ?? ""}（${t.deleted}）`
						: (selected?.name ?? t.none)
				}
				style={{
					width: 14,
					height: 14,
					borderRadius: 3,
					flexShrink: 0,
					background: swatch ?? "transparent",
					border: deleted
						? "1px solid var(--color-danger)"
						: "1px solid var(--color-border)",
				}}
			/>
			<select
				className="color-select"
				value={row.colorIdMarker ?? ""}
				style={deleted ? { color: "var(--color-danger)" } : undefined}
				onChange={(e) =>
					onChange(e.target.value === "" ? undefined : e.target.value)
				}>
				<option value="">— {t.none} —</option>
				{deleted && row.colorIdMarker !== undefined && (
					<option value={row.colorIdMarker}>
						⚠ {row.colorName ?? ""}（{t.deleted}）
					</option>
				)}
				{colors.map((c) => (
					<option key={c.id} value={c.id}>
						{c.name}
					</option>
				))}
			</select>
		</div>
	);
}

interface StationRowProps {
	row: TimetableRow;
	idx: number;
	isLast: boolean;
	fmt: Partial<RowFormat>;
	colors: EntityColor[];
	onUpdateRow: <K extends keyof TimetableRow>(idx: number, key: K, val: TimetableRow[K]) => void;
	onUpdateRowFields: (idx: number, partial: Partial<TimetableRow>) => void;
	onDeleteRow: (idx: number) => void;
	onOpenDetail: () => void;
	t: Strings;
}

function StationRow({ row, idx, isLast, fmt, colors, onUpdateRow, onUpdateRowFields, onDeleteRow, onOpenDetail, t }: StationRowProps) {
	const [remarksDraft, setRemarksDraft] = useState(row.remarks);

	// Reseed when the row data changes (e.g. after a mutation refetch).
	const prevRemarksRef = useRef(row.remarks);
	if (prevRemarksRef.current !== row.remarks) {
		prevRemarksRef.current = row.remarks;
		setRemarksDraft(row.remarks);
	}

	const rowStyle: CSSProperties = row.isPass
		? { background: "var(--color-pass-bg)" }
		: row.isOperationOnlyStop
			? { background: "var(--color-oponly-bg)" }
			: isLast
				? { background: "var(--color-laststop-bg)" }
				: {};

	return (
		<tr key={row.id} style={rowStyle}>
			<td className="row-num">
				{idx + 1}
				{isLast && (
					<div
						style={{
							fontSize: 9,
							color: "var(--color-success)",
							fontWeight: 600,
							marginTop: -2,
						}}>
						終
					</div>
				)}
			</td>
			<td>
				<div
					className="station-cell"
					onDoubleClick={onOpenDetail}
					title={
						row.stationDeleted
							? "この駅は削除されています"
							: "ダブルクリックで詳細編集"
					}>
					<span
						className={`station-name ${!row.stationName ? "empty" : ""} ${row.stationDeleted ? "tombstone" : ""}`}>
						{row.stationName || "(駅名未設定)"}
					</span>
					{row.stationDeleted && (
						<span className="tombstone-tag" title="この駅は削除されています">
							削除済み
						</span>
					)}
				</div>
			</td>
			<td style={row.isPass ? { color: "oklch(0.55 0.12 15)" } : {}}>
				<TimeCell
					value={row.arrive}
					displayText={row.arriveDisplayText || undefined}
					formattedValue={row.arriveDisplayText ? undefined : fmt.arriveFormatted}
					onChange={(v) => onUpdateRowFields(idx, { arrive: v, arriveDisplayText: "" })}
					onChangeText={(v) => onUpdateRowFields(idx, { arrive: "", arriveDisplayText: v })}
					muted={!!row.arriveHidden}
				/>
			</td>
			<td style={row.isPass ? { color: "oklch(0.55 0.12 15)" } : {}}>
				{isLast && !row.departure && !row.departureDisplayText ? (
					<span
						className="time-display"
						style={{
							textAlign: "center",
							justifyContent: "center",
							opacity: 0.35,
							fontFamily: "var(--font-mono)",
							fontSize: 12,
							cursor: "default",
						}}>
						{"=="}
					</span>
				) : (
					<TimeCell
						value={row.departure}
						displayText={row.departureDisplayText || undefined}
						formattedValue={row.departureDisplayText ? undefined : fmt.departureFormatted}
						onChange={(v) => onUpdateRowFields(idx, { departure: v, departureDisplayText: "" })}
						onChangeText={(v) => onUpdateRowFields(idx, { departure: "", departureDisplayText: v })}
						muted={!!row.departureHidden}
					/>
				)}
			</td>
			<td className="toggle-cell">
				<input
					type="checkbox"
					className="toggle-check"
					checked={!!row.isPass}
					onChange={(e) => onUpdateRow(idx, "isPass", e.target.checked)}
				/>
			</td>
			<td>
				<TrackCell
					row={row}
					onChange={(id) => onUpdateRow(idx, "stationTrackId", id)}
					t={t}
				/>
			</td>
			<td>
				<ColorCell
					row={row}
					colors={colors}
					onChange={(id) => onUpdateRow(idx, "colorIdMarker", id)}
					t={t}
				/>
			</td>
			<td>
				<div style={{ display: "flex", alignItems: "center", gap: 3 }}>
					<input
						className="remarks-input"
						value={remarksDraft || ""}
						onChange={(e) => setRemarksDraft(e.target.value)}
						onBlur={(e) => {
							const v = e.target.value;
							if (v !== row.remarks) {
								onUpdateRow(idx, "remarks", v);
							}
						}}
						placeholder="—"
					/>
					<BBCodeEditButton
						title="記事"
						value={remarksDraft || ""}
						onChange={(v) => {
							setRemarksDraft(v);
							onUpdateRow(idx, "remarks", v);
						}}
						multiline={false}
					/>
				</div>
			</td>
			<td>
				<div className="actions-cell">
					<button
						className="row-action-btn"
						onClick={onOpenDetail}
						title={t.detail}>
						⚙
					</button>
					<button
						className="row-action-btn del"
						onClick={() => onDeleteRow(idx)}
						title={t.delete}>
						✕
					</button>
				</div>
			</td>
		</tr>
	);
}

interface StationPickerModalProps {
	stations: Station[];
	usedStationIds: Set<string>;
	onPick: (station: Station) => void;
	onClose: () => void;
}

function StationPickerModal({ stations, usedStationIds, onPick, onClose }: StationPickerModalProps) {
	const [filter, setFilter] = useState("");
	const filtered = stations.filter((s) => {
		if (usedStationIds.has(s.id)) return false;
		if (filter === "") return true;
		const q = filter.toLowerCase();
		return (
			s.stationName.toLowerCase().includes(q) ||
			(s.fullName || "").toLowerCase().includes(q)
		);
	});

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div className="modal" style={{ maxWidth: 400, width: "100%" }}>
				<div className="modal-header">
					<span className="modal-title">駅を選択</span>
					<button className="btn btn-ghost btn-sm" onClick={onClose}>✕</button>
				</div>
				<div className="modal-body" style={{ padding: "8px 12px" }}>
					<input
						autoFocus
						value={filter}
						onChange={(e) => setFilter(e.target.value)}
						placeholder="駅名で絞り込み…"
						style={{
							width: "100%",
							padding: "6px 10px",
							border: "1px solid var(--color-border)",
							borderRadius: "var(--radius)",
							background: "var(--color-content)",
							color: "var(--color-text)",
							fontFamily: "inherit",
							fontSize: 13,
							marginBottom: 8,
						}}
					/>
					<div style={{ maxHeight: 320, overflowY: "auto" }}>
						{filtered.length === 0 ? (
							<div style={{ padding: "20px 0", textAlign: "center", color: "var(--color-text-muted)", fontSize: 12 }}>
								{stations.length === 0
									? "このプロジェクトには駅が登録されていません"
									: "該当する駅がありません"}
							</div>
						) : (
							filtered.map((s) => (
								<button
									key={s.id}
									onClick={() => onPick(s)}
									style={{
										display: "block",
										width: "100%",
										padding: "8px 10px",
										border: "none",
										borderBottom: "1px solid var(--color-border)",
										background: "transparent",
										textAlign: "left",
										cursor: "pointer",
										color: "var(--color-text)",
									}}>
									<span style={{ fontWeight: 500 }}>{s.stationName}</span>
									{s.fullName && s.fullName !== s.stationName && (
										<span style={{ fontSize: 11, color: "var(--color-text-muted)", marginLeft: 6 }}>
											{s.fullName}
										</span>
									)}
								</button>
							))
						)}
					</div>
				</div>
			</div>
		</div>
	);
}

interface TimetableGridProps {
	train: Train;
	stations: Station[];
	colors: EntityColor[];
	onCreateRow: (row: TimetableRow) => void;
	onUpdateRow: (rowId: string, row: TimetableRow) => void;
	onDeleteRow: (rowId: string) => void;
	t: Strings;
}

export function TimetableGrid({ train, stations, colors, onCreateRow, onUpdateRow, onDeleteRow, t }: TimetableGridProps) {
	const rows = train.timetableRows ?? [];
	const [detailRow, setDetailRow] = useState<TimetableRow | null>(null);
	const [showPicker, setShowPicker] = useState(false);

	const rowFormats = useMemo(
		() => TRViSTime.computeRowFormats(rows),
		[rows]
	);

	const lastStationIdx = rows.length - 1;

	const effectiveLastStop = (row: TimetableRow, idx: number): boolean => {
		if (idx === lastStationIdx) return row.isLastStop !== false;
		return !!row.isLastStop;
	};

	const updateRow = useCallback(
		<K extends keyof TimetableRow>(
			idx: number,
			key: K,
			val: TimetableRow[K]
		) => {
			const row = rows[idx];
			if (!row) return;
			const updated = { ...row, [key]: val };
			onUpdateRow(updated.id, updated);
		},
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[rows, onUpdateRow]
	);

	// Apply several fields in ONE update (one spread, one PUT). Needed where two
	// related fields change together (time vs its display-text): two single-key
	// updateRow calls would each read the same stale rows[idx] and the second
	// would clobber the first.
	const updateRowFields = useCallback(
		(idx: number, partial: Partial<TimetableRow>) => {
			const row = rows[idx];
			if (!row) return;
			onUpdateRow(row.id, { ...row, ...partial });
		},
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[rows, onUpdateRow]
	);

	const deleteRow = (idx: number) => {
		const row = rows[idx];
		if (!row) return;
		onDeleteRow(row.id);
	};

	const saveDetail = (updated: TimetableRow) => {
		onUpdateRow(updated.id, updated);
	};

	return (
		<div
			className="timetable-grid-wrap"
			style={{ display: "flex", flexDirection: "column", height: "100%" }}>
			<style>{`
        .timetable-grid-wrap { font-size: 13px; }
        .tgrid { border-collapse: collapse; width: 100%; table-layout: fixed; }
        .tgrid th {
          position: sticky; top: 0; z-index: 10;
          background: var(--color-content); border-bottom: 2px solid var(--color-border);
          border-right: 1px solid var(--color-border);
          padding: 0 6px; height: 32px; font-size: 11px; font-weight: 600;
          text-align: center; color: var(--color-text-muted); user-select: none;
          letter-spacing: 0.04em; white-space: nowrap;
        }
        .tgrid th.lh { text-align: left; padding-left: 10px; }
        .tgrid td {
          border-bottom: 1px solid var(--color-border);
          border-right: 1px solid var(--color-border);
          height: var(--row-h); padding: 0 6px; vertical-align: middle; position: relative;
        }
        .tgrid td:last-child { border-right: none; }
        .tgrid tr:hover td { background: color-mix(in srgb, var(--color-accent-bg) 30%, transparent); }
        .tgrid tr.drag-over td { border-top: 2px solid var(--color-accent); }
        .row-num { font-size: 10px; color: var(--color-text-muted); text-align: center; user-select: none; width: 28px; }
        .station-cell { display: flex; align-items: center; gap: 5px; padding-left: 2px; }
        .station-name { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
        .station-name.empty { color: var(--color-text-muted); font-weight: 400; font-style: italic; }
        .station-name.tombstone { color: var(--color-danger); text-decoration: line-through; text-decoration-color: color-mix(in srgb, var(--color-danger) 55%, transparent); }
        .tombstone-tag { flex-shrink: 0; font-size: 9px; font-weight: 600; line-height: 1; padding: 2px 4px; border-radius: 3px; color: var(--color-danger); background: color-mix(in srgb, var(--color-danger) 14%, transparent); border: 1px solid color-mix(in srgb, var(--color-danger) 40%, transparent); white-space: nowrap; }
        .drag-handle { cursor: grab; opacity: 0.3; font-size: 13px; flex-shrink: 0; }
        .drag-handle:hover { opacity: 0.7; }
        .tgrid tr:active .drag-handle { cursor: grabbing; }
        .time-display { font-family: var(--font-mono); font-size: 12px; display: block; text-align: center; cursor: text; width: 100%; padding: 4px 2px; border-radius: 3px; }
        .time-display:hover { background: var(--color-accent-bg); }
        .toggle-cell { text-align: center; vertical-align: middle; }
        .toggle-check { width: 14px; height: 14px; cursor: pointer; accent-color: var(--color-accent); }
        .remarks-input { border: none; outline: none; background: transparent; width: 100%; font-size: 12px; color: var(--color-text); font-family: inherit; padding: 0; }
        .remarks-input:focus { outline: 1px solid var(--color-accent); border-radius: 3px; }
        .tgrid tbody tr:last-child td { border-bottom: none; }
        .row-action-btn { padding: 3px 6px; border-radius: 3px; font-size: 11px; background: var(--color-bg); border: 1px solid var(--color-border); color: var(--color-text-muted); cursor: pointer; line-height: 1; }
        .row-action-btn:hover { background: var(--color-accent-bg); color: var(--color-accent); border-color: var(--color-accent); }
        .row-action-btn.del:hover { background: #fee2e2; color: var(--color-danger); border-color: var(--color-danger); }
        .track-input { border: none; outline: none; background: transparent; text-align: center; width: 100%; font-size: 12px; font-family: var(--font-mono); color: inherit; }
        .track-input:focus { outline: 1px solid var(--color-accent); border-radius: 3px; }
        .color-select { flex: 1; min-width: 0; border: none; outline: none; background: transparent; font-size: 12px; color: inherit; cursor: pointer; padding: 2px 0; }
        .color-select:focus { outline: 1px solid var(--color-accent); border-radius: 3px; }
        .track-pick { width: 100%; border: none; background: transparent; text-align: left; font-size: 12px; color: inherit; cursor: pointer; padding: 3px 4px; border-radius: 3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .track-pick:hover:not(:disabled) { background: var(--color-accent-bg); }
        .track-pick:disabled { cursor: default; opacity: 0.5; }
        .tgrid-scroll { flex: 1; overflow: auto; }
        .add-row-bar { padding: 8px 12px; border-top: 1px solid var(--color-border); background: var(--color-content); display: flex; align-items: center; gap: 12px; }
        .actions-cell { display: flex; gap: 3px; justify-content: flex-end; }
      `}</style>

			<div className="tgrid-scroll">
				<table className="tgrid">
					<colgroup>
						<col style={{ width: 28 }} />
						<col style={{ width: 128 }} />
						<col style={{ width: 80 }} />
						<col style={{ width: 80 }} />
						<col style={{ width: 42 }} />
						<col style={{ width: 88 }} />
						<col style={{ width: 104 }} />
						<col style={{ width: "auto" }} />
						<col style={{ width: 64 }} />
					</colgroup>
					<thead>
						<tr>
							<th>#</th>
							<th className="lh">{t.stationName}</th>
							<th>{t.arrive}</th>
							<th>{t.depart}</th>
							<th title={t.pass}>通</th>
							<th className="lh">{t.track}</th>
							<th className="lh">{t.color}</th>
							<th className="lh">{t.remarks}</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						{rows.map((row, idx) => {
							const isLast = effectiveLastStop(row, idx);
							const fmt: Partial<RowFormat> = rowFormats[idx] ?? {};

							return (
								<StationRow
									key={row.id}
									row={row}
									idx={idx}
									isLast={isLast}
									fmt={fmt}
									colors={colors}
									onUpdateRow={updateRow}
									onUpdateRowFields={updateRowFields}
									onDeleteRow={deleteRow}
									onOpenDetail={() => setDetailRow(row)}
									t={t}
								/>
							);
						})}
					</tbody>
				</table>
			</div>

			<div className="add-row-bar">
				<button
					className="btn btn-secondary btn-sm"
					onClick={() => setShowPicker(true)}>
					＋ {t.addRow}
				</button>
				{rows.length > 0 && (
					<span
						style={{ fontSize: 11, color: "var(--color-text-muted)" }}>
						{rows.length} 駅 · 通過{" "}
						{rows.filter((r) => r.isPass).length} · 運停{" "}
						{rows.filter((r) => r.isOperationOnlyStop).length}
					</span>
				)}
				<span style={{ flex: 1 }} />
				<span style={{ fontSize: 11, color: "var(--color-text-muted)" }}>
					※ 最後の行は自動的に終着駅扱いになります
				</span>
			</div>

			{detailRow && (
				<RowDetailModal
					row={detailRow}
					isFirstRow={rows[0]?.id === detailRow.id}
					isLastIdx={rows[lastStationIdx]?.id === detailRow.id}
					allRows={rows}
					t={t}
					onSave={saveDetail}
					onClose={() => setDetailRow(null)}
				/>
			)}

			{showPicker && (
				<StationPickerModal
					stations={stations}
					usedStationIds={new Set(rows.map((r) => r.stationId ?? "").filter(Boolean))}
					onPick={(picked) => {
						const newRow: TimetableRow = {
							id: "",
							stationId: picked.id,
							stationName: picked.stationName,
							fullName: picked.fullName,
							arrive: "",
							departure: "",
							trackName: "",
							isPass: false,
							isOperationOnlyStop: false,
							hasBracket: false,
							isLastStop: undefined,
							recordType: "station",
							driveTime_MM: 0,
							driveTime_SS: 0,
							runInLimit: "",
							runOutLimit: "",
							remarks: "",
							workType: "",
						};
						onCreateRow(newRow);
						setShowPicker(false);
					}}
					onClose={() => setShowPicker(false)}
				/>
			)}
		</div>
	);
}
