// ApplyPatternDialog.tsx — Create/apply a train by chaining multiple stop patterns
// Ported 1:1 from the design prototype ApplyPatternDialog.jsx.
import { useState, useMemo, Fragment } from "react";
import type { CSSProperties } from "react";
import { TRViSTime } from "../lib/timeUtils";
import type {
	Line,
	Station,
	StationOnLine,
	StopPattern,
	StopPatternRow,
	Train,
	TimetableRow,
	Direction,
} from "../types/model";
import type { Strings } from "../i18n/strings";

/* ── Time helpers (use TRViSTime) ────────────────────────────────────────── */
const toSec = (s: string | null | undefined): number | null =>
	TRViSTime.toSeconds(s);
const fmtSec = (s: number | null | undefined): string =>
	TRViSTime.fromSeconds(s);

/* A flattened, computed timetable row built from a chain of patterns. */
interface ChainRow {
	stationId: string;
	stationName: string;
	fullName: string;
	alwaysShowHH: boolean;
	trackName: string;
	trackHidden: boolean;
	isPass: boolean;
	isOperationOnlyStop: boolean;
	driveTime_MM: number;
	driveTime_SS: number;
	dwellTime_MM: number;
	dwellTime_SS: number;
}

interface PreviewRow extends ChainRow {
	arrive: string;
	departure: string;
}

type ApplyMode = "new" | "replace" | "append" | "prepend";

/** AppliedRow extends TimetableRow with the stationId that must be sent to the API. */
export type AppliedRow = TimetableRow & { stationId: string };

/* ── Build flat station row list from one pattern ─────────────────────────── */
function patternStations(
	pattern: StopPattern,
	stations: Station[],
	stationsOnLine: StationOnLine[]
): ChainRow[] {
	const lineSols = stationsOnLine
		.filter(sol => sol.lineId === pattern.lineId)
		.sort((a, b) => (a.location_m || 0) - (b.location_m || 0));
	const lineEntries = lineSols
		.map(sol => ({
			sol,
			station: stations.find(s => s.id === sol.stationId),
		}))
		.filter(
			(x): x is { sol: StationOnLine; station: Station } =>
				Boolean(x.station)
		);

	const fi = lineEntries.findIndex(
		x => x.station.id === pattern.fromStationId
	);
	const ti = lineEntries.findIndex(
		x => x.station.id === pattern.toStationId
	);
	if (fi < 0 || ti < 0 || fi === ti) return [];
	const ordered =
		fi < ti
			? lineEntries.slice(fi, ti + 1)
			: [...lineEntries.slice(ti, fi + 1)].reverse();

	return ordered.map(({ sol, station: st }) => {
		const row: Partial<StopPatternRow> =
			(pattern.stopRows || []).find(r => r.stationId === st.id) || {};
		return {
			stationId: st.id,
			stationName: st.stationName,
			fullName: st.fullName || st.stationName,
			alwaysShowHH: !!st.alwaysShowHH,
			trackName: row.trackName || "1",
			trackHidden:
				row.trackHidden !== undefined
					? !!row.trackHidden
					: !!sol.trackHiddenByDefault,
			isPass: !!row.isPass,
			isOperationOnlyStop: !!row.isOperationOnlyStop,
			driveTime_MM: row.driveTime_MM || 0,
			driveTime_SS: row.driveTime_SS || 0,
			dwellTime_MM: row.dwellTime_MM || 0,
			dwellTime_SS: row.dwellTime_SS || 0,
		};
	});
}

/* ── Merge chained patterns, deduplicate boundary stations ────────────────── */
function buildChain(
	patterns: StopPattern[],
	stations: Station[],
	stationsOnLine: StationOnLine[]
): ChainRow[] {
	const result: ChainRow[] = [];
	for (const p of patterns) {
		const rows = patternStations(p, stations, stationsOnLine);
		if (rows.length === 0) continue;
		if (result.length === 0) {
			result.push(...rows);
		} else {
			const first = rows[0];
			const last = result[result.length - 1];
			// skip first row of new segment if it matches last row of current chain
			result.push(
				...(first && last && first.stationId === last.stationId
					? rows.slice(1)
					: rows)
			);
		}
	}
	return result;
}

