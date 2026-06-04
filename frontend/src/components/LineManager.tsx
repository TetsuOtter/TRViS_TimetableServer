// LineManager.tsx — Line & station management (inline editing)
// Ported 1:1 from the design prototype LineManager.jsx.
import { useState, useMemo, useRef, useEffect, useCallback } from "react";
import type { CSSProperties, KeyboardEvent, RefObject } from "react";

import { StationTrackManager } from "./StationTrackManager";

import type { Strings } from "../i18n/strings";
import type {
	Line as EntityLine,
	ProjectStation as EntityProjectStation,
	StationOnLine as EntityStationOnLine,
} from "../types/entities";
import type { Line, Station, StationOnLine, StopPattern } from "../types/model";

/* ─── Model → entity-draft converters ─── */
type EntityLineDraft = Omit<EntityLine, "id" | "projectId" | "createdAt">;
type EntityLineUpdate = Pick<EntityLine, "id"> &
	Omit<EntityLine, "id" | "projectId" | "createdAt">;

type EntityStationDraft = Omit<
	EntityProjectStation,
	"id" | "projectId" | "createdAt"
>;
type EntityStationUpdate = Pick<EntityProjectStation, "id"> &
	Omit<EntityProjectStation, "id" | "projectId" | "createdAt">;

type EntitySolDraft = Omit<
	EntityStationOnLine,
	"id" | "projectId" | "createdAt"
>;
type EntitySolUpdate = Pick<EntityStationOnLine, "id"> &
	Omit<EntityStationOnLine, "id" | "projectId" | "createdAt">;

function modelLineToDraft(l: Line): EntityLineDraft {
	return {
		name: l.name,
		description: l.description,
	};
}

function modelStationToDraft(s: Station): EntityStationDraft {
	return {
		name: s.stationName,
		fullName: s.fullName !== "" ? s.fullName : undefined,
		longitude: s.longitude_deg,
		latitude: s.latitude_deg,
		onStationDetectRadiusM: s.onStationDetectRadius_m,
		alwaysShowHh: s.alwaysShowHH,
	};
}

function modelSolToDraft(sol: StationOnLine): EntitySolDraft {
	return {
		lineId: sol.lineId,
		projectStationId: sol.stationId,
		locationM: sol.location_m,
		longitude: sol.longitude_deg,
		latitude: sol.latitude_deg,
		trackHiddenByDefault: sol.trackHiddenByDefault,
	};
}

/* A station-on-line joined with its station for display in LineStationsTab. */
type LineStationEntry = {
	station: Station;
} & StationOnLine;

/* Local draft shape for the global Stations tab (geo fields may be ''). */
type StationDraft = {
	stationName: string;
	fullName: string;
	longitude_deg: number | "";
	latitude_deg: number | "";
	onStationDetectRadius_m: number | "";
	alwaysShowHH: boolean;
};

/* Local draft shape for the line-stations tab. */
type SolDraft = {
	stationId?: string;
	location_m: number;
	longitude_deg: number | "";
	latitude_deg: number | "";
	trackHiddenByDefault: boolean;
};

/* ─── Inline-editable cell ─── */
type ICellProps = {
	readonly value: string | number;
	readonly onChange: (v: string | number) => void;
	readonly onKeyDown?: (e: KeyboardEvent<HTMLInputElement>) => void;
	readonly placeholder?: string;
	readonly type?: string;
	readonly step?: string;
	readonly style?: CSSProperties;
	readonly inputRef?: RefObject<HTMLInputElement | null>;
	readonly mono?: boolean;
	readonly alignRight?: boolean;
};

const ICell = ({
	value,
	onChange,
	onKeyDown,
	placeholder,
	type = "text",
	step,
	style,
	inputRef,
	mono,
	alignRight,
}: ICellProps) => {
	return (
		<input
			ref={inputRef}
			type={type}
			step={step}
			value={value}
			onChange={(e) => {
				onChange(
					type === "number"
						? e.target.value === ""
							? ""
							: +e.target.value
						: e.target.value
				);
			}}
			onKeyDown={onKeyDown}
			placeholder={placeholder}
			style={{
				width: "100%",
				padding: "4px 6px",
				border: "1px solid var(--color-accent)",
				borderRadius: "var(--radius)",
				fontSize: 12,
				fontFamily: mono ? "var(--font-mono)" : "var(--font-main)",
				background: "var(--color-content)",
				color: "var(--color-text)",
				outline: "none",
				textAlign: alignRight ? "right" : "left",
				...style,
			}}
		/>
	);
};

