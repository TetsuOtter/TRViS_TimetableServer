// StopPatternWizard.tsx — Stop pattern wizard with edit support
// Ported 1:1 from the design prototype StopPatternWizard.jsx.
import { useState, useEffect, Fragment } from "react";
import type { CSSProperties } from "react";
import type {
	Line,
	Station,
	StationOnLine,
	StopPattern,
	StopPatternRow,
	Direction,
} from "../types/model";
import type { Strings } from "../i18n/strings";

/* Wizard-local working shape: direction may be null until chosen. */
interface WizardData {
	id?: string;
	lineId: string;
	direction: Direction | null;
	name: string;
	fromStationId: string;
	toStationId: string;
	stopRows: StopPatternRow[];
}

type SetData = React.Dispatch<React.SetStateAction<WizardData>>;

/* ── Step indicator ─────────────────────────────────────────────────────── */
interface StepIndicatorProps {
	step: number;
	labels: string[];
}

function StepIndicator({ step, labels }: StepIndicatorProps) {
	return (
		<div className="wizard-steps">
			{labels.map((lbl, i) => (
				<Fragment key={i}>
					{i > 0 && <div className="wizard-connector" />}
					<div
						className={`wizard-step ${
							step === i ? "active" : step > i ? "done" : ""
						}`}
					>
						<div className="wizard-step-num">
							{step > i ? "✓" : i + 1}
						</div>
						<div className="wizard-step-label">{lbl}</div>
					</div>
				</Fragment>
			))}
		</div>
	);
}

/* ── Step 1: Line & Range ───────────────────────────────────────────────── */
interface Step1Props {
	data: WizardData;
	setData: SetData;
	lines: Line[];
	stations: Station[];
	stationsOnLine: StationOnLine[];
	t: Strings;
}