/* ── Compute arrival/departure times ─────────────────────────────────────── */
// junctionDwells: { stationId: totalSeconds } — extra dwell at chain boundaries
function computeTimes(
	chainRows: ChainRow[],
	startDepStr: string,
	junctionDwells: Record<string, number> = {}
): PreviewRow[] {
	const startSec = toSec(startDepStr);
	if (startSec == null || chainRows.length === 0) return [];
	const result: PreviewRow[] = [];
	let curDepSec = startSec;

	for (let i = 0; i < chainRows.length; i++) {
		const row = chainRows[i];
		if (!row) continue;
		const isFirst = i === 0;
		const isLast = i === chainRows.length - 1;
		const driveSec = row.driveTime_MM * 60 + row.driveTime_SS;
		const dwellSec = row.dwellTime_MM * 60 + row.dwellTime_SS;
		// Extra dwell from junction override (only relevant for boundary stations in chain)
		const junctionSec =
			junctionDwells[row.stationId] != null
				? junctionDwells[row.stationId]
				: null;

		if (isFirst) {
			result.push({
				...row,
				arrive: "",
				departure: fmtSec(startSec),
			});
			curDepSec = startSec;
		} else {
			const arrSec = curDepSec + driveSec;
			if (row.isPass) {
				result.push({
					...row,
					arrive: fmtSec(arrSec),
					departure: fmtSec(arrSec),
				});
				curDepSec = arrSec;
			} else {
				const effectiveDwell =
					junctionSec != null ? junctionSec : dwellSec;
				const depSec = arrSec + effectiveDwell;
				result.push({
					...row,
					arrive: fmtSec(arrSec),
					departure: isLast ? "" : fmtSec(depSec),
				});
				curDepSec = depSec;
			}
		}
	}
	return result;
}

/* ── Small number input ───────────────────────────────────────────────────── */
interface NumInputProps {
	value: number;
	onChange: (v: number) => void;
	min?: number;
	max?: number;
	disabled?: boolean;
	style?: CSSProperties;
}

function NumInput({
	value,
	onChange,
	min = 0,
	max = 999,
	disabled,
	style,
}: NumInputProps) {
	return (
		<input
			type="number"
			value={disabled ? "" : value}
			min={min}
			max={max}
			disabled={disabled}
			onChange={e => onChange(+e.target.value)}
			style={{
				width: "100%",
				textAlign: "center",
				border: "1px solid var(--color-border)",
				borderRadius: 3,
				padding: "2px 4px",
				background: disabled
					? "var(--color-bg)"
					: "var(--color-content)",
				color: disabled
					? "var(--color-text-muted)"
					: "var(--color-text)",
				fontFamily: "var(--font-mono)",
				fontSize: 12,
				...style,
			}}
		/>
	);
}

/* ── Pattern chip (in chain) ─────────────────────────────────────────────── */
interface PatternChipProps {
	pattern: StopPattern;
	stations: Station[];
	onRemove: () => void;
}

function PatternChip({
	pattern,
	stations,
	onRemove,
}: PatternChipProps) {
	const fromSt = stations.find(s => s.id === pattern.fromStationId);
	const toSt = stations.find(s => s.id === pattern.toStationId);
	return (
		<div
			style={{
				display: "flex",
				alignItems: "center",
				gap: 6,
				padding: "4px 10px",
				background: "var(--color-accent-bg)",
				border: "1px solid var(--color-accent)",
				borderRadius: "var(--radius)",
				fontSize: 12,
				flexShrink: 0,
				whiteSpace: "nowrap",
			}}
		>
			<span
				style={{
					color: "var(--color-accent)",
					fontWeight: 600,
				}}
			>
				{pattern.name || "(無名)"}
			</span>
			<span style={{ color: "var(--color-text-muted)" }}>
				{fromSt?.stationName}→{toSt?.stationName}
			</span>
			<button
				onClick={onRemove}
				style={{
					marginLeft: 2,
					background: "none",
					border: "none",
					cursor: "pointer",
					color: "var(--color-text-muted)",
					fontSize: 13,
					padding: "0 2px",
					lineHeight: 1,
				}}
			>
				✕
			</button>
		</div>
	);
}

