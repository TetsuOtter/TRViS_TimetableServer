// RowDetailModal — per-row detail editor modal for TimetableGrid.
import { useMemo, useState } from "react";

import { TRViSTime } from "../lib/timeUtils";

import { BBCodeField } from "./BBCodeField";
import { NumLimit } from "./TimetableNumLimit";

import type { Strings } from "../i18n/strings";
import type { ShowHH, TimetableRow } from "../types/model";

export type RowDetailModalProps = {
	readonly row: TimetableRow;
	readonly isFirstRow: boolean;
	readonly isLastIdx: boolean;
	readonly allRows: TimetableRow[];
	readonly t: Strings;
	readonly onSave: (r: TimetableRow) => void;
	readonly onClose: () => void;
};

export const RowDetailModal = ({
	row,
	isFirstRow,
	isLastIdx,
	allRows,
	t,
	onSave,
	onClose,
}: RowDetailModalProps) => {
	const [data, setData] = useState<TimetableRow>({ ...row });
	const set = <K extends keyof TimetableRow>(k: K, v: TimetableRow[K]) => {
		setData((d) => ({ ...d, [k]: v }));
	};

	const lastRowExclude = isLastIdx && data.isLastStop === false;

	const { arrivePhPlaceholder, departurePhPlaceholder } = useMemo(() => {
		if (allRows == null)
			return {
				arrivePhPlaceholder: "HH:MM:SS",
				departurePhPlaceholder: "HH:MM:SS",
			};
		let lastHH: number | null = null;
		const rowIdx = allRows.findIndex((r) => r.id === data.id);
		for (let i = 0; i < rowIdx; i++) {
			const r = allRows[i];
			if (r == null) continue;
			const forceShow = r.showHH === true;
			if (!r.isPass && Boolean(r.arrive) && r.arriveDisplayText == null) {
				const res = TRViSTime.formatOne(r.arrive, forceShow, lastHH);
				if (res.formatted !== null) lastHH = res.newHH;
			}
			if (Boolean(r.departure) && r.departureDisplayText == null) {
				const res = TRViSTime.formatOne(r.departure, forceShow, lastHH);
				if (res.formatted !== null) lastHH = res.newHH;
			}
		}

		const makePh = (
			timeStr: string,
			forceShow: boolean,
			lhh: number | null
		): string => {
			if (!(timeStr !== "")) return "HH:MM:SS";
			const res = TRViSTime.formatOne(timeStr, forceShow, lhh);
			if (res.formatted == null) return "HH:MM:SS";
			return res.formatted;
		};

		const forceShow = data.showHH === true;
		const arrivePh = makePh(data.arrive, forceShow, lastHH);

		let lastHHAfterArrive = lastHH;
		if (
			!data.isPass &&
			Boolean(data.arrive) &&
			data.arriveDisplayText == null
		) {
			const res = TRViSTime.formatOne(data.arrive, forceShow, lastHH);
			if (res.formatted !== null) lastHHAfterArrive = res.newHH;
		}
		const depPh = makePh(data.departure, forceShow, lastHHAfterArrive);

		return { arrivePhPlaceholder: arrivePh, departurePhPlaceholder: depPh };
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
		if (!(val?.trim() !== "")) return "";
		return TRViSTime.normalize(val.trim());
	};

	const boolFlags: ["isPass" | "isOperationOnlyStop", string, string][] = [
		["isPass", t.pass, "通過"],
		["isOperationOnlyStop", t.opStop, "運転停車（客扱いなし）"],
	];

	const showHHOptions: [ShowHH, string, string][] = [
		[undefined, "自動", "HHが前の表示時刻と同じ場合は省略"],
		[true, "常に表示", "この駅の時刻は常にHHを表示する"],
		[false, "常に省略", "この駅の時刻は常にHHを省略する"],
	];

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div
				className="modal"
				style={{ maxWidth: 620 }}>
				<div className="modal-header">
					<span className="modal-title">
						{`
						⚙ `}
						{Boolean(row.stationName) || "(未設定)"}
						{` — `}
						{t.detail}
					</span>
					<button
						type="button"
						className="btn btn-ghost btn-sm"
						onClick={onClose}>{`
						✕
					`}</button>
				</div>
				<div className="modal-body">
					{/* 駅名表示（読み取り専用） */}
					<div
						className="field-row"
						style={{ marginBottom: 12 }}>
						<div
							className="field"
							style={{ flex: 2 }}>
							<label>{t.stationName}</label>
							<span
								style={{
									padding: "6px 0",
									display: "block",
									fontWeight: 500,
									...((data.stationDeleted ?? false)
										? {
												color: "var(--color-danger)",
												textDecoration: "line-through",
											}
										: {}),
								}}>
								{Boolean(data.stationName) || "(未設定)"}
								{(data.stationDeleted ?? false) ? (
									<span
										style={{
											marginLeft: 6,
											fontSize: 11,
											textDecoration: "none",
										}}>{`
										(削除済み)
									`}</span>
								) : null}
							</span>
						</div>
						<div
							className="field"
							style={{ flex: 3 }}>
							<label>{`駅名フルネーム`}</label>
							<span style={{ padding: "6px 0", display: "block" }}>
								{Boolean(data.fullName) || "—"}
							</span>
						</div>
					</div>

					{/* 着時刻・発時刻・運転時分 */}
					<div
						className="field-row"
						style={{ marginBottom: 12 }}>
						<div className="field">
							<label
								style={{
									display: "flex",
									alignItems: "center",
									gap: 6,
								}}>
								{`
								着時刻
								`}
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
											checked={!!(data.arriveHidden ?? false)}
											onChange={(e) => {
												set("arriveHidden", e.target.checked);
											}}
											style={{ accentColor: "var(--color-accent)" }}
										/>
										{`
										非表示
									`}
									</label>
								)}
							</label>
							<input
								value={data.arrive}
								onChange={(e) => {
									set("arrive", e.target.value);
								}}
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
								{`
								発時刻
								`}
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
										checked={!!(data.departureHidden ?? false)}
										onChange={(e) => {
											set("departureHidden", e.target.checked);
										}}
										style={{ accentColor: "var(--color-accent)" }}
									/>
									{`
									非表示
								`}
								</label>
							</label>
							<input
								value={data.departure}
								onChange={(e) => {
									set("departure", e.target.value);
								}}
								onBlur={(e) => {
									const n = normalizeTimeField(e.target.value);
									set("departure", n);
								}}
								placeholder="HH:MM:SS"
								style={{ fontFamily: "var(--font-mono)" }}
							/>
						</div>
						<div
							className="field"
							style={{ flex: "0 0 160px" }}>
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
									value={data.driveTime_MM}
									onChange={(e) => {
										set("driveTime_MM", +e.target.value);
									}}
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
								<span style={{ opacity: 0.5, fontSize: 11 }}>{`分`}</span>
								<input
									type="number"
									min={0}
									max={59}
									value={data.driveTime_SS}
									onChange={(e) => {
										set("driveTime_SS", +e.target.value);
									}}
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
								<span style={{ opacity: 0.5, fontSize: 11 }}>{`秒`}</span>
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
							{`
							到着・発車時刻欄に表示する文字列`}{" "}
							<span style={{ fontWeight: 400, opacity: 0.7 }}>{`
								（時刻の代わりに表示する場合）
							`}</span>
						</div>
						<div className="field-row">
							<div className="field">
								<label style={{ fontSize: 11 }}>{`着欄の表示文字列`}</label>
								<input
									value={data.arriveDisplayText ?? ""}
									onChange={(e) => {
										set("arriveDisplayText", e.target.value);
									}}
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
									}}>{`
									空欄なら着時刻を表示
								`}</span>
							</div>
							<div className="field">
								<label style={{ fontSize: 11 }}>{`発欄の表示文字列`}</label>
								<input
									value={data.departureDisplayText ?? ""}
									onChange={(e) => {
										set("departureDisplayText", e.target.value);
									}}
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
									}}>{`
									空欄なら発時刻を表示
								`}</span>
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
									onChange={(e) => {
										set(k, e.target.checked);
									}}
									style={{ accentColor: "var(--color-accent)" }}
								/>
								{lbl}
							</label>
						))}
						{isFirstRow ? (
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
									checked={!!(data.hasBracket ?? false)}
									onChange={(e) => {
										set("hasBracket", e.target.checked);
									}}
									style={{ accentColor: "var(--color-accent)" }}
								/>
								{t.bracketTime}
							</label>
						) : null}
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
								}}>{`
								HH（時）表示
							`}</div>
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
											onChange={() => {
												set("showHH", val);
											}}
											style={{ accentColor: "var(--color-accent)" }}
										/>
										{lbl}
									</label>
								))}
							</div>
						</div>
						{isLastIdx ? (
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
									onChange={(e) => {
										set("isLastStop", !e.target.checked);
									}}
									style={{ accentColor: "var(--color-accent)" }}
								/>
								<span>
									{`
									この行を`}
									<strong>{`終着駅にしない`}</strong>
								</span>
								<span
									style={{
										fontSize: 11,
										color: "var(--color-text-muted)",
										marginLeft: "auto",
									}}>{`
									（既定: 最後の行 = 終着）
								`}</span>
							</label>
						) : null}
					</div>

					{/* 制限・作業 */}
					<div
						className="field-row"
						style={{ marginBottom: 12 }}>
						<div className="field">
							<label>{`進入制限 (0–999)`}</label>
							<NumLimit
								value={data.runInLimit}
								onChange={(v) => {
									set("runInLimit", v);
								}}
							/>
						</div>
						<div className="field">
							<label>{`進出制限 (0–999)`}</label>
							<NumLimit
								value={data.runOutLimit}
								onChange={(v) => {
									set("runOutLimit", v);
								}}
							/>
						</div>
						<div
							className="field"
							style={{ flex: "0 0 160px" }}>
							<label>{`駅作業タイプ`}</label>
							<input
								value={data.workType}
								onChange={(e) => {
									set("workType", e.target.value);
								}}
								placeholder="—"
							/>
						</div>
					</div>

					<BBCodeField
						label={t.remarks}
						value={data.remarks}
						onChange={(v) => {
							set("remarks", v);
						}}
						multiline
						rows={2}
					/>
				</div>
				<div className="modal-footer">
					<button
						type="button"
						className="btn btn-secondary"
						onClick={onClose}>
						{t.cancel}
					</button>
					<button
						type="button"
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
};