function Step1({
	data,
	setData,
	lines,
	stations,
	stationsOnLine,
	t,
}: Step1Props) {
	// キロ程昇順に並べた路線内全駅
	const lineStations: Station[] = data.lineId
		? stationsOnLine
				.filter(sol => sol.lineId === data.lineId)
				.sort((a, b) => (a.location_m || 0) - (b.location_m || 0))
				.map(sol => stations.find(s => s.id === sol.stationId))
				.filter((s): s is Station => Boolean(s))
		: [];

	// 始発駅のインデックス
	const fromIdx = lineStations.findIndex(
		s => s.id === data.fromStationId
	);

	// 終点候補：方向と始発に応じて絞り込む
	// direction===1 (下り=キロ程昇順): fromIdx より後ろの駅のみ
	// direction===-1 (上り=キロ程降順): fromIdx より前の駅のみ（逆順表示）
	const toStationCandidates: Station[] = (() => {
		if (!data.direction || fromIdx < 0) return lineStations;
		if (data.direction === 1) {
			return lineStations.slice(fromIdx + 1);
		} else {
			return lineStations.slice(0, fromIdx).reverse();
		}
	})();

	// 始発候補：方向によって「終点になれない」駅は除外
	// 下り: 最終駅（末尾）は始発になれない / 上り: 先頭駅は始発になれない
	const fromStationCandidates: Station[] = (() => {
		if (!data.direction) return lineStations;
		if (data.direction === 1)
			return lineStations.slice(0, lineStations.length - 1);
		return lineStations.slice(1).reverse();
	})();

	// 方向変更時に始発・終点をリセット
	const handleDirectionChange = (dir: Direction) => {
		setData(d => ({
			...d,
			direction: dir,
			fromStationId: "",
			toStationId: "",
		}));
	};

	// 始発変更時に終点をリセット（候補外になる可能性があるため）
	const handleFromChange = (stId: string) => {
		setData(d => ({ ...d, fromStationId: stId, toStationId: "" }));
	};

	return (
		<div
			style={{
				display: "flex",
				flexDirection: "column",
				gap: 16,
			}}
		>
			{/* 路線選択 */}
			<div className="field">
				<label>{t.selectLine}</label>
				<select
					value={data.lineId || ""}
					onChange={e =>
						setData(d => ({
							...d,
							lineId: e.target.value,
							fromStationId: "",
							toStationId: "",
							direction: null,
						}))
					}
				>
					<option value="">── {t.selectLine} ──</option>
					{lines.map(l => (
						<option key={l.id} value={l.id}>
							{l.name}
						</option>
					))}
				</select>
			</div>

			{/* 方向選択（路線が決まったら表示） */}
			{data.lineId && (
				<div className="field">
					<label>方向</label>
					<div style={{ display: "flex", gap: 8 }}>
						{(
							[
								{ val: 1, label: `↓ ${t.down}`, cls: "green" },
								{ val: -1, label: `↑ ${t.up}`, cls: "amber" },
							] as { val: Direction; label: string; cls: string }[]
						).map(opt => (
							<button
								key={opt.val}
								type="button"
								onClick={() => handleDirectionChange(opt.val)}
								style={{
									flex: 1,
									padding: "8px 0",
									borderRadius: "var(--radius)",
									border:
										data.direction === opt.val
											? "2px solid var(--color-accent)"
											: "1.5px solid var(--color-border)",
									background:
										data.direction === opt.val
											? "var(--color-accent)"
											: "var(--color-content)",
									color:
										data.direction === opt.val
											? "#fff"
											: "var(--color-text)",
									fontWeight:
										data.direction === opt.val ? 700 : 400,
									fontSize: 14,
									cursor: "pointer",
									transition: "all .15s",
								}}
							>
								{opt.label}
							</button>
						))}
					</div>
				</div>
			)}

			{/* 始発・終点（方向が決まったら表示） */}
			{data.lineId && data.direction && (
				<>
					<div className="field-row">
						<div className="field">
							<label>{t.from}（始発）</label>
							<select
								value={data.fromStationId || ""}
								onChange={e => handleFromChange(e.target.value)}
							>
								<option value="">── 選択 ──</option>
								{fromStationCandidates.map(s => (
									<option key={s.id} value={s.id}>
										{s.stationName}
									</option>
								))}
							</select>
						</div>
						<div className="field">
							<label>{t.to}（終点）</label>
							<select
								value={data.toStationId || ""}
								onChange={e =>
									setData(d => ({
										...d,
										toStationId: e.target.value,
									}))
								}
								disabled={!data.fromStationId}
							>
								<option value="">
									{data.fromStationId
										? "── 選択 ──"
										: "── 始発を先に選択 ──"}
								</option>
								{toStationCandidates.map(s => (
									<option key={s.id} value={s.id}>
										{s.stationName}
									</option>
								))}
							</select>
						</div>
					</div>

					{/* 区間プレビュー */}
					{data.fromStationId &&
						data.toStationId &&
						(() => {
							const fromSt = stations.find(
								s => s.id === data.fromStationId
							);
							const toSt = stations.find(
								s => s.id === data.toStationId
							);
							return (
								<div
									style={{
										display: "flex",
										alignItems: "center",
										gap: 8,
										padding: "8px 12px",
										background: "var(--color-bg)",
										borderRadius: "var(--radius)",
										fontSize: 13,
									}}
								>
									<span
										style={{
											color: "var(--color-text-muted)",
										}}
									>
										区間:
									</span>
									<span style={{ fontWeight: 600 }}>
										{fromSt?.stationName}
									</span>
									<span
										style={{
											color: "var(--color-text-muted)",
										}}
									>
										{data.direction === 1 ? "→" : "←"}
									</span>
									<span style={{ fontWeight: 600 }}>
										{toSt?.stationName}
									</span>
									<span
										className={`chip ${
											data.direction === 1
												? "green"
												: "amber"
										}`}
										style={{ marginLeft: 4 }}
									>
										{data.direction === 1
											? `↓ ${t.down}`
											: `↑ ${t.up}`}
									</span>
								</div>
							);
						})()}

					<div className="field">
						<label>パターン名</label>
						<input
							value={data.name || ""}
							onChange={e =>
								setData(d => ({ ...d, name: e.target.value }))
							}
							placeholder="例: 快速停車パターン"
						/>
					</div>
				</>
			)}
		</div>
	);
}

/* ── Step 2: Per-station settings ───────────────────────────────────────── */
interface Step2Props {
	data: WizardData;
	setData: SetData;
	stations: Station[];
	stationsOnLine: StationOnLine[];
	t: Strings;
}