/* ─── Global Stations Tab (inline editing) ─── */
type StationsTabProps = {
	readonly stations: Station[];
	readonly stationsOnLine: StationOnLine[];
	readonly lines: Line[];
	readonly onCreateStation: (draft: EntityStationDraft) => void;
	readonly onUpdateStation: (vars: EntityStationUpdate) => void;
	readonly onDeleteStation: (id: string) => void;
	readonly t: Strings;
};

const StationsTab = ({
	stations,
	stationsOnLine,
	lines,
	onCreateStation,
	onUpdateStation,
	onDeleteStation,
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
			stationName: s.stationName || "",
			fullName: s.fullName || "",
			longitude_deg: s.longitude_deg ?? "",
			latitude_deg: s.latitude_deg ?? "",
			onStationDetectRadius_m: s.onStationDetectRadius_m ?? 300,
			alwaysShowHH: s.alwaysShowHH || false,
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
		if (editingId) setTimeout(() => firstInputRef.current?.focus(), 30);
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
		if (!draft.stationName?.trim()) {
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
		if (e.key === "Enter") {
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

	const COL_W = {
		num: 36,
		name: 120,
		full: 150,
		lon: 96,
		lat: 96,
		rad: 72,
		hh: 56,
		lines: 120,
		act: 64,
	};

	const HeaderRow = () => (
		<tr style={{ background: "var(--color-bg)" }}>
			{(
				[
					["#", "", COL_W.num, "center"],
					["駅名（短）", "", COL_W.name, "left"],
					["フルネーム", "", COL_W.full, "left"],
					["経度", "", COL_W.lon, "right"],
					["緯度", "", COL_W.lat, "right"],
					["検出半径", "", COL_W.rad, "right"],
					["HH常表示", "", COL_W.hh, "center"],
					["使用路線", "", COL_W.lines, "left"],
					["", "", COL_W.act, "right"],
				] as [string, string, number, CSSProperties["textAlign"]][]
			).map(([h, , w, align], i) => (
				<th
					key={i}
					style={{
						padding: "7px 8px",
						textAlign: align,
						fontSize: 10,
						fontWeight: 600,
						color: "var(--color-text-muted)",
						textTransform: "uppercase",
						letterSpacing: "0.05em",
						width: w,
						whiteSpace: "nowrap",
					}}>
					{h}
				</th>
			))}
		</tr>
	);

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
					}}>
					クイック追加:
				</span>
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
						<HeaderRow />
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
										if (!isEditing) startEdit(s);
									}}
									style={{
										borderTop: "1px solid var(--color-border)",
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
												<ICell
													inputRef={firstInputRef}
													value={draft.stationName}
													onChange={(v) => {
														set("stationName", v as string);
													}}
													onKeyDown={(e) => {
														handleKeyDown(e, false);
													}}
													placeholder="横浜"
												/>
											</td>
											<td style={{ padding: "4px 4px" }}>
												<ICell
													value={draft.fullName}
													onChange={(v) => {
														set("fullName", v as string);
													}}
													onKeyDown={(e) => {
														handleKeyDown(e, false);
													}}
													placeholder="横浜駅"
												/>
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
													<span>未使用</span>
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
													className="btn btn-primary btn-xs"
													style={{ marginRight: 4 }}
													onClick={(e) => {
														e.stopPropagation();
														commit();
													}}>
													✓ 確定
												</button>
												<button
													className="btn btn-ghost btn-xs"
													onClick={(e) => {
														e.stopPropagation();
														cancelEdit();
													}}>
													✕
												</button>
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
												{hasGeo ? (+s.longitude_deg!).toFixed(4) : "—"}
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
												{hasGeo ? (+s.latitude_deg!).toFixed(4) : "—"}
											</td>
											<td
												style={{
													padding: "6px 8px",
													textAlign: "right",
													fontFamily: "var(--font-mono)",
													fontSize: 12,
													color: "var(--color-text-muted)",
												}}>
												{s.onStationDetectRadius_m || 300} m
											</td>
											<td
												style={{
													padding: "6px 8px",
													textAlign: "center",
												}}>
												{s.alwaysShowHH ? (
													<span
														className="chip green"
														style={{ fontSize: 10, padding: "1px 5px" }}>
														ON
													</span>
												) : (
													<span
														style={{
															color: "var(--color-text-muted)",
															fontSize: 12,
														}}>
														—
													</span>
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
															}}>
															未使用
														</span>
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
												<button
													className="btn btn-ghost btn-xs"
													title={t.trackManager}
													onClick={(e) => {
														e.stopPropagation();
														setTracksStation({
															id: s.id,
															name: s.stationName,
														});
													}}>
													🛤
												</button>
												<button
													className="btn btn-ghost btn-xs"
													style={{
														color: "var(--color-danger)",
														opacity: 0.7,
													}}
													onClick={(e) => {
														e.stopPropagation();
														deleteStation(s.id);
													}}>
													🗑
												</button>
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
									}}>
									新
								</td>
								<td style={{ padding: "4px 4px" }}>
									<ICell
										inputRef={firstInputRef}
										value={draft.stationName}
										onChange={(v) => {
											set("stationName", v as string);
										}}
										onKeyDown={(e) => {
											handleKeyDown(e, false);
										}}
										placeholder="駅名（短）"
									/>
								</td>
								<td style={{ padding: "4px 4px" }}>
									<ICell
										value={draft.fullName}
										onChange={(v) => {
											set("fullName", v as string);
										}}
										onKeyDown={(e) => {
											handleKeyDown(e, false);
										}}
										placeholder="フルネーム"
									/>
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
									}}>
									—
								</td>
								<td
									style={{
										padding: "4px 6px",
										textAlign: "right",
										whiteSpace: "nowrap",
									}}>
									<button
										className="btn btn-primary btn-xs"
										style={{ marginRight: 4 }}
										onClick={(e) => {
											e.stopPropagation();
											commit();
										}}>
										✓ 確定
									</button>
									<button
										className="btn btn-ghost btn-xs"
										onClick={(e) => {
											e.stopPropagation();
											cancelEdit();
										}}>
										✕
									</button>
								</td>
							</tr>
						) : (
							<tr
								style={{
									borderTop: "1px dashed var(--color-border)",
								}}>
								<td
									colSpan={9}
									style={{ padding: "6px 8px" }}>
									<button
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
										<span style={{ fontSize: 16, lineHeight: 1 }}>＋</span>{" "}
										新しい駅を追加
									</button>
								</td>
							</tr>
						)}
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