/* ── Main dialog ─────────────────────────────────────────────────────────── */
// mode: 'new' | 'prepend' | 'append' | 'replace'
export interface ApplyPatternDialogProps {
	stopPatterns: StopPattern[];
	stations: Station[];
	stationsOnLine: StationOnLine[];
	lines: Line[];
	t: Strings;
	existingTrain: Train | null;
	onApply: (r: {
		rows: AppliedRow[];
		direction: Direction;
		destination: string;
		mode?: "replace" | "append" | "prepend";
	}) => void;
	onClose: () => void;
}

export function ApplyPatternDialog({
	stopPatterns,
	stations,
	stationsOnLine,
	lines,
	onApply,
	onClose,
	t,
	existingTrain,
}: ApplyPatternDialogProps) {
	const [chain, setChain] = useState<string[]>([]);
	const [startDep, setStartDep] = useState("09:00:00");
	const [filterLineId, setFilterLine] = useState("");
	const [junctionDwells, setJDwells] = useState<
		Record<string, number>
	>({}); // stationId → seconds
	const [applyMode, setApplyMode] = useState<ApplyMode>(
		existingTrain ? "replace" : "new"
	);

	const addPattern = (id: string) => {
		if (!chain.includes(id)) setChain(c => [...c, id]);
	};
	const removePattern = (idx: number) =>
		setChain(c => c.filter((_, i) => i !== idx));
	const moveUp = (idx: number) => {
		if (idx === 0) return;
		setChain(c => {
			const n = [...c];
			const a = n[idx - 1] as string;
			const b = n[idx] as string;
			n[idx - 1] = b;
			n[idx] = a;
			return n;
		});
	};
	const moveDown = (idx: number) =>
		setChain(c => {
			if (idx >= c.length - 1) return c;
			const n = [...c];
			const a = n[idx] as string;
			const b = n[idx + 1] as string;
			n[idx] = b;
			n[idx + 1] = a;
			return n;
		});

	const chainPatterns = chain
		.map(id => stopPatterns.find(p => p.id === id))
		.filter((p): p is StopPattern => Boolean(p));

	// Detect boundary stations (last of pattern[i] = first of pattern[i+1])
	const boundaryIds = useMemo(() => {
		const ids = new Set<string>();
		for (let i = 0; i < chainPatterns.length - 1; i++) {
			const a = chainPatterns[i];
			const b = chainPatterns[i + 1];
			if (!a || !b) continue;
			const aRows = patternStations(a, stations, stationsOnLine);
			const bRows = patternStations(b, stations, stationsOnLine);
			const aLast = aRows[aRows.length - 1];
			const bFirst = bRows[0];
			if (
				aRows.length &&
				bRows.length &&
				aLast &&
				bFirst &&
				aLast.stationId === bFirst.stationId
			) {
				ids.add(aLast.stationId);
			}
		}
		return ids;
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [chain, stations, stationsOnLine]);

	const chainRows = useMemo(
		() => buildChain(chainPatterns, stations, stationsOnLine),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[chain, stations, stationsOnLine]
	);

	// Normalize startDep on blur
	const normalizeStart = () => {
		const n = TRViSTime.normalize(startDep);
		if (n) setStartDep(n);
	};

	const previewRows = useMemo(
		() => computeTimes(chainRows, startDep, junctionDwells),
		[chainRows, startDep, junctionDwells]
	);

	const availablePatterns = stopPatterns.filter(
		p => !filterLineId || p.lineId === filterLineId
	);

	// Infer direction from first pattern
	const inferDirection = (): Direction => {
		const firstP = chainPatterns[0];
		if (!firstP) return 1;
		const lineSols = stationsOnLine
			.filter(sol => sol.lineId === firstP.lineId)
			.sort(
				(a, b) => (a.location_m || 0) - (b.location_m || 0)
			);
		const ls = lineSols
			.map(sol => stations.find(s => s.id === sol.stationId))
			.filter((s): s is Station => Boolean(s));
		const fi = ls.findIndex(s => s.id === firstP.fromStationId);
		const ti = ls.findIndex(s => s.id === firstP.toStationId);
		return fi >= 0 && ti >= 0 ? (fi < ti ? 1 : -1) : 1;
	};

	const handleApply = () => {
		if (previewRows.length === 0) return;
		const rows: AppliedRow[] = previewRows.map((r, i) => {
			const tr: AppliedRow = {
				id: "r" + Date.now() + i,
				stationId: r.stationId,
				stationName: r.stationName,
				fullName: r.fullName,
				arrive: r.arrive,
				departure: r.departure,
				trackName: r.trackName,
				trackHidden: r.trackHidden || false,
				isPass: r.isPass,
				isOperationOnlyStop: r.isOperationOnlyStop,
				hasBracket: i === 0,
				recordType: "station",
				driveTime_MM: r.driveTime_MM,
				driveTime_SS: r.driveTime_SS,
				runInLimit: "",
				runOutLimit: "",
				remarks: "",
				workType: "",
			};
			// alwaysShowHH が ON の駅は showHH:true で追加
			if (r.alwaysShowHH) tr.showHH = true;
			return tr;
		});
		const direction = inferDirection();
		const destination =
			previewRows[previewRows.length - 1]?.stationName || "";
		const result: {
			rows: AppliedRow[];
			direction: Direction;
			destination: string;
			mode?: "replace" | "append" | "prepend";
		} = { rows, direction, destination };
		if (applyMode !== "new") result.mode = applyMode;
		onApply(result);
		onClose();
	};

	const setJD = (stationId: string, secs: number) =>
		setJDwells(d => ({ ...d, [stationId]: secs }));

	return (
		<div
			className="modal-backdrop"
			onClick={e => e.target === e.currentTarget && onClose()}
		>
			<div
				className="modal"
				style={{
					maxWidth: 920,
					width: "100%",
					height: "88vh",
					display: "flex",
					flexDirection: "column",
				}}
			>
				<div className="modal-header">
					<span className="modal-title">
						🧩 停車パターンから列車を
						{existingTrain ? "編集" : "作成"}
					</span>
					<button
						className="btn btn-ghost btn-sm"
						onClick={onClose}
					>
						✕
					</button>
				</div>

				<div
					style={{
						flex: 1,
						display: "grid",
						gridTemplateColumns: "260px 1fr",
						overflow: "hidden",
					}}
				>
					{/* ── Left: pattern library ──────────────────────────────────── */}
					<div
						style={{
							borderRight: "1px solid var(--color-border)",
							display: "flex",
							flexDirection: "column",
							overflow: "hidden",
						}}
					>
						<div
							style={{
								padding: "10px 12px",
								borderBottom:
									"1px solid var(--color-border)",
							}}
						>
							<div
								style={{
									fontSize: 11,
									fontWeight: 600,
									color: "var(--color-text-muted)",
									textTransform: "uppercase",
									letterSpacing: "0.06em",
									marginBottom: 6,
								}}
							>
								停車パターン一覧
							</div>
							<select
								value={filterLineId}
								onChange={e =>
									setFilterLine(e.target.value)
								}
								style={{
									width: "100%",
									padding: "5px 8px",
									border:
										"1px solid var(--color-border)",
									borderRadius: "var(--radius)",
									background: "var(--color-content)",
									color: "var(--color-text)",
									fontSize: 12,
								}}
							>
								<option value="">すべての路線</option>
								{lines.map(l => (
									<option key={l.id} value={l.id}>
										{l.name}
									</option>
								))}
							</select>
						</div>
						<div style={{ flex: 1, overflow: "auto" }}>
							{availablePatterns.length === 0 && (
								<div
									style={{
										padding: 16,
										fontSize: 12,
										color: "var(--color-text-muted)",
									}}
								>
									停車パターンがありません。路線・駅管理から作成してください。
								</div>
							)}
							{availablePatterns.map(p => {
								const line = lines.find(
									l => l.id === p.lineId
								);
								const fromSt = stations.find(
									s => s.id === p.fromStationId
								);
								const toSt = stations.find(
									s => s.id === p.toStationId
								);
								const inChain = chain.includes(p.id);
								return (
									<div
										key={p.id}
										onClick={() => addPattern(p.id)}
										style={{
											padding: "10px 12px",
											borderBottom:
												"1px solid var(--color-border)",
											cursor: "pointer",
											background: inChain
												? "var(--color-accent-bg)"
												: "transparent",
											opacity: inChain ? 0.7 : 1,
										}}
									>
										<div
											style={{
												display: "flex",
												alignItems: "center",
												gap: 6,
												marginBottom: 4,
											}}
										>
											<span
												style={{
													fontWeight: 600,
													fontSize: 13,
												}}
											>
												{p.name || "(無名)"}
											</span>
											<span
												className={`chip ${
													p.direction === 1
														? "green"
														: "amber"
												}`}
												style={{
													fontSize: 9,
													padding: "1px 5px",
												}}
											>
												{p.direction === 1 ? "↓" : "↑"}
											</span>
											{inChain && (
												<span
													className="chip"
													style={{
														fontSize: 9,
														padding: "1px 5px",
														marginLeft: "auto",
													}}
												>
													追加済
												</span>
											)}
										</div>
										<div
											style={{
												fontSize: 11,
												color: "var(--color-text-muted)",
											}}
										>
											{line?.name}
										</div>
										<div
											style={{
												fontSize: 12,
												marginTop: 2,
											}}
										>
											<span style={{ fontWeight: 500 }}>
												{fromSt?.stationName}
											</span>
											<span
												style={{
													color: "var(--color-text-muted)",
													margin: "0 4px",
												}}
											>
												→
											</span>
											<span style={{ fontWeight: 500 }}>
												{toSt?.stationName}
											</span>
										</div>
										<div
											style={{
												fontSize: 11,
												color: "var(--color-text-muted)",
												marginTop: 2,
											}}
										>
											停車{" "}
											{
												(p.stopRows || []).filter(
													r => !r.isPass
												).length
											}
											駅 · 通過{" "}
											{
												(p.stopRows || []).filter(
													r => r.isPass
												).length
											}
											駅
										</div>
									</div>
								);
							})}
						</div>
					</div>

					{/* ── Right: chain + preview ──────────────────────────────────── */}
					<div
						style={{
							display: "flex",
							flexDirection: "column",
							overflow: "hidden",
						}}
					>
						{/* Chain selector */}
						<div
							style={{
								padding: "10px 14px",
								borderBottom:
									"1px solid var(--color-border)",
								background: "var(--color-bg)",
							}}
						>
							<div
								style={{
									fontSize: 11,
									fontWeight: 600,
									color: "var(--color-text-muted)",
									textTransform: "uppercase",
									letterSpacing: "0.06em",
									marginBottom: 6,
								}}
							>
								適用順（クリックして追加、ドラッグで並べ替え）
							</div>
							{chain.length === 0 ? (
								<div
									style={{
										fontSize: 12,
										color: "var(--color-text-muted)",
										padding: "6px 0",
									}}
								>
									← 左のパターンをクリックして追加
								</div>
							) : (
								<div
									style={{
										display: "flex",
										flexWrap: "wrap",
										gap: 6,
										alignItems: "center",
									}}
								>
									{chain.map((id, idx) => {
										const p = stopPatterns.find(
											x => x.id === id
										);
										if (!p) return null;
										return (
											<Fragment key={id}>
												<div
													style={{
														display: "flex",
														alignItems: "center",
														gap: 4,
													}}
												>
													<span
														style={{
															fontSize: 11,
															color: "var(--color-text-muted)",
														}}
													>
														{idx + 1}.
													</span>
													<PatternChip
														pattern={p}
														stations={stations}
														onRemove={() =>
															removePattern(idx)
														}
													/>
													<div
														style={{
															display: "flex",
															gap: 2,
														}}
													>
														<button
															className="btn btn-ghost btn-xs"
															onClick={() =>
																moveUp(idx)
															}
															disabled={idx === 0}
															style={{
																opacity:
																	idx === 0
																		? 0.3
																		: 1,
															}}
														>
															↑
														</button>
														<button
															className="btn btn-ghost btn-xs"
															onClick={() =>
																moveDown(idx)
															}
															disabled={
																idx ===
																chain.length - 1
															}
															style={{
																opacity:
																	idx ===
																	chain.length -
																		1
																		? 0.3
																		: 1,
															}}
														>
															↓
														</button>
													</div>
												</div>
												{idx < chain.length - 1 && (
													<span
														style={{
															fontSize: 13,
															color: "var(--color-accent)",
														}}
													>
														+
													</span>
												)}
											</Fragment>
										);
									})}
								</div>
							)}
						</div>

						{/* Junction dwell time (when chain has 2+ patterns) */}
						{boundaryIds.size > 0 && (
							<div
								style={{
									padding: "8px 14px",
									borderBottom:
										"1px solid var(--color-border)",
									background: "var(--color-bg)",
								}}
							>
								<div
									style={{
										fontSize: 11,
										fontWeight: 600,
										color: "var(--color-text-muted)",
										textTransform: "uppercase",
										letterSpacing: "0.06em",
										marginBottom: 6,
									}}
								>
									パターン接続駅の停車時間
									<span
										style={{
											fontWeight: 400,
											textTransform: "none",
											letterSpacing: "normal",
											marginLeft: 8,
											color: "var(--color-text-muted)",
											fontSize: 11,
										}}
									>
										（各パターンの終着→次パターン始発での停車時間）
									</span>
								</div>
								<div
									style={{
										display: "flex",
										gap: 16,
										flexWrap: "wrap",
									}}
								>
									{[...boundaryIds].map(stId => {
										const st = stations.find(
											s => s.id === stId
										);
										const secs =
											junctionDwells[stId] ?? 0;
										const mm = Math.floor(secs / 60);
										const ss = secs % 60;
										return (
											<div
												key={stId}
												style={{
													display: "flex",
													alignItems: "center",
													gap: 8,
													fontSize: 13,
												}}
											>
												<span
													style={{
														fontWeight: 500,
													}}
												>
													{st?.stationName}
												</span>
												<span
													style={{
														color: "var(--color-text-muted)",
														fontSize: 11,
													}}
												>
													停車時間:
												</span>
												<div
													style={{
														display: "flex",
														alignItems: "center",
														gap: 4,
													}}
												>
													<NumInput
														value={mm}
														onChange={v =>
															setJD(
																stId,
																v * 60 + ss
															)
														}
														style={{ width: 44 }}
													/>
													<span
														style={{
															fontSize: 11,
															color: "var(--color-text-muted)",
														}}
													>
														分
													</span>
													<NumInput
														value={ss}
														onChange={v =>
															setJD(
																stId,
																mm * 60 + v
															)
														}
														max={59}
														style={{ width: 44 }}
													/>
													<span
														style={{
															fontSize: 11,
															color: "var(--color-text-muted)",
														}}
													>
														秒
													</span>
												</div>
											</div>
										);
									})}
								</div>
							</div>
						)}

						{/* Start time + mode */}
						<div
							style={{
								padding: "8px 14px",
								borderBottom:
									"1px solid var(--color-border)",
								display: "flex",
								alignItems: "center",
								gap: 20,
								flexWrap: "wrap",
							}}
						>
							<div
								style={{
									display: "flex",
									alignItems: "center",
									gap: 8,
								}}
							>
								<span
									style={{
										fontSize: 12,
										color: "var(--color-text-muted)",
										whiteSpace: "nowrap",
									}}
								>
									始発駅 発車時刻:
								</span>
								<input
									type="text"
									value={startDep}
									onChange={e =>
										setStartDep(e.target.value)
									}
									onBlur={normalizeStart}
									placeholder="09:00:00"
									style={{
										width: 88,
										padding: "4px 8px",
										border:
											"1px solid var(--color-border)",
										borderRadius: "var(--radius)",
										fontFamily: "var(--font-mono)",
										fontSize: 13,
										background: "var(--color-content)",
										color: "var(--color-text)",
										outline: "none",
									}}
								/>
							</div>
							{existingTrain && (
								<div
									style={{
										display: "flex",
										alignItems: "center",
										gap: 8,
									}}
								>
									<span
										style={{
											fontSize: 12,
											color: "var(--color-text-muted)",
										}}
									>
										適用方法:
									</span>
									{/* append/prepend disabled: server re-sorts rows by stations.location_km,
								    so positional append/prepend is incoherent under the API — deferred to task #26 */}
								{(
										[
											["replace", "既存の行を置き換え"],
											["append", "末尾に追加"],
											["prepend", "先頭に挿入"],
										] as [ApplyMode, string][]
									).map(([v, l]) => (
										<label
											key={v}
											style={{
												display: "flex",
												alignItems: "center",
												gap: 4,
												fontSize: 12,
												cursor: v === "replace" ? "pointer" : "not-allowed",
												opacity: v === "replace" ? 1 : 0.4,
											}}
										>
											<input
												type="radio"
												value={v}
												checked={applyMode === v}
												disabled={v !== "replace"}
												onChange={() =>
													setApplyMode(v)
												}
												style={{
													accentColor:
														"var(--color-accent)",
												}}
											/>
											{l}
										</label>
									))}
								</div>
							)}
							{chainRows.length > 0 && (
								<span
									style={{
										fontSize: 12,
										color: "var(--color-text-muted)",
										marginLeft: "auto",
									}}
								>
									合計 {chainRows.length} 駅 · 通過{" "}
									{
										chainRows.filter(r => r.isPass)
											.length
									}{" "}
									· 停車{" "}
									{
										chainRows.filter(r => !r.isPass)
											.length
									}
								</span>
							)}
						</div>

						{/* Preview table */}
						<div
							style={{ flex: 1, overflow: "auto" }}
						>
							{previewRows.length === 0 ? (
								<div
									className="empty-state"
									style={{ padding: 40 }}
								>
									<p>
										パターンを選択し始発時刻を入力すると、時刻表プレビューが表示されます。
									</p>
								</div>
							) : (
								<table
									style={{
										width: "100%",
										borderCollapse: "collapse",
										fontSize: 12,
									}}
								>
									<thead
										style={{
											position: "sticky",
											top: 0,
											zIndex: 5,
										}}
									>
										<tr
											style={{
												background:
													"var(--color-content)",
												borderBottom:
													"2px solid var(--color-border)",
											}}
										>
											{[
												"#",
												"駅名",
												"着",
												"発",
												"番線",
												"通",
												"運停",
												"所要時分",
											].map((h, i) => (
												<th
													key={i}
													style={{
														padding: "6px 8px",
														textAlign:
															i <= 1
																? "left"
																: "center",
														fontSize: 11,
														fontWeight: 600,
														color: "var(--color-text-muted)",
														textTransform:
															"uppercase",
														letterSpacing:
															"0.04em",
														width:
															i === 0
																? 28
																: i === 1
																? undefined
																: i >= 2 &&
																  i <= 3
																? 80
																: i === 4
																? 44
																: i === 5 ||
																  i === 6
																? 36
																: 80,
													}}
												>
													{h}
												</th>
											))}
										</tr>
									</thead>
									<tbody>
										{previewRows.map((row, i) => {
											const isLast =
												i ===
												previewRows.length - 1;
											const bg = row.isPass
												? "var(--color-pass-bg)"
												: row.isOperationOnlyStop
												? "var(--color-oponly-bg)"
												: isLast
												? "var(--color-laststop-bg)"
												: "transparent";
											const isBoundary =
												boundaryIds.has(
													row.stationId
												) &&
												i > 0 &&
												i <
													previewRows.length - 1;
											return (
												<tr
													key={i}
													style={{
														borderBottom:
															"1px solid var(--color-border)",
														background: bg,
													}}
												>
													<td
														style={{
															padding: "5px 8px",
															fontFamily:
																"var(--font-mono)",
															fontSize: 11,
															color: "var(--color-text-muted)",
														}}
													>
														{i + 1}
													</td>
													<td
														style={{
															padding: "5px 8px",
															fontWeight: 500,
														}}
													>
														{row.stationName}
														{isLast && (
															<span
																style={{
																	marginLeft: 6,
																	fontSize: 10,
																	color: "var(--color-success)",
																	fontWeight: 700,
																}}
															>
																終
															</span>
														)}
														{isBoundary && (
															<span
																style={{
																	marginLeft: 4,
																	fontSize: 9,
																	color: "var(--color-accent)",
																	fontWeight: 600,
																}}
															>
																接
															</span>
														)}
													</td>
													<td
														style={{
															padding: "5px 8px",
															textAlign:
																"center",
															fontFamily:
																"var(--font-mono)",
															fontSize: 11,
														}}
													>
														{row.isPass ? (
															<span
																style={{
																	opacity: 0.3,
																}}
															>
																—
															</span>
														) : (
															TRViSTime.display(
																row.arrive,
																false
															) || (
																<span
																	style={{
																		opacity: 0.25,
																	}}
																>
																	──:──
																</span>
															)
														)}
													</td>
													<td
														style={{
															padding: "5px 8px",
															textAlign:
																"center",
															fontFamily:
																"var(--font-mono)",
															fontSize: 11,
														}}
													>
														{TRViSTime.display(
															row.departure,
															false
														) || (
															<span
																style={{
																	opacity: 0.25,
																}}
															>
																──:──
															</span>
														)}
													</td>
													<td
														style={{
															padding: "5px 8px",
															textAlign:
																"center",
															fontFamily:
																"var(--font-mono)",
															fontSize: 11,
															color: "var(--color-text-muted)",
														}}
													>
														{row.trackName}
													</td>
													<td
														style={{
															padding: "5px 8px",
															textAlign:
																"center",
														}}
													>
														{row.isPass && "✓"}
													</td>
													<td
														style={{
															padding: "5px 8px",
															textAlign:
																"center",
														}}
													>
														{row.isOperationOnlyStop &&
															"✓"}
													</td>
													<td
														style={{
															padding: "5px 8px",
															textAlign:
																"center",
															fontFamily:
																"var(--font-mono)",
															fontSize: 11,
															color: "var(--color-text-muted)",
														}}
													>
														{i > 0
															? `${
																	row.driveTime_MM
															  }:${String(
																	row.driveTime_SS
															  ).padStart(
																	2,
																	"0"
															  )}`
															: "—"}
													</td>
												</tr>
											);
										})}
									</tbody>
								</table>
							)}
						</div>
					</div>
				</div>

				<div className="modal-footer">
					<button
						className="btn btn-secondary"
						onClick={onClose}
					>
						{t.cancel}
					</button>
					<button
						className="btn btn-primary"
						onClick={handleApply}
						disabled={
							previewRows.length === 0 || !startDep
						}
					>
						✓{" "}
						{existingTrain
							? `この内容で列車を${
									applyMode === "replace"
										? "置き換え"
										: applyMode === "append"
										? "末尾に追加"
										: "先頭に挿入"
							  }`
							: "この内容で列車を作成"}
					</button>
				</div>
			</div>
		</div>
	);
}