function Step2({
	data,
	setData,
	stations,
	stationsOnLine,
	t,
}: Step2Props) {
	const lineStations: Station[] = stationsOnLine
		.filter(sol => sol.lineId === data.lineId)
		.sort((a, b) => (a.location_m || 0) - (b.location_m || 0))
		.map(sol => stations.find(s => s.id === sol.stationId))
		.filter((s): s is Station => Boolean(s));

	const fromIdx = lineStations.findIndex(
		s => s.id === data.fromStationId
	);
	const toIdx = lineStations.findIndex(
		s => s.id === data.toStationId
	);
	const rangeStations: Station[] =
		fromIdx >= 0 && toIdx >= 0
			? fromIdx <= toIdx
				? lineStations.slice(fromIdx, toIdx + 1)
				: [...lineStations.slice(toIdx, fromIdx + 1)].reverse()
			: lineStations;

	const getRow = (stId: string): StopPatternRow =>
		(data.stopRows || []).find(r => r.stationId === stId) || {
			stationId: stId,
			trackName: "1",
			isPass: false,
			isOperationOnlyStop: false,
			driveTime_MM: 0,
			driveTime_SS: 0,
			dwellTime_MM: 0,
			dwellTime_SS: 0,
		};

	const updateRow = <K extends keyof StopPatternRow>(
		stId: string,
		key: K,
		val: StopPatternRow[K]
	) => {
		setData(d => {
			const rows = d.stopRows || [];
			const existing = rows.find(r => r.stationId === stId);
			const next = existing
				? rows.map(r =>
						r.stationId === stId ? { ...r, [key]: val } : r
				  )
				: [...rows, { ...getRow(stId), [key]: val }];
			return { ...d, stopRows: next };
		});
	};

	// Ensure all range stations have a row (so defaults are persisted on save)
	useEffect(() => {
		setData(d => {
			const rows = d.stopRows || [];
			const missingIds = rangeStations
				.map(s => s.id)
				.filter(id => !rows.find(r => r.stationId === id));
			if (missingIds.length === 0) return d;
			const newRows = [
				...rows,
				...missingIds.map(id => getRow(id)),
			];
			return { ...d, stopRows: newRows };
		});
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [data.fromStationId, data.toStationId, data.lineId]);

	const cellStyle = (disabled: boolean): CSSProperties => ({
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
	});

	return (
		<div>
			<p
				style={{
					fontSize: 12,
					color: "var(--color-text-muted)",
					marginBottom: 12,
				}}
			>
				各駅の停車パターンを設定してください。始発・終着駅では不要な項目はグレーアウトされます。
			</p>
			<div
				style={{
					border: "1px solid var(--color-border)",
					borderRadius: "var(--radius)",
					overflow: "auto",
				}}
			>
				<table
					style={{
						borderCollapse: "collapse",
						width: "100%",
						fontSize: 13,
						minWidth: 560,
					}}
				>
					<thead>
						<tr style={{ background: "var(--color-bg)" }}>
							<th
								rowSpan={2}
								style={{
									padding: "6px 10px",
									textAlign: "left",
									fontWeight: 600,
									fontSize: 11,
									color: "var(--color-text-muted)",
									borderBottom:
										"1px solid var(--color-border)",
									borderRight:
										"1px solid var(--color-border)",
								}}
							>
								駅名
							</th>
							<th
								rowSpan={2}
								style={{
									padding: "6px 8px",
									textAlign: "center",
									fontSize: 11,
									color: "var(--color-text-muted)",
									borderBottom:
										"1px solid var(--color-border)",
									borderRight:
										"1px solid var(--color-border)",
									width: 48,
								}}
							>
								{t.track}
							</th>
							<th
								rowSpan={2}
								style={{
									padding: "6px 8px",
									textAlign: "center",
									fontSize: 11,
									color: "var(--color-text-muted)",
									borderBottom:
										"1px solid var(--color-border)",
									borderRight:
										"1px solid var(--color-border)",
									width: 40,
								}}
							>
								通
							</th>
							<th
								rowSpan={2}
								style={{
									padding: "6px 8px",
									textAlign: "center",
									fontSize: 11,
									color: "var(--color-text-muted)",
									borderBottom:
										"1px solid var(--color-border)",
									borderRight:
										"1px solid var(--color-border)",
									width: 40,
								}}
							>
								運停
							</th>
							<th
								colSpan={2}
								style={{
									padding: "4px 8px",
									textAlign: "center",
									fontSize: 11,
									color: "var(--color-text-muted)",
									borderBottom:
										"1px solid var(--color-border)",
									borderRight:
										"1px solid var(--color-border)",
								}}
							>
								所要時間（前駅から）
							</th>
							<th
								colSpan={2}
								style={{
									padding: "4px 8px",
									textAlign: "center",
									fontSize: 11,
									color: "var(--color-text-muted)",
									borderBottom:
										"1px solid var(--color-border)",
								}}
							>
								停車時間（停車駅のみ）
							</th>
						</tr>
						<tr style={{ background: "var(--color-bg)" }}>
							<th
								style={{
									padding: "4px 6px",
									textAlign: "center",
									fontSize: 10,
									color: "var(--color-text-muted)",
									borderBottom:
										"2px solid var(--color-border)",
									width: 48,
								}}
							>
								分
							</th>
							<th
								style={{
									padding: "4px 6px",
									textAlign: "center",
									fontSize: 10,
									color: "var(--color-text-muted)",
									borderBottom:
										"2px solid var(--color-border)",
									borderRight:
										"1px solid var(--color-border)",
									width: 48,
								}}
							>
								秒
							</th>
							<th
								style={{
									padding: "4px 6px",
									textAlign: "center",
									fontSize: 10,
									color: "var(--color-text-muted)",
									borderBottom:
										"2px solid var(--color-border)",
									width: 48,
								}}
							>
								分
							</th>
							<th
								style={{
									padding: "4px 6px",
									textAlign: "center",
									fontSize: 10,
									color: "var(--color-text-muted)",
									borderBottom:
										"2px solid var(--color-border)",
									width: 48,
								}}
							>
								秒
							</th>
						</tr>
					</thead>
					<tbody>
						{rangeStations.map((st, i) => {
							const row = getRow(st.id);
							const isFirst = i === 0;
							const isLast =
								i === rangeStations.length - 1;
							const driveDis = isFirst;
							const dwellDis =
								row.isPass || isFirst || isLast;
							const rowBg = row.isPass
								? "var(--color-pass-bg)"
								: i % 2 === 0
								? "transparent"
								: "rgba(0,0,0,0.012)";
							return (
								<tr
									key={st.id}
									style={{ background: rowBg }}
								>
									<td
										style={{
											padding: "5px 10px",
											borderBottom:
												"1px solid var(--color-border)",
											borderRight:
												"1px solid var(--color-border)",
											color: row.isPass
												? "var(--color-pass-text)"
												: "var(--color-text)",
											whiteSpace: "nowrap",
										}}
									>
										{st.stationName}
										{isFirst && (
											<span
												style={{
													fontSize: 10,
													color: "var(--color-text-muted)",
													marginLeft: 6,
												}}
											>
												始発
											</span>
										)}
										{isLast && (
											<span
												style={{
													fontSize: 10,
													color: "var(--color-success)",
													marginLeft: 6,
													fontWeight: 600,
												}}
											>
												終着
											</span>
										)}
									</td>
									<td
										style={{
											padding: "3px 4px",
											borderBottom:
												"1px solid var(--color-border)",
											borderRight:
												"1px solid var(--color-border)",
											textAlign: "center",
										}}
									>
										<input
											value={row.trackName || "1"}
											onChange={e =>
												updateRow(
													st.id,
													"trackName",
													e.target.value
												)
											}
											style={cellStyle(false)}
										/>
									</td>
									<td
										style={{
											padding: "3px",
											borderBottom:
												"1px solid var(--color-border)",
											borderRight:
												"1px solid var(--color-border)",
											textAlign: "center",
										}}
									>
										<input
											type="checkbox"
											checked={!!row.isPass}
											onChange={e =>
												updateRow(
													st.id,
													"isPass",
													e.target.checked
												)
											}
											style={{
												accentColor:
													"var(--color-accent)",
												width: 14,
												height: 14,
											}}
										/>
									</td>
									<td
										style={{
											padding: "3px",
											borderBottom:
												"1px solid var(--color-border)",
											borderRight:
												"1px solid var(--color-border)",
											textAlign: "center",
										}}
									>
										<input
											type="checkbox"
											checked={
												!!row.isOperationOnlyStop
											}
											onChange={e =>
												updateRow(
													st.id,
													"isOperationOnlyStop",
													e.target.checked
												)
											}
											disabled={row.isPass}
											style={{
												accentColor:
													"var(--color-accent)",
												width: 14,
												height: 14,
												opacity: row.isPass ? 0.3 : 1,
											}}
										/>
									</td>
									{/* Drive time MM */}
									<td
										style={{
											padding: "3px 4px",
											borderBottom:
												"1px solid var(--color-border)",
										}}
									>
										<input
											type="number"
											value={
												driveDis
													? ""
													: row.driveTime_MM || 0
											}
											min={0}
											onChange={e =>
												updateRow(
													st.id,
													"driveTime_MM",
													+e.target.value
												)
											}
											disabled={driveDis}
											style={cellStyle(driveDis)}
											title={
												driveDis
													? "始発駅は所要時間不要"
													: ""
											}
										/>
									</td>
									{/* Drive time SS */}
									<td
										style={{
											padding: "3px 4px",
											borderBottom:
												"1px solid var(--color-border)",
											borderRight:
												"1px solid var(--color-border)",
										}}
									>
										<input
											type="number"
											value={
												driveDis
													? ""
													: row.driveTime_SS || 0
											}
											min={0}
											max={59}
											onChange={e =>
												updateRow(
													st.id,
													"driveTime_SS",
													+e.target.value
												)
											}
											disabled={driveDis}
											style={cellStyle(driveDis)}
											title={
												driveDis
													? "始発駅は所要時間不要"
													: ""
											}
										/>
									</td>
									{/* Dwell time MM */}
									<td
										style={{
											padding: "3px 4px",
											borderBottom:
												"1px solid var(--color-border)",
										}}
									>
										<input
											type="number"
											value={
												dwellDis
													? ""
													: row.dwellTime_MM || 0
											}
											min={0}
											onChange={e =>
												updateRow(
													st.id,
													"dwellTime_MM",
													+e.target.value
												)
											}
											disabled={dwellDis}
											style={cellStyle(dwellDis)}
											title={
												isLast
													? "終着駅は停車時間不要"
													: row.isPass
													? "通過駅は停車時間不要"
													: ""
											}
										/>
									</td>
									{/* Dwell time SS */}
									<td
										style={{
											padding: "3px 4px",
											borderBottom:
												"1px solid var(--color-border)",
										}}
									>
										<input
											type="number"
											value={
												dwellDis
													? ""
													: row.dwellTime_SS || 0
											}
											min={0}
											max={59}
											onChange={e =>
												updateRow(
													st.id,
													"dwellTime_SS",
													+e.target.value
												)
											}
											disabled={dwellDis}
											style={cellStyle(dwellDis)}
											title={
												isLast
													? "終着駅は停車時間不要"
													: row.isPass
													? "通過駅は停車時間不要"
													: ""
											}
										/>
									</td>
								</tr>
							);
						})}
					</tbody>
				</table>
			</div>
		</div>
	);
}

/* ── Step 3: Confirm ────────────────────────────────────────────────────── */
interface Step3Props {
	data: WizardData;
	stations: Station[];
	lines: Line[];
	t: Strings;
}

function Step3({ data, stations, lines, t }: Step3Props) {
	const line = lines.find(l => l.id === data.lineId);
	const fromSt = stations.find(s => s.id === data.fromStationId);
	const toSt = stations.find(s => s.id === data.toStationId);
	const stops = (data.stopRows || []).filter(r => !r.isPass);
	const passes = (data.stopRows || []).filter(r => r.isPass);
	return (
		<div
			style={{
				display: "flex",
				flexDirection: "column",
				gap: 12,
			}}
		>
			<div className="card" style={{ padding: 16 }}>
				<div
					className="section-title"
					style={{ marginBottom: 8 }}
				>
					パターン情報
				</div>
				<div
					style={{
						display: "grid",
						gridTemplateColumns: "1fr 1fr",
						gap: 8,
						fontSize: 13,
					}}
				>
					<div>
						<span
							style={{ color: "var(--color-text-muted)" }}
						>
							パターン名:{" "}
						</span>
						<strong>{data.name || "—"}</strong>
					</div>
					<div>
						<span
							style={{ color: "var(--color-text-muted)" }}
						>
							路線:{" "}
						</span>
						<strong>{line?.name || "—"}</strong>
					</div>
					<div>
						<span
							style={{ color: "var(--color-text-muted)" }}
						>
							区間:{" "}
						</span>
						<strong>
							{fromSt?.stationName}〜{toSt?.stationName}
						</strong>
					</div>
					<div>
						<span
							style={{ color: "var(--color-text-muted)" }}
						>
							方向:{" "}
						</span>
						<strong>
							{data.direction === 1 ? t.down : t.up}
						</strong>
					</div>
					<div>
						<span
							style={{ color: "var(--color-text-muted)" }}
						>
							停車:{" "}
						</span>
						<strong>{stops.length}駅</strong>
					</div>
					<div>
						<span
							style={{ color: "var(--color-text-muted)" }}
						>
							通過:{" "}
						</span>
						<strong>{passes.length}駅</strong>
					</div>
				</div>
			</div>
			<p
				style={{
					fontSize: 12,
					color: "var(--color-text-muted)",
				}}
			>
				このパターンを保存すると、列車作成時に適用できます。
			</p>
		</div>
	);
}

/* ── Main wizard ─────────────────────────────────────────────────────────── */
export interface StopPatternWizardProps {
	lines: Line[];
	stations: Station[];
	stationsOnLine: StationOnLine[];
	t: Strings;
	editPattern: StopPattern | null;
	onSave: (sp: StopPattern) => void;
	onClose: () => void;
}

export function StopPatternWizard({
	lines,
	stations,
	stationsOnLine,
	t,
	onSave,
	onClose,
	editPattern,
}: StopPatternWizardProps) {
	const isEdit = !!editPattern;
	const [step, setStep] = useState(0);
	const [data, setData] = useState<WizardData>(
		isEdit && editPattern
			? {
					id: editPattern.id,
					lineId: editPattern.lineId,
					direction: editPattern.direction,
					name: editPattern.name,
					fromStationId: editPattern.fromStationId,
					toStationId: editPattern.toStationId,
					stopRows:
						editPattern.stopRows ?? editPattern.rows ?? [],
			  }
			: {
					lineId: lines[0]?.id || "",
					direction: 1,
					name: "",
					fromStationId: "",
					toStationId: "",
					stopRows: [],
			  }
	);

	const stepLabels = [t.step1, t.step2, t.step3];
	const canNext =
		step === 0
			? !!(
					data.lineId &&
					data.direction &&
					data.fromStationId &&
					data.toStationId &&
					data.fromStationId !== data.toStationId
			  )
			: true;

	const handleFinish = () => {
		const id =
			isEdit && editPattern
				? editPattern.id
				: "sp" + Date.now();
		// canNext (step 0) guarantees a non-null direction by the time the
		// finish button is reachable; default to 1 defensively.
		const direction: Direction = data.direction ?? 1;
		const sp: StopPattern = {
			id,
			name: data.name,
			lineId: data.lineId,
			fromStationId: data.fromStationId,
			toStationId: data.toStationId,
			direction,
			rows: data.stopRows,
			stopRows: data.stopRows,
		};
		onSave(sp);
		onClose();
	};

	return (
		<div
			className="modal-backdrop"
			onClick={e => e.target === e.currentTarget && onClose()}
		>
			<div
				className="modal"
				style={{ maxWidth: 680, width: "100%" }}
			>
				<div className="modal-header">
					<span className="modal-title">
						🧩{" "}
						{isEdit
							? `停車パターンを編集: ${
									editPattern?.name || "(無名)"
							  }`
							: t.stopPattern}
					</span>
					<button
						className="btn btn-ghost btn-sm"
						onClick={onClose}
					>
						✕
					</button>
				</div>
				<div className="modal-body">
					<StepIndicator step={step} labels={stepLabels} />
					{step === 0 && (
						<Step1
							data={data}
							setData={setData}
							lines={lines}
							stations={stations}
							stationsOnLine={stationsOnLine}
							t={t}
						/>
					)}
					{step === 1 && (
						<Step2
							data={data}
							setData={setData}
							stations={stations}
							stationsOnLine={stationsOnLine}
							t={t}
						/>
					)}
					{step === 2 && (
						<Step3
							data={data}
							stations={stations}
							lines={lines}
							t={t}
						/>
					)}
				</div>
				<div className="modal-footer">
					<button
						className="btn btn-secondary"
						onClick={onClose}
					>
						{t.cancel}
					</button>
					{step > 0 && (
						<button
							className="btn btn-secondary"
							onClick={() => setStep(s => s - 1)}
						>
							{t.back}
						</button>
					)}
					{step < 2 ? (
						<button
							className="btn btn-primary"
							onClick={() => setStep(s => s + 1)}
							disabled={!canNext}
						>
							{t.next}
						</button>
					) : (
						<button
							className="btn btn-primary"
							onClick={handleFinish}
						>
							{isEdit ? "更新" : t.finish}
						</button>
					)}
				</div>
			</div>
		</div>
	);
}