/* ─── Quick-add bar ─── */
type QuickAddBarProps = {
	readonly stations: Station[];
	readonly onAdd: (draft: EntityStationDraft) => void;
};

const QuickAddBar = ({ stations, onAdd }: QuickAddBarProps) => {
	const [name, setName] = useState("");
	const ref = useRef<HTMLInputElement>(null);

	const submit = () => {
		const n = name.trim();
		if (!n) return;
		// auto fullName = name + '駅' if not already ending in 駅
		const fullName = n.endsWith("駅") ? n : n + "駅";
		onAdd({
			name: n,
			fullName,
			onStationDetectRadiusM: 300,
			alwaysShowHh: false,
		});
		setName("");
		ref.current?.focus();
	};

	return (
		<div
			style={{
				display: "flex",
				gap: 6,
				flex: 1,
				alignItems: "center",
			}}>
			<input
				ref={ref}
				value={name}
				onChange={(e) => {
					setName(e.target.value);
				}}
				onKeyDown={(e) => {
					if (e.key === "Enter") {
						e.preventDefault();
						submit();
					}
				}}
				placeholder="駅名を入力してEnter — 複数連続で追加できます"
				style={{
					flex: 1,
					padding: "5px 10px",
					border: "1px solid var(--color-border)",
					borderRadius: "var(--radius)",
					fontSize: 13,
					background: "var(--color-content)",
					color: "var(--color-text)",
					outline: "none",
					fontFamily: "var(--font-main)",
				}}
				onFocus={(e) => (e.target.style.borderColor = "var(--color-accent)")}
				onBlur={(e) => (e.target.style.borderColor = "var(--color-border)")}
			/>
			<button
				className="btn btn-primary btn-sm"
				onClick={submit}
				disabled={!name.trim()}>
				追加
			</button>
			<span
				style={{
					fontSize: 11,
					color: "var(--color-text-muted)",
					whiteSpace: "nowrap",
				}}>
				{stations.length}駅登録済み
			</span>
		</div>
	);
};

/* ─── Line Stations Tab (inline editing with drag-reorder) ─── */
type LineStationsTabProps = {
	readonly activeLine: Line;
	readonly lineStations: LineStationEntry[];
	readonly stations: Station[];
	readonly stationsOnLine: StationOnLine[];
	readonly onCreateStationOnLine: (draft: EntitySolDraft) => void;
	readonly onUpdateStationOnLine: (vars: EntitySolUpdate) => void;
	readonly onDeleteStationOnLine: (id: string) => void;
	readonly onReorderStationsOnLine: (updates: EntitySolUpdate[]) => void;
};

