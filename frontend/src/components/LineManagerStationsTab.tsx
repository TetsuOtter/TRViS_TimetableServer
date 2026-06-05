// LineManagerStationsTab.tsx — Global Stations tab (inline editing)
import { useState, useRef, useEffect, useCallback } from "react";
import type { KeyboardEvent } from "react";

import { ICell } from "./LineManagerICell";
import { QuickAddBar } from "./LineManagerQuickAddBar";
import { StationsHeaderRow } from "./LineManagerStationsHeaderRow";
import { modelStationToDraft } from "./LineManagerTypes";
import { StationTrackManager } from "./StationTrackManager";

import type {
	EntityStationDraft,
	EntityStationUpdate,
	StationDraft,
} from "./LineManagerTypes";
import type { Strings } from "../i18n/strings";
import type { Line, Station, StationOnLine } from "../types/model";

export type StationsTabProps = {
	readonly stations: Station[];
	readonly stationsOnLine: StationOnLine[];
	readonly lines: Line[];
	readonly onCreateStation: (draft: EntityStationDraft) => void;
	readonly onUpdateStation: (vars: EntityStationUpdate) => void;
	readonly onDeleteStation: (id: string) => void;
	readonly canWrite: boolean;
	readonly t: Strings;
};

export const StationsTab = ({
	stations,
	stationsOnLine,
	lines,
	onCreateStation,
	onUpdateStation,
	onDeleteStation,
	canWrite,
	t,
}: StationsTabProps) => {
	const [editingId, setEditingId] = useState<string | null>(null); // station id or 'new'
	// The station whose tracks are being managed in the modal (null = closed).
	const [tracksStation, setTracksStation] = useState<{
		id: string;
		name: string;
	} | null>(null);
	const [draft, setDraft] = useState<StationDraft>({
		stationName: "",
		fullName: "",
		longitude_deg: "",
		latitude_deg: "",
		onStationDetectRadius_m: 300,
		alwaysShowHH: false,
	});
	const firstInputRef = useRef<HTMLInputElement>(null);

	const linesForStation = useCallback(
		(sid: string): Line[] =>
			stationsOnLine
				.filter((sol) => sol.stationId === sid)
				.map((sol) => lines.find((l) => l.id === sol.lineId))
				.filter((l): l is Line => Boolean(l)),
		[stationsOnLine, lines]
	);

	const startEdit = (s: Station) => {
		setEditingId(s.id);
		setDraft({
			stationName: s.stationName,
			fullName: s.fullName,
			longitude_deg: s.longitude_deg ?? "",
			latitude_deg: s.latitude_deg ?? "",
			onStationDetectRadius_m: s.onStationDetectRadius_m ?? 300,
			alwaysShowHH: (s.alwaysShowHH ?? false) || false,
		});
	};

	const startNew = () => {
		setEditingId("new");
		setDraft({
			stationName: "",
			fullName: "",
			longitude_deg: "",
			latitude_deg: "",
			onStationDetectRadius_m: 300,
			alwaysShowHH: false,
		});
	};

	useEffect(() => {
		if (editingId != null) setTimeout(() => firstInputRef.current?.focus(), 30);
	}, [editingId]);

	// Convert the draft into a Station (drop '' geo fields → undefined).
	const draftToStation = (id: string): Station => {
		const st: Station = {
			id,
			stationName: draft.stationName,
			fullName: draft.fullName,
			alwaysShowHH: draft.alwaysShowHH,
		};
		if (draft.longitude_deg !== "") st.longitude_deg = draft.longitude_deg;
		if (draft.latitude_deg !== "") st.latitude_deg = draft.latitude_deg;
		if (draft.onStationDetectRadius_m !== "")
			st.onStationDetectRadius_m = draft.onStationDetectRadius_m;
		return st;
	};

	const commit = () => {
		if (!(draft.stationName?.trim() !== "")) {
			cancelEdit();
			return;
		}
		const entityDraft = modelStationToDraft(draftToStation("_tmp"));
		if (editingId === "new") {
			onCreateStation(entityDraft);
		} else if (editingId !== null) {
			onUpdateStation({ id: editingId, ...entityDraft });
		}
		setEditingId(null);
	};

	const cancelEdit = () => {
		setEditingId(null);
	};

	const deleteStation = (id: string) => {
		const s = stations.find((x) => x.id === id);
		if (
			!confirm(
				`「${s?.stationName}」を削除しますか？\n路線の紐付けも削除されます。`
			)
		)
			return;
		onDeleteStation(id);
		if (editingId === id) setEditingId(null);
	};

	const handleKeyDown = (
		e: KeyboardEvent<HTMLInputElement>,
		isLast: boolean
	) => {
		if (e.key === "Enter" && !e.nativeEvent.isComposing) {
			e.preventDefault();
			commit();
		}
		if (e.key === "Escape") {
			e.preventDefault();
			cancelEdit();
		}
		if (e.key === "Tab" && isLast) {
			e.preventDefault();
			commit();
		}
	};

	const set = <K extends keyof StationDraft>(k: K, v: StationDraft[K]) => {
		setDraft((p) => ({ ...p, [k]: v }));
	};

	return (
		<div style={{ flex: 1, overflow: "auto" }}>
			{/* Quick-add bar at top */}
			<div
				style={{
					display: "flex",
					gap: 8,
					padding: "10px 12px",
					background: "var(--color-content)",
					borderBottom: "1px solid var(--color-border)",
					alignItems: "center",
				}}>
				<span
					style={{
						fontSize: 12,
						color: "var(--color-text-muted)",
						whiteSpace: "nowrap",
					}}>{`
					クイック追加:
				`}</span>
				<QuickAddBar
					stations={stations}
					onAdd={onCreateStation}
				/>
			</div>

			<div
				style={{
					border: "1px solid var(--color-border)",
					borderRadius: "var(--radius)",
					margin: "12px",
					overflow: "hidden",
				}}>
				<table
					style={{
						width: "100%",
						borderCollapse: "collapse",
						fontSize: 13,
					}}>
					<thead>
						<StationsHeaderRow />
					</thead>
					<tbody>
						{stations.map((s, i) => {
							const usedLines = linesForStation(s.id);
							const isEditing = editingId === s.id;
							const hasGeo =
								s.longitude_deg != null &&
								(s.longitude_deg as unknown) !== "" &&
								s.latitude_deg != null &&
								(s.latitude_deg as unknown) !== "";
							return (
								<tr
									key={s.id}
									onClick={() => {
										if (!isEditing && canWrite) startEdit(s);
									}}
									style={{
										borderTop: "1px solid var(--color-border)",
										background: isEditing
											? "var(--color-accent-bg)"
											: "transparent",
										cursor: isEditing || !canWrite ? "default" : "pointer",
										transition: "background .1s",
									}}
									onMouseEnter={(e) => {
										if (!isEditing && canWrite)
											e.currentTarget.style.background = "var(--color-bg)";
									}}
									onMouseLeave={(e) => {
										if (!isEditing && canWrite)
											e.currentTarget.style.background = "transparent";
									}}>
									<td
										style={{
											padding: "6px 8px",
											color: "var(--color-text-muted)",
											fontFamily: "var(--font-mono)",
											fontSize: 11,
											textAlign: "center",
										}}>
										{i + 1}
									</td>

									{isEditing ? (
										<>
											<td style={{ padding: "4px 4px" }}>
												<form
													onSubmit={(e) => {
														e.preventDefault();
														commit();
													}}
													style={{ margin: 0 }}>
													<ICell
														inputRef={firstInputRef}
														value={draft.stationName}
														onChange={(v) => {
															set("stationName", v as string);
														}}
														onKeyDown={(e) => {
															if (e.key === "Escape") {
																e.preventDefault();
																cancelEdit();
															}
														}}
														placeholder="横浜"
													/>
												</form>
											</td>
											<td style={{ padding: "4px 4px" }}>
												<form
													onSubmit={(e) => {
														e.preventDefault();
														commit();
													}}
													style={{ margin: 0 }}>
													<ICell
														value={draft.fullName}
														onChange={(v) => {
															set("fullName", v as string);
														}}
														onKeyDown={(e) => {
															if (e.key === "Escape") {
																e.preventDefault();
																cancelEdit();
															}
														}}
														placeholder="横浜駅"
													/>
												</form>
											</td>
											<td style={{ padding: "4px 4px" }}>
												<ICell
													type="number"
													step="0.000001"
													value={draft.longitude_deg}
													onChange={(v) => {
														set("longitude_deg", v as number | "");
													}}
													onKeyDown={(e) => {
														handleKeyDown(e, false);
													}}
													placeholder="139.621"
													mono
													alignRight
												/>
											</td>
											<td style={{ padding: "4px 4px" }}>
												<ICell
													type="number"
													step="0.000001"
													value={draft.latitude_deg}
													onChange={(v) => {
														set("latitude_deg", v as number | "");
													}}
													onKeyDown={(e) => {
														handleKeyDown(e, false);
													}}
													placeholder="35.466"
													mono
													alignRight
												/>
											</td>
											<td style={{ padding: "4px 4px" }}>
												<ICell
													type="number"
													value={draft.onStationDetectRadius_m}
													onChange={(v) => {
														set("onStationDetectRadius_m", v as number | "");
													}}
													onKeyDown={(e) => {
														handleKeyDown(e, false);
													}}
													mono
													alignRight
												/>
											</td>
											<td
												style={{
													padding: "4px 8px",
													textAlign: "center",
												}}>
												<input
													type="checkbox"
													checked={!!draft.alwaysShowHH}
													onChange={(e) => {
														set("alwaysShowHH", e.target.checked);
													}}
													style={{
														accentColor: "var(--color-accent)",
														width: 14,
														height: 14,
														cursor: "pointer",
													}}
												/>
											</td>
											<td
												style={{
													padding: "4px 8px",
													fontSize: 11,
													color: "var(--color-text-muted)",
												}}
												colSpan={1}>
												{usedLines.length === 0 ? (
													<span>{`未使用`}</span>
												) : (
													usedLines.map((l) => (
														<span
															key={l.id}
															className="chip"
															style={{
																fontSize: 10,
																padding: "1px 5px",
																marginRight: 2,
															}}>
															{l.name}
														</span>
													))
												)}
											</td>
											<td
												style={{
													padding: "4px 6px",
													textAlign: "right",
													whiteSpace: "nowrap",
												}}>
												<button
													type="button"
													className="btn btn-primary btn-xs"
													style={{ marginRight: 4 }}
													onClick={(e) => {
														e.stopPropagation();
														commit();
													}}>{`
													✓ 確定
												`}</button>
												<button
													type="button"
													className="btn btn-ghost btn-xs"
													onClick={(e) => {
														e.stopPropagation();
														cancelEdit();
													}}>{`
													✕
												`}</button>
											</td>
										</>
									) : (
										<>
											<td
												style={{
													padding: "6px 8px",
													fontWeight: 500,
												}}>
												{s.stationName}
											</td>
											<td
												style={{
													padding: "6px 8px",
													color: "var(--color-text-muted)",
													fontSize: 12,
												}}>
												{s.fullName}
											</td>
											<td
												style={{
													padding: "6px 8px",
													textAlign: "right",
													fontFamily: "var(--font-mono)",
													fontSize: 12,
													color: hasGeo
														? "var(--color-text)"
														: "var(--color-text-muted)",
												}}>
												{hasGeo ? (+(s.longitude_deg ?? 0)).toFixed(4) : "—"}
											</td>
											<td
												style={{
													padding: "6px 8px",
													textAlign: "right",
													fontFamily: "var(--font-mono)",
													fontSize: 12,
													color: hasGeo
														? "var(--color-text)"
														: "var(--color-text-muted)",
												}}>
												{hasGeo ? (+(s.latitude_deg ?? 0)).toFixed(4) : "—"}
											</td>
											<td
												style={{
													padding: "6px 8px",
													textAlign: "right",
													fontFamily: "var(--font-mono)",
													fontSize: 12,
													color: "var(--color-text-muted)",
												}}>
												{Boolean(s.onStationDetectRadius_m) || 300}
												{` m
											`}
											</td>
											<td
												style={{
													padding: "6px 8px",
													textAlign: "center",
												}}>
												{(s.alwaysShowHH ?? false) ? (
													<span
														className="chip green"
														style={{ fontSize: 10, padding: "1px 5px" }}>{`
														ON
													`}</span>
												) : (
													<span
														style={{
															color: "var(--color-text-muted)",
															fontSize: 12,
														}}>{`
														—
													`}</span>
												)}
											</td>
											<td style={{ padding: "6px 8px" }}>
												<div
													style={{
														display: "flex",
														gap: 3,
														flexWrap: "wrap",
													}}>
													{usedLines.length === 0 ? (
														<span
															style={{
																fontSize: 11,
																color: "var(--color-text-muted)",
															}}>{`
															未使用
														`}</span>
													) : (
														usedLines.map((l) => (
															<span
																key={l.id}
																className="chip"
																style={{
																	fontSize: 10,
																	padding: "1px 5px",
																}}>
																{l.name}
															</span>
														))
													)}
												</div>
											</td>
											<td
												style={{
													padding: "4px 6px",
													textAlign: "right",
													whiteSpace: "nowrap",
												}}>
												{canWrite ? (
													<>
														<button
															type="button"
															className="btn btn-ghost btn-xs"
															title={t.trackManager}
															onClick={(e) => {
																e.stopPropagation();
																setTracksStation({
																	id: s.id,
																	name: s.stationName,
																});
															}}>{`
															🛤
														`}</button>
														<button
															type="button"
															className="btn btn-ghost btn-xs"
															style={{
																color: "var(--color-danger)",
																opacity: 0.7,
															}}
															onClick={(e) => {
																e.stopPropagation();
																deleteStation(s.id);
															}}>{`
															🗑
														`}</button>
													</>
												) : null}
											</td>
										</>
									)}
								</tr>
							);
						})}

						{/* New row */}
						{editingId === "new" ? (
							<tr
								style={{
									background: "var(--color-accent-bg)",
									borderTop: "2px dashed var(--color-accent)",
								}}>
								<td
									style={{
										padding: "4px 8px",
										color: "var(--color-text-muted)",
										fontSize: 11,
										textAlign: "center",
									}}>{`
									新
								`}</td>
								<td style={{ padding: "4px 4px" }}>
									<form
										onSubmit={(e) => {
											e.preventDefault();
											commit();
										}}
										style={{ margin: 0 }}>
										<ICell
											inputRef={firstInputRef}
											value={draft.stationName}
											onChange={(v) => {
												set("stationName", v as string);
											}}
											onKeyDown={(e) => {
												if (e.key === "Escape") {
													e.preventDefault();
													cancelEdit();
												}
											}}
											placeholder="駅名（短）"
										/>
									</form>
								</td>
								<td style={{ padding: "4px 4px" }}>
									<form
										onSubmit={(e) => {
											e.preventDefault();
											commit();
										}}
										style={{ margin: 0 }}>
										<ICell
											value={draft.fullName}
											onChange={(v) => {
												set("fullName", v as string);
											}}
											onKeyDown={(e) => {
												if (e.key === "Escape") {
													e.preventDefault();
													cancelEdit();
												}
											}}
											placeholder="フルネーム"
										/>
									</form>
								</td>
								<td style={{ padding: "4px 4px" }}>
									<ICell
										type="number"
										step="0.000001"
										value={draft.longitude_deg}
										onChange={(v) => {
											set("longitude_deg", v as number | "");
										}}
										onKeyDown={(e) => {
											handleKeyDown(e, false);
										}}
										placeholder="経度"
										mono
										alignRight
									/>
								</td>
								<td style={{ padding: "4px 4px" }}>
									<ICell
										type="number"
										step="0.000001"
										value={draft.latitude_deg}
										onChange={(v) => {
											set("latitude_deg", v as number | "");
										}}
										onKeyDown={(e) => {
											handleKeyDown(e, false);
										}}
										placeholder="緯度"
										mono
										alignRight
									/>
								</td>
								<td style={{ padding: "4px 4px" }}>
									<ICell
										type="number"
										value={draft.onStationDetectRadius_m}
										onChange={(v) => {
											set("onStationDetectRadius_m", v as number | "");
										}}
										onKeyDown={(e) => {
											handleKeyDown(e, true);
										}}
										mono
										alignRight
									/>
								</td>
								<td style={{ padding: "4px 8px", textAlign: "center" }}>
									<input
										type="checkbox"
										checked={!!draft.alwaysShowHH}
										onChange={(e) => {
											set("alwaysShowHH", e.target.checked);
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
										padding: "4px 8px",
										fontSize: 11,
										color: "var(--color-text-muted)",
									}}>{`
									—
								`}</td>
								<td
									style={{
										padding: "4px 6px",
										textAlign: "right",
										whiteSpace: "nowrap",
									}}>
									<button
										type="button"
										className="btn btn-primary btn-xs"
										style={{ marginRight: 4 }}
										onClick={(e) => {
											e.stopPropagation();
											commit();
										}}>{`
										✓ 確定
									`}</button>
									<button
										type="button"
										className="btn btn-ghost btn-xs"
										onClick={(e) => {
											e.stopPropagation();
											cancelEdit();
										}}>{`
										✕
									`}</button>
								</td>
							</tr>
						) : canWrite ? (
							<tr
								style={{
									borderTop: "1px dashed var(--color-border)",
								}}>
								<td
									colSpan={9}
									style={{ padding: "6px 8px" }}>
									<button
										type="button"
										onClick={startNew}
										style={{
											display: "flex",
											alignItems: "center",
											gap: 6,
											width: "100%",
											padding: "4px 4px",
											border: "none",
											background: "transparent",
											cursor: "pointer",
											fontSize: 12,
											color: "var(--color-text-muted)",
											borderRadius: "var(--radius)",
											transition: "color .1s",
										}}
										onMouseEnter={(e) =>
											(e.currentTarget.style.color = "var(--color-accent)")
										}
										onMouseLeave={(e) =>
											(e.currentTarget.style.color = "var(--color-text-muted)")
										}>
										<span style={{ fontSize: 16, lineHeight: 1 }}>{`＋`}</span>{" "}
										{`
										新しい駅を追加
									`}
									</button>
								</td>
							</tr>
						) : null}
					</tbody>
				</table>
			</div>
			{tracksStation !== null && (
				<StationTrackManager
					stationId={tracksStation.id}
					stationName={tracksStation.name}
					onClose={() => {
						setTracksStation(null);
					}}
					t={t}
				/>
			)}
		</div>
	);
};
