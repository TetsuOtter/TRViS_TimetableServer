// StopPatternStep1.tsx — Step 1: Line & Range selection for StopPatternWizard
import type { WizardData, SetData } from "./StopPatternWizardTypes";
import type { Strings } from "../i18n/strings";
import type { Line, Station, StationOnLine, Direction } from "../types/model";

export type Step1Props = {
	readonly data: WizardData;
	readonly setData: SetData;
	readonly lines: Line[];
	readonly stations: Station[];
	readonly stationsOnLine: StationOnLine[];
	readonly t: Strings;
};

export const StopPatternStep1 = ({
	data,
	setData,
	lines,
	stations,
	stationsOnLine,
	t,
}: Step1Props) => {
	// キロ程昇順に並べた路線内全駅
	const lineStations: Station[] =
		data.lineId !== ""
			? stationsOnLine
					.filter((sol) => sol.lineId === data.lineId)
					.sort(
						(a, b) =>
							(a.location_m !== undefined ? a.location_m : 0) -
							(b.location_m !== undefined ? b.location_m : 0)
					)
					.map((sol) => stations.find((s) => s.id === sol.stationId))
					.filter((s): s is Station => s !== undefined)
			: [];

	// 始発駅のインデックス
	const fromIdx = lineStations.findIndex((s) => s.id === data.fromStationId);

	// 終点候補：方向と始発に応じて絞り込む
	// direction===1 (下り=キロ程昇順): fromIdx より後ろの駅のみ
	// direction===-1 (上り=キロ程降順): fromIdx より前の駅のみ（逆順表示）
	const toStationCandidates: Station[] = (() => {
		if (data.direction === null || fromIdx < 0) return lineStations;
		if (data.direction === 1) {
			return lineStations.slice(fromIdx + 1);
		} else {
			return lineStations.slice(0, fromIdx).reverse();
		}
	})();

	// 始発候補：方向によって「終点になれない」駅は除外
	// 下り: 最終駅（末尾）は始発になれない / 上り: 先頭駅は始発になれない
	const fromStationCandidates: Station[] = (() => {
		if (data.direction === null) return lineStations;
		if (data.direction === 1)
			return lineStations.slice(0, lineStations.length - 1);
		return lineStations.slice(1).reverse();
	})();

	// 方向変更時に始発・終点をリセット
	const handleDirectionChange = (dir: Direction) => {
		setData((d) => ({
			...d,
			direction: dir,
			fromStationId: "",
			toStationId: "",
		}));
	};

	// 始発変更時に終点をリセット（候補外になる可能性があるため）
	const handleFromChange = (stId: string) => {
		setData((d) => ({ ...d, fromStationId: stId, toStationId: "" }));
	};

	return (
		<div
			style={{
				display: "flex",
				flexDirection: "column",
				gap: 16,
			}}>
			{/* 路線選択 */}
			<div className="field">
				<label>{t.selectLine}</label>
				<select
					value={data.lineId !== "" ? data.lineId : ""}
					onChange={(e) => {
						setData((d) => ({
							...d,
							lineId: e.target.value,
							fromStationId: "",
							toStationId: "",
							direction: null,
						}));
					}}>
					<option value="">{`── ${t.selectLine} ──`}</option>
					{lines.map((l) => (
						<option
							key={l.id}
							value={l.id}>
							{l.name}
						</option>
					))}
				</select>
			</div>

			{/* 方向選択（路線が決まったら表示） */}
			{data.lineId !== "" ? (
				<div className="field">
					{/* prettier-ignore */}
					<label>{`方向`}</label>
					<div style={{ display: "flex", gap: 8 }}>
						{(
							[
								{ val: 1, label: `↓ ${t.down}`, cls: "green" },
								{ val: -1, label: `↑ ${t.up}`, cls: "amber" },
							] as { val: Direction; label: string; cls: string }[]
						).map((opt) => (
							<button
								key={opt.val}
								type="button"
								onClick={() => {
									handleDirectionChange(opt.val);
								}}
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
										data.direction === opt.val ? "#fff" : "var(--color-text)",
									fontWeight: data.direction === opt.val ? 700 : 400,
									fontSize: 14,
									cursor: "pointer",
									transition: "all .15s",
								}}>
								{opt.label}
							</button>
						))}
					</div>
				</div>
			) : null}

			{/* 始発・終点（方向が決まったら表示） */}
			{data.lineId !== "" && data.direction !== null ? (
				<>
					<div className="field-row">
						<div className="field">
							<label>{`${t.from}（始発）`}</label>
							<select
								value={data.fromStationId !== "" ? data.fromStationId : ""}
								onChange={(e) => {
									handleFromChange(e.target.value);
								}}>
								<option value="">{`── 選択 ──`}</option>
								{fromStationCandidates.map((s) => (
									<option
										key={s.id}
										value={s.id}>
										{s.stationName}
									</option>
								))}
							</select>
						</div>
						<div className="field">
							<label>{`${t.to}（終点）`}</label>
							<select
								value={data.toStationId !== "" ? data.toStationId : ""}
								onChange={(e) => {
									setData((d) => ({
										...d,
										toStationId: e.target.value,
									}));
								}}
								disabled={data.fromStationId === ""}>
								<option value="">
									{data.fromStationId !== ""
										? "── 選択 ──"
										: "── 始発を先に選択 ──"}
								</option>
								{toStationCandidates.map((s) => (
									<option
										key={s.id}
										value={s.id}>
										{s.stationName}
									</option>
								))}
							</select>
						</div>
					</div>

					{/* 区間プレビュー */}
					{data.fromStationId !== "" && data.toStationId !== ""
						? (() => {
								const fromSt = stations.find(
									(s) => s.id === data.fromStationId
								);
								const toSt = stations.find((s) => s.id === data.toStationId);
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
										}}>
										<span
											style={{
												color: "var(--color-text-muted)",
											}}>{`
											区間:
										`}</span>
										<span style={{ fontWeight: 600 }}>
											{fromSt?.stationName}
										</span>
										<span
											style={{
												color: "var(--color-text-muted)",
											}}>
											{data.direction === 1 ? "→" : "←"}
										</span>
										<span style={{ fontWeight: 600 }}>{toSt?.stationName}</span>
										<span
											className={`chip ${
												data.direction === 1 ? "green" : "amber"
											}`}
											style={{ marginLeft: 4 }}>
											{data.direction === 1 ? `↓ ${t.down}` : `↑ ${t.up}`}
										</span>
									</div>
								);
							})()
						: null}

					<div className="field">
						<label>{`パターン名`}</label>
						<input
							value={data.name !== "" ? data.name : ""}
							onChange={(e) => {
								setData((d) => ({ ...d, name: e.target.value }));
							}}
							placeholder="例: 快速停車パターン"
						/>
					</div>
				</>
			) : null}
		</div>
	);
};