const LineStationsTab = ({
	activeLine,
	lineStations,
	stations,
	stationsOnLine,
	onCreateStationOnLine,
	onUpdateStationOnLine,
	onDeleteStationOnLine,
	onReorderStationsOnLine,
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
				? Math.max(...lineStations.map((s) => s.location_m || 0))
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
		if (editingId)
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
			if (!draft.stationId) {
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
		if (e.key === "Enter") {
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
		if (!dragItem.current || dragItem.current === targetId) {
			setDragOver(null);
			return;
		}
		const mine = [
			...stationsOnLine.filter((sol) => sol.lineId === activeLine.id),
		].sort((a, b) => (a.location_m || 0) - (b.location_m || 0));
		const fromIdx = mine.findIndex((s) => s.id === dragItem.current);
		const toIdx = mine.findIndex((s) => s.id === targetId);
		const [moved] = mine.splice(fromIdx, 1);
		if (!moved) {
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
				<span style={{ fontSize: 11, color: "var(--color-text-muted)" }}>
					· キロ程順 · 行をクリックで編集 · ドラッグで並べ替え
				</span>
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
							).map(([h, w, align], i) => (
								<th
									key={i}
									style={{
										padding: "7px 8px",
										textAlign: align,
										fontSize: 10,
										fontWeight: 600,
										color: "var(--color-text-muted)",
										textTransform: "uppercase",
										letterSpacing: "0.05em",
										width: w || undefined,
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
									draggable={!isEditing}
									onDragStart={(e) => {
										onDragStart(e, sol.id);
									}}
									onDragOver={(e) => {
										e.preventDefault();
										setDragOver(sol.id);
									}}
									onDragLeave={() => {
										setDragOver(null);
									}}
									onDrop={(e) => {
										onDrop(e, sol.id);
									}}
									onClick={() => {
										if (!isEditing) startEditSol(sol);
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
										}}>
										⠿
									</td>
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
														sol.stationDeleted
															? {
																	color: "var(--color-danger)",
																	textDecoration: "line-through",
																}
															: undefined
													}>
													{sol.station.stationName}
												</span>
												{sol.stationDeleted ? (
													<span
														style={{
															marginLeft: 6,
															fontSize: 10,
															fontWeight: 600,
															color: "var(--color-danger)",
														}}>
														(削除済み)
													</span>
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
														style={{ width: 70 }}
													/>
													<span
														style={{
															fontSize: 11,
															color: "var(--color-text-muted)",
															whiteSpace: "nowrap",
														}}>
														m
													</span>
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
												<span title="位置上書きはモーダルで設定">—</span>
											</td>
											<td
												style={{
													padding: "4px 6px",
													textAlign: "right",
													whiteSpace: "nowrap",
												}}>
												<button
													className="btn btn-primary btn-xs"
													style={{ marginRight: 4 }}
													onClick={(e) => {
														e.stopPropagation();
														commitSol();
													}}>
													✓
												</button>
												<button
													className="btn btn-ghost btn-xs"
													style={{ marginRight: 4 }}
													onClick={(e) => {
														e.stopPropagation();
														cancelEdit();
													}}>
													✕
												</button>
												<button
													className="btn btn-ghost btn-xs"
													style={{ color: "var(--color-danger)" }}
													onClick={(e) => {
														e.stopPropagation();
														removeSol(sol.id, sol.station.stationName);
													}}>
													🗑
												</button>
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
														sol.stationDeleted
															? {
																	color: "var(--color-danger)",
																	textDecoration: "line-through",
																}
															: undefined
													}>
													{sol.station.stationName}
												</span>
												{sol.stationDeleted ? (
													<span
														style={{
															marginLeft: 6,
															fontSize: 10,
															fontWeight: 600,
															color: "var(--color-danger)",
														}}>
														(削除済み)
													</span>
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
												{((sol.location_m || 0) / 1000).toFixed(1)} km
											</td>
											<td
												style={{
													padding: "6px 8px",
													textAlign: "center",
												}}>
												{sol.trackHiddenByDefault ? (
													<span
														className="chip amber"
														style={{ fontSize: 10 }}>
														非表示
													</span>
												) : (
													<span
														style={{
															color: "var(--color-text-muted)",
															fontSize: 12,
														}}>
														—
													</span>
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
														style={{ fontSize: 10 }}>
														上書き
													</span>
												) : (
													<span
														style={{
															color: "var(--color-text-muted)",
															fontSize: 12,
														}}>
														—
													</span>
												)}
											</td>
											<td
												style={{
													padding: "4px 6px",
													textAlign: "right",
												}}>
												<button
													className="btn btn-ghost btn-xs"
													style={{
														color: "var(--color-danger)",
														opacity: 0.6,
													}}
													onClick={(e) => {
														e.stopPropagation();
														removeSol(sol.id, sol.station.stationName);
													}}>
													🗑
												</button>
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
									}}>
									新
								</td>
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
										<option value="">── 駅を選択 ──</option>
										{available.map((s) => (
											<option
												key={s.id}
												value={s.id}>
												{s.stationName}（{s.fullName}）
											</option>
										))}
									</select>
									{available.length === 0 && (
										<div
											style={{
												fontSize: 11,
												color: "var(--color-text-muted)",
												marginTop: 4,
											}}>
											追加できる駅がありません。「駅（全体）」タブから先に登録してください。
										</div>
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
											style={{ width: 70 }}
										/>
										<span
											style={{
												fontSize: 11,
												color: "var(--color-text-muted)",
											}}>
											m
										</span>
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
										className="btn btn-primary btn-xs"
										style={{ marginRight: 4 }}
										onClick={(e) => {
											e.stopPropagation();
											commitSol();
										}}>
										✓ 追加
									</button>
									<button
										className="btn btn-ghost btn-xs"
										onClick={(e) => {
											e.stopPropagation();
											cancelEdit();
										}}>
										✕
									</button>
								</td>
							</tr>
						) : (
							<tr
								style={{
									borderTop: "1px dashed var(--color-border)",
								}}>
								<td
									colSpan={8}
									style={{ padding: "6px 8px" }}>
									<button
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
										<span style={{ fontSize: 16, lineHeight: 1 }}>＋</span>{" "}
										既存の駅を路線に追加
									</button>
								</td>
							</tr>
						)}
					</tbody>
				</table>
			</div>
		</div>
	);
};

/* ─── Stop pattern card ─── */
type StopPatternCardProps = {
	readonly pattern: StopPattern;
	readonly lines: Line[];
	readonly stations: Station[];
	readonly onEdit: () => void;
	readonly onDuplicate: () => void;
	readonly onDelete: (id: string) => void;
};

const StopPatternCard = ({
	pattern,
	lines,
	stations,
	onEdit,
	onDuplicate,
	onDelete,
}: StopPatternCardProps) => {
	const line = lines.find((l) => l.id === pattern.lineId);
	const fromSt = stations.find((s) => s.id === pattern.fromStationId);
	const toSt = stations.find((s) => s.id === pattern.toStationId);
	const stops = (pattern.stopRows || []).filter((r) => !r.isPass).length;
	const passes = (pattern.stopRows || []).filter((r) => r.isPass).length;
	return (
		<div
			className="card"
			style={{
				padding: 14,
				display: "flex",
				flexDirection: "column",
				gap: 8,
			}}>
			<div
				style={{
					display: "flex",
					alignItems: "center",
					gap: 6,
				}}>
				<strong
					style={{
						fontSize: 14,
						flex: 1,
						overflow: "hidden",
						textOverflow: "ellipsis",
						whiteSpace: "nowrap",
					}}>
					{pattern.name || "(無名)"}
				</strong>
				<span className={`chip ${pattern.direction === 1 ? "green" : "amber"}`}>
					{pattern.direction === 1 ? "↓" : "↑"}
				</span>
			</div>
			<div style={{ fontSize: 12, color: "var(--color-text-muted)" }}>
				{line?.name}
			</div>
			<div
				style={{
					display: "flex",
					alignItems: "center",
					gap: 6,
					fontSize: 13,
				}}>
				<span style={{ fontWeight: 500 }}>{fromSt?.stationName || "?"}</span>
				<span style={{ color: "var(--color-text-muted)" }}>→</span>
				<span style={{ fontWeight: 500 }}>{toSt?.stationName || "?"}</span>
			</div>
			<div style={{ fontSize: 11, color: "var(--color-text-muted)" }}>
				停車 {stops}駅 · 通過 {passes}駅
			</div>
			<div
				style={{
					display: "flex",
					gap: 4,
					marginTop: 4,
					borderTop: "1px solid var(--color-border)",
					paddingTop: 8,
				}}>
				<button
					className="btn btn-secondary btn-xs"
					style={{ flex: 1 }}
					onClick={onEdit}>
					✏ 編集
				</button>
				<button
					className="btn btn-ghost btn-xs"
					style={{ flex: 1 }}
					onClick={onDuplicate}
					title="複製">
					⎘ 複製
				</button>
				<button
					className="btn btn-ghost btn-xs"
					onClick={() => {
						if (confirm(`「${pattern.name}」を削除しますか？`))
							onDelete(pattern.id);
					}}
					style={{ color: "var(--color-danger)" }}>
					🗑
				</button>
			</div>
		</div>
	);
};

/* ─── Line edit dialog (kept for line create/edit) ─── */
type LineDraft = {
	id?: string;
	name: string;
	description: string;
};

type LineDialogProps = {
	readonly line: Partial<Line>;
	readonly onSave: (line: LineDraft) => void;
	readonly onDelete: (id: string) => void;
	readonly onClose: () => void;
	readonly t: Strings;
};

const LineDialog = ({
	line,
	onSave,
	onDelete,
	onClose,
	t,
}: LineDialogProps) => {
	const [d, setD] = useState<LineDraft>({
		name: line?.name || "",
		description: line?.description || "",
	});
	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div
				className="modal"
				style={{ maxWidth: 460 }}>
				<div className="modal-header">
					<span className="modal-title">
						🛤 {line?.id ? t.edit : t.addLine}
					</span>
					<button
						className="btn btn-ghost btn-sm"
						onClick={onClose}>
						✕
					</button>
				</div>
				<div className="modal-body">
					<div
						className="field"
						style={{ marginBottom: 12 }}>
						<label>路線名</label>
						<input
							value={d.name}
							onChange={(e) => {
								setD((p) => ({ ...p, name: e.target.value }));
							}}
							placeholder="例: 東海道本線"
							autoFocus
						/>
					</div>
					<div className="field">
						<label>{t.description}</label>
						<textarea
							value={d.description}
							onChange={(e) => {
								setD((p) => ({ ...p, description: e.target.value }));
							}}
							rows={3}
							placeholder="例: 東京〜小田原間"
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
				</div>
				<div className="modal-footer">
					{line?.id ? (
						<button
							className="btn btn-danger btn-sm"
							style={{ marginRight: "auto" }}
							onClick={() => {
								if (confirm(`「${line.name}」を削除しますか？`)) {
									onDelete(line.id!);
									onClose();
								}
							}}>
							🗑 {t.delete}
						</button>
					) : null}
					<button
						className="btn btn-secondary"
						onClick={onClose}>
						{t.cancel}
					</button>
					<button
						className="btn btn-primary"
						onClick={() => {
							if (!d.name.trim()) return;
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

/* ─── Main ─── */
export type LineManagerProps = {
	readonly lines: Line[];
	readonly stations: Station[];
	readonly stationsOnLine: StationOnLine[];
	readonly stopPatterns: StopPattern[];
	readonly activeLineId: string;
	readonly onSelectLine: (id: string) => void;
	readonly onCreateLine: (draft: EntityLineDraft) => void;
	readonly onUpdateLine: (vars: EntityLineUpdate) => void;
	readonly onDeleteLine: (id: string) => void;
	readonly onCreateStation: (draft: EntityStationDraft) => void;
	readonly onUpdateStation: (vars: EntityStationUpdate) => void;
	readonly onDeleteStation: (id: string) => void;
	readonly onCreateStationOnLine: (draft: EntitySolDraft) => void;
	readonly onUpdateStationOnLine: (vars: EntitySolUpdate) => void;
	readonly onDeleteStationOnLine: (id: string) => void;
	readonly onReorderStationsOnLine: (updates: EntitySolUpdate[]) => void;
	readonly onOpenStopPatternWizard: () => void;
	readonly onEditStopPattern: (p: StopPattern) => void;
	readonly onDeleteStopPattern: (id: string) => void;
	readonly onDuplicateStopPattern: (p: StopPattern) => void;
	readonly t: Strings;
};

export const LineManager = ({
	lines,
	stations,
	stationsOnLine,
	stopPatterns,
	activeLineId,
	onSelectLine,
	onCreateLine,
	onUpdateLine,
	onDeleteLine,
	onCreateStation,
	onUpdateStation,
	onDeleteStation,
	onCreateStationOnLine,
	onUpdateStationOnLine,
	onDeleteStationOnLine,
	onReorderStationsOnLine,
	onOpenStopPatternWizard,
	onEditStopPattern,
	onDeleteStopPattern,
	onDuplicateStopPattern,
	t,
}: LineManagerProps) => {
	const [mainTab, setMainTab] = useState<"lines" | "stations">("lines");
	const [lineTab, setLineTab] = useState<"stations" | "patterns">("stations");
	const [lineDialog, setLineDialog] = useState<Partial<Line> | null>(null);

	const activeLine = lines.find((l) => l.id === activeLineId);
	const lineStations = useMemo<LineStationEntry[]>(
		() =>
			activeLine
				? stationsOnLine
						.filter((sol) => sol.lineId === activeLineId)
						.map((sol) => {
							// A soft-deleted referenced station is absent from the
							// live `stations` list; fall back to the backend-resolved
							// name (sol.stationName) so the row tombstones instead of
							// silently vanishing from the line editor.
							const liveStation = stations.find((s) => s.id === sol.stationId);
							const station: Station | undefined =
								liveStation ??
								(sol.stationName !== undefined
									? {
											id: sol.stationId,
											stationName: sol.stationName,
											fullName: sol.stationName,
										}
									: undefined);
							return { ...sol, station };
						})
						.filter((x): x is LineStationEntry => Boolean(x.station))
						.sort((a, b) => (a.location_m || 0) - (b.location_m || 0))
				: [],
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[activeLine, stationsOnLine, stations]
	);

	const linePatterns = stopPatterns.filter((p) => p.lineId === activeLineId);

	const saveLine = (line: LineDraft) => {
		if (line.id !== undefined && line.id !== "") {
			onUpdateLine({ id: line.id, ...modelLineToDraft(line as Line) });
		} else {
			onCreateLine(modelLineToDraft(line as Line));
		}
	};
	const deleteLine = (id: string) => {
		onDeleteLine(id);
		if (activeLineId === id) {
			const nextLine = lines.find((l) => l.id !== id);
			onSelectLine(nextLine !== undefined ? nextLine.id : "");
		}
	};
	const deletePattern = (id: string) => {
		onDeleteStopPattern(id);
	};

	return (
		<div
			style={{
				padding: "16px 20px",
				display: "flex",
				flexDirection: "column",
				gap: 14,
				minHeight: "100%",
			}}>
			{/* Header */}
			<div
				style={{
					display: "flex",
					alignItems: "center",
					gap: 12,
				}}>
				<h2 style={{ fontSize: 18, fontWeight: 600, margin: 0 }}>
					{t.lineManager}
				</h2>
				<span style={{ fontSize: 12, color: "var(--color-text-muted)" }}>
					路線・駅・停車パターンを管理します
				</span>
				<div style={{ flex: 1 }} />
				{mainTab === "lines" && (
					<button
						className="btn btn-secondary btn-sm"
						onClick={() => {
							setLineDialog({});
						}}>
						＋ {t.addLine}
					</button>
				)}
			</div>

			{/* Main tabs */}
			<div
				style={{
					display: "flex",
					gap: 0,
					borderBottom: "1px solid var(--color-border)",
				}}>
				{(
					[
						["lines", "🛤 路線"],
						["stations", "🚉 駅（全体）"],
					] as ["lines" | "stations", string][]
				).map(([id, lbl]) => (
					<button
						key={id}
						onClick={() => {
							setMainTab(id);
						}}
						style={{
							padding: "8px 20px",
							fontSize: 13,
							fontWeight: mainTab === id ? 600 : 400,
							border: "none",
							background: "transparent",
							cursor: "pointer",
							color:
								mainTab === id
									? "var(--color-accent)"
									: "var(--color-text-muted)",
							borderBottom:
								mainTab === id
									? `2px solid var(--color-accent)`
									: "2px solid transparent",
							marginBottom: -1,
						}}>
						{lbl}
					</button>
				))}
			</div>

			{/* ── LINES tab ── */}
			{mainTab === "lines" && (
				<div
					style={{
						display: "grid",
						gridTemplateColumns: "220px 1fr",
						gap: 16,
						flex: 1,
						minHeight: 0,
					}}>
					{/* line list */}
					<div
						className="card"
						style={{ padding: 8, height: "fit-content" }}>
						<div
							style={{
								padding: "4px 8px 8px",
								fontSize: 11,
								fontWeight: 600,
								color: "var(--color-text-muted)",
								textTransform: "uppercase",
								letterSpacing: "0.06em",
							}}>
							路線一覧 ({lines.length})
						</div>
						{lines.length === 0 && (
							<div
								style={{
									padding: "12px 8px",
									fontSize: 12,
									color: "var(--color-text-muted)",
								}}>
								路線がありません。
							</div>
						)}
						{lines.map((l) => {
							const sc = stationsOnLine.filter(
								(sol) => sol.lineId === l.id
							).length;
							const pc = stopPatterns.filter((p) => p.lineId === l.id).length;
							return (
								<div
									key={l.id}
									style={{ position: "relative" }}>
									<button
										onClick={() => {
											onSelectLine(l.id);
										}}
										style={{
											display: "block",
											width: "100%",
											textAlign: "left",
											padding: "8px 32px 8px 10px",
											borderRadius: "var(--radius)",
											background:
												activeLineId === l.id
													? "var(--color-accent-bg)"
													: "transparent",
											color:
												activeLineId === l.id
													? "var(--color-accent)"
													: "var(--color-text)",
											fontWeight: activeLineId === l.id ? 600 : 400,
											fontSize: 13,
											marginBottom: 2,
											cursor: "pointer",
											border: "none",
										}}>
										<div
											style={{
												whiteSpace: "nowrap",
												overflow: "hidden",
												textOverflow: "ellipsis",
											}}>
											{l.name}
										</div>
										<div
											style={{
												fontSize: 11,
												color: "var(--color-text-muted)",
												marginTop: 2,
												display: "flex",
												gap: 8,
											}}>
											<span>🚉 {sc}</span>
											<span>🧩 {pc}</span>
										</div>
									</button>
									<button
										className="btn btn-ghost btn-xs"
										style={{
											position: "absolute",
											right: 4,
											top: 6,
										}}
										onClick={(e) => {
											e.stopPropagation();
											setLineDialog(l);
										}}>
										⚙
									</button>
								</div>
							);
						})}
					</div>

					{/* line detail */}
					<div
						className="card"
						style={{
							display: "flex",
							flexDirection: "column",
							overflow: "hidden",
						}}>
						{!activeLine ? (
							<div
								className="empty-state"
								style={{ padding: 60 }}>
								<p>路線を選択してください。</p>
							</div>
						) : (
							<>
								<div className="tabs">
									<div
										className={`tab ${lineTab === "stations" ? "active" : ""}`}
										onClick={() => {
											setLineTab("stations");
										}}>
										🚉 経由駅 ({lineStations.length})
									</div>
									<div
										className={`tab ${lineTab === "patterns" ? "active" : ""}`}
										onClick={() => {
											setLineTab("patterns");
										}}>
										🧩 停車パターン ({linePatterns.length})
									</div>
								</div>

								{lineTab === "stations" && (
									<LineStationsTab
										activeLine={activeLine}
										lineStations={lineStations}
										stations={stations}
										stationsOnLine={stationsOnLine}
										onCreateStationOnLine={onCreateStationOnLine}
										onUpdateStationOnLine={onUpdateStationOnLine}
										onDeleteStationOnLine={onDeleteStationOnLine}
										onReorderStationsOnLine={onReorderStationsOnLine}
									/>
								)}

								{lineTab === "patterns" && (
									<div
										style={{
											padding: 16,
											overflow: "auto",
										}}>
										<div
											style={{
												display: "flex",
												gap: 8,
												marginBottom: 12,
												alignItems: "center",
											}}>
											<strong style={{ fontSize: 14 }}>
												{activeLine.name} の停車パターン
											</strong>
											<div style={{ flex: 1 }} />
											<button
												className="btn btn-primary btn-sm"
												onClick={onOpenStopPatternWizard}>
												🧩 ウィザードで作成
											</button>
										</div>
										{linePatterns.length === 0 ? (
											<div
												className="empty-state"
												style={{ padding: 40 }}>
												<svg
													width="44"
													height="44"
													viewBox="0 0 24 24"
													fill="none"
													stroke="currentColor"
													strokeWidth="1.5">
													<rect
														x="3"
														y="3"
														width="18"
														height="18"
														rx="2"
													/>
													<path d="M8 12h8M8 8h8M8 16h5" />
												</svg>
												<p>まだ停車パターンがありません。</p>
											</div>
										) : (
											<div
												style={{
													display: "grid",
													gridTemplateColumns:
														"repeat(auto-fill,minmax(240px,1fr))",
													gap: 12,
												}}>
												{linePatterns.map((p) => (
													<StopPatternCard
														key={p.id}
														pattern={p}
														lines={lines}
														stations={stations}
														onEdit={() => {
															onEditStopPattern && onEditStopPattern(p);
														}}
														onDuplicate={() => {
															onDuplicateStopPattern(p);
														}}
														onDelete={deletePattern}
													/>
												))}
											</div>
										)}
									</div>
								)}
							</>
						)}
					</div>
				</div>
			)}

			{/* ── STATIONS tab ── */}
			{mainTab === "stations" && (
				<StationsTab
					stations={stations}
					stationsOnLine={stationsOnLine}
					lines={lines}
					onCreateStation={onCreateStation}
					onUpdateStation={onUpdateStation}
					onDeleteStation={onDeleteStation}
					t={t}
				/>
			)}

			{/* ── Dialogs ── */}
			{lineDialog !== null && (
				<LineDialog
					line={lineDialog}
					onSave={saveLine}
					onDelete={deleteLine}
					onClose={() => {
						setLineDialog(null);
					}}
					t={t}
				/>
			)}
		</div>
	);
};
