// StopPatternStep2.tsx — Step 2: Per-station settings for StopPatternWizard
import { useEffect } from "react";
import type { CSSProperties } from "react";

import type { WizardData, SetData } from "./StopPatternWizardTypes";
import type { Strings } from "../i18n/strings";
import type { Station, StationOnLine, StopPatternRow } from "../types/model";

export type Step2Props = {
	readonly data: WizardData;
	readonly setData: SetData;
	readonly stations: Station[];
	readonly stationsOnLine: StationOnLine[];
	readonly t: Strings;
};

export const StopPatternStep2 = ({
	data,
	setData,
	stations,
	stationsOnLine,
	t,
}: Step2Props) => {
	const lineStations: Station[] = stationsOnLine
		.filter((sol) => sol.lineId === data.lineId)
		.sort(
			(a, b) =>
				(a.location_m !== undefined ? a.location_m : 0) -
				(b.location_m !== undefined ? b.location_m : 0)
		)
		.map((sol) => stations.find((s) => s.id === sol.stationId))
		.filter((s): s is Station => s !== undefined);

	const fromIdx = lineStations.findIndex((s) => s.id === data.fromStationId);
	const toIdx = lineStations.findIndex((s) => s.id === data.toStationId);
	const rangeStations: Station[] =
		fromIdx >= 0 && toIdx >= 0
			? fromIdx <= toIdx
				? lineStations.slice(fromIdx, toIdx + 1)
				: [...lineStations.slice(toIdx, fromIdx + 1)].reverse()
			: lineStations;

	const getRow = (stId: string): StopPatternRow =>
		data.stopRows.find((r) => r.stationId === stId) ?? {
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
		setData((d) => {
			const rows = d.stopRows;
			const existing = rows.find((r) => r.stationId === stId);
			const next =
				existing != null
					? rows.map((r) => (r.stationId === stId ? { ...r, [key]: val } : r))
					: [...rows, { ...getRow(stId), [key]: val }];
			return { ...d, stopRows: next };
		});
	};

	// Ensure all range stations have a row (so defaults are persisted on save)
	useEffect(() => {
		setData((d) => {
			const rows = d.stopRows;
			const missingIds = rangeStations
				.map((s) => s.id)
				.filter((id) => rows.find((r) => r.stationId === id) == null);
			if (missingIds.length === 0) return d;
			const newRows = [...rows, ...missingIds.map((id) => getRow(id))];
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
		background: disabled ? "var(--color-bg)" : "var(--color-content)",
		color: disabled ? "var(--color-text-muted)" : "var(--color-text)",
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
				}}>{`
				各駅の停車パターンを設定してください。始発・終着駅では不要な項目はグレーアウトされます。
			`}</p>
			<div
				style={{
					border: "1px solid var(--color-border)",
					borderRadius: "var(--radius)",
					overflow: "auto",
				}}>
				<table
					style={{
						borderCollapse: "collapse",
						width: "100%",
						fontSize: 13,
						minWidth: 560,
					}}>
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
									borderBottom: "1px solid var(--color-border)",
									borderRight: "1px solid var(--color-border)",
								}}>{`
								駅名
							`}</th>
							<th
								rowSpan={2}
								style={{
									padding: "6px 8px",
									textAlign: "center",
									fontSize: 11,
									color: "var(--color-text-muted)",
									borderBottom: "1px solid var(--color-border)",
									borderRight: "1px solid var(--color-border)",
									width: 48,
								}}>
								{t.track}
							</th>
							<th
								rowSpan={2}
								style={{
									padding: "6px 8px",
									textAlign: "center",
									fontSize: 11,
									color: "var(--color-text-muted)",
									borderBottom: "1px solid var(--color-border)",
									borderRight: "1px solid var(--color-border)",
									width: 40,
								}}>{`
								通
							`}</th>
							<th
								rowSpan={2}
								style={{
									padding: "6px 8px",
									textAlign: "center",
									fontSize: 11,
									color: "var(--color-text-muted)",
									borderBottom: "1px solid var(--color-border)",
									borderRight: "1px solid var(--color-border)",
									width: 40,
								}}>{`
								運停
							`}</th>
							<th
								colSpan={2}
								style={{
									padding: "4px 8px",
									textAlign: "center",
									fontSize: 11,
									color: "var(--color-text-muted)",
									borderBottom: "1px solid var(--color-border)",
									borderRight: "1px solid var(--color-border)",
								}}>{`
								所要時間（前駅から）
							`}</th>
							<th
								colSpan={2}
								style={{
									padding: "4px 8px",
									textAlign: "center",
									fontSize: 11,
									color: "var(--color-text-muted)",
									borderBottom: "1px solid var(--color-border)",
								}}>{`
								停車時間（停車駅のみ）
							`}</th>
						</tr>
						<tr style={{ background: "var(--color-bg)" }}>
							<th
								style={{
									padding: "4px 6px",
									textAlign: "center",
									fontSize: 10,
									color: "var(--color-text-muted)",
									borderBottom: "2px solid var(--color-border)",
									width: 48,
								}}>{`
								分
							`}</th>
							<th
								style={{
									padding: "4px 6px",
									textAlign: "center",
									fontSize: 10,
									color: "var(--color-text-muted)",
									borderBottom: "2px solid var(--color-border)",
									borderRight: "1px solid var(--color-border)",
									width: 48,
								}}>{`
								秒
							`}</th>
							<th
								style={{
									padding: "4px 6px",
									textAlign: "center",
									fontSize: 10,
									color: "var(--color-text-muted)",
									borderBottom: "2px solid var(--color-border)",
									width: 48,
								}}>{`
								分
							`}</th>
							<th
								style={{
									padding: "4px 6px",
									textAlign: "center",
									fontSize: 10,
									color: "var(--color-text-muted)",
									borderBottom: "2px solid var(--color-border)",
									width: 48,
								}}>{`
								秒
							`}</th>
						</tr>
					</thead>
					<tbody>
						{rangeStations.map((st, i) => {
							const row = getRow(st.id);
							const isFirst = i === 0;
							const isLast = i === rangeStations.length - 1;
							const driveDis = isFirst;
							const dwellDis = (row.isPass ?? false) || isFirst || isLast;
							const rowBg =
								(row.isPass ?? false)
									? "var(--color-pass-bg)"
									: i % 2 === 0
										? "transparent"
										: "rgba(0,0,0,0.012)";
							return (
								<tr
									key={st.id}
									style={{ background: rowBg }}>
									<td
										style={{
											padding: "5px 10px",
											borderBottom: "1px solid var(--color-border)",
											borderRight: "1px solid var(--color-border)",
											color:
												(row.isPass ?? false)
													? "var(--color-pass-text)"
													: "var(--color-text)",
											whiteSpace: "nowrap",
										}}>
										{st.stationName}
										{isFirst ? (
											<span
												style={{
													fontSize: 10,
													color: "var(--color-text-muted)",
													marginLeft: 6,
												}}>{`
												始発
											`}</span>
										) : null}
										{isLast ? (
											<span
												style={{
													fontSize: 10,
													color: "var(--color-success)",
													marginLeft: 6,
													fontWeight: 600,
												}}>{`
												終着
											`}</span>
										) : null}
									</td>
									<td
										style={{
											padding: "3px 4px",
											borderBottom: "1px solid var(--color-border)",
											borderRight: "1px solid var(--color-border)",
											textAlign: "center",
										}}>
										<input
											value={row.trackName !== undefined ? row.trackName : "1"}
											onChange={(e) => {
												updateRow(st.id, "trackName", e.target.value);
											}}
											style={cellStyle(false)}
										/>
									</td>
									<td
										style={{
											padding: "3px",
											borderBottom: "1px solid var(--color-border)",
											borderRight: "1px solid var(--color-border)",
											textAlign: "center",
										}}>
										<input
											type="checkbox"
											checked={!!(row.isPass ?? false)}
											onChange={(e) => {
												updateRow(st.id, "isPass", e.target.checked);
											}}
											style={{
												accentColor: "var(--color-accent)",
												width: 14,
												height: 14,
											}}
										/>
									</td>
									<td
										style={{
											padding: "3px",
											borderBottom: "1px solid var(--color-border)",
											borderRight: "1px solid var(--color-border)",
											textAlign: "center",
										}}>
										<input
											type="checkbox"
											checked={!!(row.isOperationOnlyStop ?? false)}
											onChange={(e) => {
												updateRow(
													st.id,
													"isOperationOnlyStop",
													e.target.checked
												);
											}}
											disabled={row.isPass}
											style={{
												accentColor: "var(--color-accent)",
												width: 14,
												height: 14,
												opacity: (row.isPass ?? false) ? 0.3 : 1,
											}}
										/>
									</td>
									{/* Drive time MM */}
									<td
										style={{
											padding: "3px 4px",
											borderBottom: "1px solid var(--color-border)",
										}}>
										<input
											type="number"
											value={driveDis ? "" : (row.driveTime_MM ?? 0)}
											min={0}
											onChange={(e) => {
												updateRow(st.id, "driveTime_MM", +e.target.value);
											}}
											disabled={driveDis}
											style={cellStyle(driveDis)}
											title={driveDis ? "始発駅は所要時間不要" : ""}
										/>
									</td>
									{/* Drive time SS */}
									<td
										style={{
											padding: "3px 4px",
											borderBottom: "1px solid var(--color-border)",
											borderRight: "1px solid var(--color-border)",
										}}>
										<input
											type="number"
											value={driveDis ? "" : (row.driveTime_SS ?? 0)}
											min={0}
											max={59}
											onChange={(e) => {
												updateRow(st.id, "driveTime_SS", +e.target.value);
											}}
											disabled={driveDis}
											style={cellStyle(driveDis)}
											title={driveDis ? "始発駅は所要時間不要" : ""}
										/>
									</td>
									{/* Dwell time MM */}
									<td
										style={{
											padding: "3px 4px",
											borderBottom: "1px solid var(--color-border)",
										}}>
										<input
											type="number"
											value={dwellDis ? "" : (row.dwellTime_MM ?? 0)}
											min={0}
											onChange={(e) => {
												updateRow(st.id, "dwellTime_MM", +e.target.value);
											}}
											disabled={dwellDis}
											style={cellStyle(dwellDis)}
											title={
												isLast
													? "終着駅は停車時間不要"
													: (row.isPass ?? false)
														? "通過駅は停車時間不要"
														: ""
											}
										/>
									</td>
									{/* Dwell time SS */}
									<td
										style={{
											padding: "3px 4px",
											borderBottom: "1px solid var(--color-border)",
										}}>
										<input
											type="number"
											value={dwellDis ? "" : (row.dwellTime_SS ?? 0)}
											min={0}
											max={59}
											onChange={(e) => {
												updateRow(st.id, "dwellTime_SS", +e.target.value);
											}}
											disabled={dwellDis}
											style={cellStyle(dwellDis)}
											title={
												isLast
													? "終着駅は停車時間不要"
													: (row.isPass ?? false)
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
};
