// LineManagerLineStationsTab.tsx — Line Stations Tab (inline editing with drag-reorder)
import type { CSSProperties, KeyboardEvent } from "react";
import React, { useState, useRef, useEffect } from "react";

import { ICell } from "./LineManagerICell";
import { modelSolToDraft } from "./LineManagerTypes";

import type {
	EntitySolDraft,
	EntitySolUpdate,
	LineStationEntry,
	SolDraft,
} from "./LineManagerTypes";
import type { Line, Station, StationOnLine } from "../types/model";

export type LineStationsTabProps = {
	readonly activeLine: Line;
	readonly lineStations: LineStationEntry[];
	readonly stations: Station[];
	readonly stationsOnLine: StationOnLine[];
	readonly onCreateStationOnLine: (draft: EntitySolDraft) => void;
	readonly onUpdateStationOnLine: (vars: EntitySolUpdate) => void;
	readonly onDeleteStationOnLine: (id: string) => void;
	readonly onReorderStationsOnLine: (updates: EntitySolUpdate[]) => void;
	readonly canWrite?: boolean;
};

export const LineStationsTab = ({
	activeLine,
	lineStations,
	stations,
	stationsOnLine,
	onCreateStationOnLine,
	onUpdateStationOnLine,
	onDeleteStationOnLine,
	onReorderStationsOnLine,
	canWrite = true,
}: LineStationsTabProps) => {
	const [editingId, setEditingId] = useState<string | null>(null); // sol.id or 'new'
	const [draft, setDraft] = useState<SolDraft>({
		location_m: 0,
		longitude_deg: "",
		latitude_deg: "",
		trackHiddenByDefault: false,
	});
	const [dragOver, setDragOver] = useState<string | null>(null);
	const firstInputRef = useRef<HTMLInputElement>(null);
	const firstSelectRef = useRef<HTMLSelectElement>(null);

	// available stations not yet on this line
	const usedIds = new Set(lineStations.map((s) => s.stationId));
	const available = stations.filter((s) => !usedIds.has(s.id));

	const startEditSol = (sol: LineStationEntry) => {
		setEditingId(sol.id);
		setDraft({
			location_m: sol.location_m ?? 0,
			longitude_deg: sol.longitude_deg ?? "",
			latitude_deg: sol.latitude_deg ?? "",
			trackHiddenByDefault: sol.trackHiddenByDefault ?? false,
		});
	};

	const startNew = () => {
		setEditingId("new");
		// guess next km: last station + 5000 m
		const lastKm =
			lineStations.length > 0
				? Math.max(...lineStations.map((s) => s.location_m))
				: 0;
		setDraft({
			stationId: "",
			location_m: lastKm + 5000,
			longitude_deg: "",
			latitude_deg: "",
			trackHiddenByDefault: false,
		});
	};

	useEffect(() => {
		if (editingId != null)
			setTimeout(() => {
				firstInputRef.current?.focus();
				firstSelectRef.current?.focus();
			}, 30);
	}, [editingId]);

	// Build a StationOnLine from the draft (drop '' geo fields → undefined).
	const buildSol = (
		base: Pick<StationOnLine, "id" | "lineId" | "stationId">
	): StationOnLine => {
		const sol: StationOnLine = {
			...base,
			location_m: draft.location_m,
			trackHiddenByDefault: draft.trackHiddenByDefault,
		};
		if (draft.longitude_deg !== "") sol.longitude_deg = draft.longitude_deg;
		if (draft.latitude_deg !== "") sol.latitude_deg = draft.latitude_deg;
		return sol;
	};

	const commitSol = () => {
		if (editingId === "new") {
			if (draft.stationId == null) {
				setEditingId(null);
				return;
			}
			const sol = buildSol({
				id: "_tmp",
				lineId: activeLine.id,
				stationId: draft.stationId,
			});
			onCreateStationOnLine(modelSolToDraft(sol));
		} else if (editingId !== null) {
			const existing = stationsOnLine.find((sol) => sol.id === editingId);
			if (existing !== undefined) {
				const sol = buildSol({
					id: existing.id,
					lineId: existing.lineId,
					stationId: existing.stationId,
				});
				onUpdateStationOnLine({ id: sol.id, ...modelSolToDraft(sol) });
			}
		}
		setEditingId(null);
	};

	const cancelEdit = () => {
		setEditingId(null);
	};

	const removeSol = (solId: string, stName: string) => {
		if (!confirm(`「${stName}」をこの路線から除外しますか？`)) return;
		onDeleteStationOnLine(solId);
		if (editingId === solId) setEditingId(null);
	};

	const set = <K extends keyof SolDraft>(k: K, v: SolDraft[K]) => {
		setDraft((p) => ({ ...p, [k]: v }));
	};

	const handleKeyDown = (
		e: KeyboardEvent<HTMLInputElement | HTMLSelectElement>
	) => {
		if (e.key === "Enter" && !e.nativeEvent.isComposing) {
			e.preventDefault();
			commitSol();
		}
		if (e.key === "Escape") {
			e.preventDefault();
			cancelEdit();
		}
	};

	// Drag-to-reorder (reorder sol in this line only).
	// Known degradation: issues N sequential update mutations for each reordered row.
	const dragItem = useRef<string | null>(null);
	const onDragStart = (
		e: React.DragEvent<HTMLTableRowElement>,
		solId: string
	) => {
		dragItem.current = solId;
		e.dataTransfer.effectAllowed = "move";
	};
	const onDrop = (
		e: React.DragEvent<HTMLTableRowElement>,
		targetId: string
	) => {
		e.preventDefault();
		if (dragItem.current == null || dragItem.current === targetId) {
			setDragOver(null);
			return;
		}
		const mine = [
			...stationsOnLine.filter((sol) => sol.lineId === activeLine.id),
		].sort((a, b) => a.location_m - b.location_m);
		const fromIdx = mine.findIndex((s) => s.id === dragItem.current);
		const toIdx = mine.findIndex((s) => s.id === targetId);
		const [moved] = mine.splice(fromIdx, 1);
		if (moved == null) {
			setDragOver(null);
			return;
		}
		mine.splice(toIdx, 0, moved);
		// Assign monotonic location_m values (1000m intervals) then persist changed rows
		const updates: EntitySolUpdate[] = mine
			.map((sol, idx) => {
				const newLocationM = (idx + 1) * 1000;
				return { sol, newLocationM };
			})
			.filter(({ sol, newLocationM }) => sol.location_m !== newLocationM)
			.map(({ sol, newLocationM }) => ({
				id: sol.id,
				...modelSolToDraft({ ...sol, location_m: newLocationM }),
			}));
		if (updates.length > 0) {
			onReorderStationsOnLine(updates);
		}
		dragItem.current = null;
		setDragOver(null);
	};

	return (
		<div style={{ padding: 14, overflow: "auto", flex: 1 }}>
			<div
				style={{
					display: "flex",
					gap: 8,
					marginBottom: 10,
					alignItems: "center",
				}}>
				<strong style={{ fontSize: 14 }}>{activeLine.name}</strong>
				<span style={{ fontSize: 11, color: "var(--color-text-muted)" }}>{`
					· キロ程順 · 行をクリックで編集 · ドラッグで並べ替え
				`}</span>
			</div>

			<div
				style={{
					border: "1px solid var(--color-border)",
					borderRadius: "var(--radius)",
					overflow: "hidden",
				}}>
				<table
					style={{
						width: "100%",
						borderCollapse: "collapse",
						fontSize: 13,
					}}>
					<thead>
						<tr style={{ background: "var(--color-bg)" }}>
							{(
								[
									["", "28px", "center"],
									["#", "32px", "center"],
									["駅名", "", "left"],
									["フルネーム", "120px", "left"],
									["キロ程", "90px", "right"],
									["番線非表示", "72px", "center"],
									["位置上書き", "72px", "center"],
									["", "64px", "right"],
								] as [string, string, CSSProperties["textAlign"]][]
							).map(([h, w, align]) => (
								<th
									key={`${h}-${String(align)}`}
									style={{
										padding: "7px 8px",
										textAlign: align,
										fontSize: 10,
										fontWeight: 600,
										color: "var(--color-text-muted)",
										textTransform: "uppercase",
										letterSpacing: "0.05em",
										width: w !== "" ? w : undefined,
										whiteSpace: "nowrap",
									}}>
									{h}
								</th>
							))}
						</tr>
					</thead>
					<tbody>
						{lineStations.map((sol, i) => {
							const isEditing = editingId === sol.id;
							const hasOverride =
								(sol.longitude_deg !== undefined &&
									(sol.longitude_deg as unknown) !== "" &&
									sol.longitude_deg != null) ||
								(sol.latitude_deg !== undefined &&
									(sol.latitude_deg as unknown) !== "" &&
									sol.latitude_deg != null);
							return (
								<tr
									key={sol.id}
									draggable={!isEditing && canWrite}
									onDragStart={(e) => {
										if (canWrite) onDragStart(e, sol.id);
									}}
									onDragOver={(e) => {
										if (!canWrite) return;
										e.preventDefault();
										setDragOver(sol.id);
									}}
									onDragLeave={() => {
										setDragOver(null);
									}}
									onDrop={(e) => {
										if (canWrite) onDrop(e, sol.id);
									}}
									onClick={() => {
										if (!isEditing && canWrite) startEditSol(sol);
									}}
									style={{
										borderTop:
											dragOver === sol.id
												? "2px solid var(--color-accent)"
												: "1px solid var(--color-border)",
										background: isEditing
											? "var(--color-accent-bg)"
											: "transparent",
										cursor: isEditing ? "default" : "pointer",
										transition: "background .1s",
									}}
									onMouseEnter={(e) => {
										if (!isEditing)
											e.currentTarget.style.background = "var(--color-bg)";
									}}
									onMouseLeave={(e) => {
										if (!isEditing)
											e.currentTarget.style.background = "transparent";
									}}>
									{/* drag handle */}
									<td
										style={{
											padding: "6px 6px",
											textAlign: "center",
											color: "var(--color-text-muted)",
											fontSize: 12,
											cursor: "grab",
											userSelect: "none",
										}}>{`
										⠿
									`}</td>
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
											{/* editing: show station name as read-only, edit km/flags */}
											<td
												style={{
													padding: "6px 8px",
													fontWeight: 500,
												}}>
												<span
													style={
														(sol.stationDeleted ?? false)
															? {
																	color: "var(--color-danger)",
																	textDecoration: "line-through",
																}
															: undefined
													}>
													{sol.station.stationName}
												</span>
												{(sol.stationDeleted ?? false) ? (
													<span
														style={{
															marginLeft: 6,
															fontSize: 10,
															fontWeight: 600,
															color: "var(--color-danger)",
														}}>{`
														(削除済み)
													`}</span>
												) : null}
											</td>
											<td
												style={{
													padding: "4px 4px",
													fontSize: 12,
													color: "var(--color-text-muted)",
												}}>
												{sol.station.fullName}
											</td>
											<td style={{ padding: "4px 4px" }}>
												<div
													style={{
														display: "flex",
														alignItems: "center",
														gap: 4,
													}}>
													<ICell
														inputRef={firstInputRef}
														type="number"
														value={draft.location_m}
														onChange={(v) => {
															set("location_m", v === "" ? 0 : (v as number));
														}}
														onKeyDown={handleKeyDown}
														mono
														alignRight
														inputStyle={{ width: 70 }}
													/>
													<span
														style={{
															fontSize: 11,
															color: "var(--color-text-muted)",
															whiteSpace: "nowrap",
														}}>{`
														m
													`}</span>
												</div>
											</td>
											<td
												style={{
													padding: "4px 8px",
													textAlign: "center",
												}}>
												<input
													type="checkbox"
													checked={!!draft.trackHiddenByDefault}
													onChange={(e) => {
														set("trackHiddenByDefault", e.target.checked);
													}}
													title="番線をデフォルトで非表示にする"
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
													textAlign: "center",
													fontSize: 11,
													color: "var(--color-text-muted)",
												}}>
												<span title="位置上書きはモーダルで設定">{`—`}</span>
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
														commitSol();
													}}>{`
													✓
												`}</button>
												<button
													type="button"
													className="btn btn-ghost btn-xs"
													style={{ marginRight: 4 }}
													onClick={(e) => {
														e.stopPropagation();
														cancelEdit();
													}}>{`
													✕
												`}</button>
												<button
													type="button"
													className="btn btn-ghost btn-xs"
													style={{ color: "var(--color-danger)" }}
													onClick={(e) => {
														e.stopPropagation();
														removeSol(sol.id, sol.station.stationName);
													}}>{`
													🗑
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
												<span
													style={
														(sol.stationDeleted ?? false)
															? {
																	color: "var(--color-danger)",
																	textDecoration: "line-through",
																}
															: undefined
													}>
													{sol.station.stationName}
												</span>
												{(sol.stationDeleted ?? false) ? (
													<span
														style={{
															marginLeft: 6,
															fontSize: 10,
															fontWeight: 600,
															color: "var(--color-danger)",
														}}>{`
														(削除済み)
													`}</span>
												) : null}
											</td>
											<td
												style={{
													padding: "6px 8px",
													color: "var(--color-text-muted)",
													fontSize: 12,
												}}>
												{sol.station.fullName}
											</td>
											<td
												style={{
													padding: "6px 8px",
													textAlign: "right",
													fontFamily: "var(--font-mono)",
													fontSize: 12,
												}}>
												{(sol.location_m / 1000).toFixed(1)}
												{` km
											`}
											</td>
											<td
												style={{
													padding: "6px 8px",
													textAlign: "center",
												}}>
												{(sol.trackHiddenByDefault ?? false) ? (
													<span
														className="chip amber"
														style={{ fontSize: 10 }}>{`
														非表示
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
											<td
												style={{
													padding: "6px 8px",
													textAlign: "center",
												}}>
												{hasOverride ? (
													<span
														className="chip amber"
														style={{ fontSize: 10 }}>{`
														上書き
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
											<td
												style={{
													padding: "4px 6px",
													textAlign: "right",
												}}>
												{canWrite ? (
													<button
														type="button"
														className="btn btn-ghost btn-xs"
														style={{
															color: "var(--color-danger)",
															opacity: 0.6,
														}}
														onClick={(e) => {
															e.stopPropagation();
															removeSol(sol.id, sol.station.stationName);
														}}>{`
														🗑
													`}</button>
												) : null}
											</td>
										</>
									)}
								</tr>
							);
						})}

						{/* Add new station to line row */}
						{editingId === "new" ? (
							<tr
								style={{
									background: "var(--color-accent-bg)",
									borderTop: "2px dashed var(--color-accent)",
								}}>
								<td
									colSpan={2}
									style={{
										padding: "4px 8px",
										color: "var(--color-text-muted)",
										fontSize: 11,
										textAlign: "center",
									}}>{`
									新
								`}</td>
								<td
									colSpan={2}
									style={{ padding: "4px 6px" }}>
									<select
										ref={firstSelectRef}
										value={draft.stationId}
										onChange={(e) => {
											set("stationId", e.target.value);
										}}
										onKeyDown={handleKeyDown}
										style={{
											width: "100%",
											padding: "5px 8px",
											border: "1px solid var(--color-accent)",
											borderRadius: "var(--radius)",
											fontSize: 12,
											background: "var(--color-content)",
											color: "var(--color-text)",
											outline: "none",
										}}>
										<option value="">{`── 駅を選択 ──`}</option>
										{available.map((s) => (
											<option
												key={s.id}
												value={s.id}>
												{s.stationName}
												{`（`}
												{s.fullName}
												{`）
											`}
											</option>
										))}
									</select>
									{available.length === 0 && (
										<div
											style={{
												fontSize: 11,
												color: "var(--color-text-muted)",
												marginTop: 4,
											}}>{`
											追加できる駅がありません。「駅（全体）」タブから先に登録してください。
										`}</div>
									)}
								</td>
								<td style={{ padding: "4px 4px" }}>
									<div
										style={{
											display: "flex",
											alignItems: "center",
											gap: 4,
										}}>
										<ICell
											type="number"
											value={draft.location_m}
											onChange={(v) => {
												set("location_m", v === "" ? 0 : (v as number));
											}}
											onKeyDown={handleKeyDown}
											mono
											alignRight
											inputStyle={{ width: 70 }}
										/>
										<span
											style={{
												fontSize: 11,
												color: "var(--color-text-muted)",
											}}>{`
											m
										`}</span>
									</div>
								</td>
								<td
									style={{
										padding: "4px 8px",
										textAlign: "center",
									}}>
									<input
										type="checkbox"
										checked={!!draft.trackHiddenByDefault}
										onChange={(e) => {
											set("trackHiddenByDefault", e.target.checked);
										}}
										style={{
											accentColor: "var(--color-accent)",
											width: 14,
											height: 14,
										}}
									/>
								</td>
								<td />
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
											commitSol();
										}}>{`
										✓ 追加
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
									colSpan={8}
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
										既存の駅を路線に追加
									`}
									</button>
								</td>
							</tr>
						) : null}
					</tbody>
				</table>
			</div>
		</div>
	);
};
