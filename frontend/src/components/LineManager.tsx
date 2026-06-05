// LineManager.tsx — Line & station management (inline editing)
// Ported 1:1 from the design prototype LineManager.jsx.
import { useState, useMemo } from "react";

import { LineDialog } from "./LineManagerLineDialog";
import { LineStationsTab } from "./LineManagerLineStationsTab";
import { StationsTab } from "./LineManagerStationsTab";
import { StopPatternCard } from "./LineManagerStopPatternCard";
import { modelLineToDraft } from "./LineManagerTypes";

import type {
	EntityLineDraft,
	EntityLineUpdate,
	EntityStationDraft,
	EntityStationUpdate,
	EntitySolDraft,
	EntitySolUpdate,
	LineStationEntry,
	LineDraft,
} from "./LineManagerTypes";
import type { Strings } from "../i18n/strings";
import type { Line, Station, StationOnLine, StopPattern } from "../types/model";

/* ─── Main ─── */
export type LineManagerProps = {
	readonly lines: Line[];
	readonly isLoading: boolean;
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
	readonly canWrite?: boolean;
	readonly t: Strings;
};

export const LineManager = ({
	lines,
	isLoading,
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
	canWrite = true,
	t,
}: LineManagerProps) => {
	const [mainTab, setMainTab] = useState<"lines" | "stations">("lines");
	const [lineTab, setLineTab] = useState<"stations" | "patterns">("stations");
	const [lineDialog, setLineDialog] = useState<Partial<Line> | null>(null);

	const activeLine = lines.find((l) => l.id === activeLineId);
	const lineStations = useMemo<LineStationEntry[]>(
		() =>
			activeLine != null
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
						.sort((a, b) => a.location_m - b.location_m)
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
				<span style={{ fontSize: 12, color: "var(--color-text-muted)" }}>{`
					路線・駅・停車パターンを管理します
				`}</span>
				<div style={{ flex: 1 }} />
				{mainTab === "lines" && canWrite ? (
					<button
						type="button"
						className="btn btn-secondary btn-sm"
						onClick={() => {
							setLineDialog({});
						}}>
						{`
						＋ `}
						{t.addLine}
					</button>
				) : null}
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
						type="button"
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
							{`
							路線一覧 (`}
							{lines.length}
							{`)
						`}
						</div>
						{isLoading && lines.length === 0 ? (
							<div
								className="loading-center"
								style={{ padding: "16px 8px" }}>
								<span className="spinner" />
							</div>
						) : lines.length === 0 ? (
							<div
								style={{
									padding: "12px 8px",
									fontSize: 12,
									color: "var(--color-text-muted)",
								}}>{`
								路線がありません。
							`}</div>
						) : null}
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
										type="button"
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
											<span>
												{`🚉 `}
												{sc}
											</span>
											<span>
												{`🧩 `}
												{pc}
											</span>
										</div>
									</button>
									{canWrite ? (
										<button
											type="button"
											className="btn btn-ghost btn-xs"
											style={{
												position: "absolute",
												right: 4,
												top: 6,
											}}
											onClick={(e) => {
												e.stopPropagation();
												setLineDialog(l);
											}}>{`
											⚙
										`}</button>
									) : null}
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
						{activeLine == null ? (
							<div
								className="empty-state"
								style={{ padding: 60 }}>
								<p>{`路線を選択してください。`}</p>
							</div>
						) : (
							<>
								<div className="tabs">
									<div
										className={`tab ${lineTab === "stations" ? "active" : ""}`}
										onClick={() => {
											setLineTab("stations");
										}}>
										{`
										🚉 経由駅 (`}
										{lineStations.length}
										{`)
									`}
									</div>
									<div
										className={`tab ${lineTab === "patterns" ? "active" : ""}`}
										onClick={() => {
											setLineTab("patterns");
										}}>
										{`
										🧩 停車パターン (`}
										{linePatterns.length}
										{`)
									`}
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
										canWrite={canWrite}
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
												{activeLine.name}
												{` の停車パターン
											`}
											</strong>
											<div style={{ flex: 1 }} />
											{canWrite ? (
												<button
													type="button"
													className="btn btn-primary btn-sm"
													onClick={onOpenStopPatternWizard}>{`
													🧩 ウィザードで作成
												`}</button>
											) : null}
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
												<p>{`まだ停車パターンがありません。`}</p>
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
															onEditStopPattern(p);
														}}
														onDuplicate={() => {
															onDuplicateStopPattern(p);
														}}
														onDelete={deletePattern}
														canWrite={canWrite}
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
					canWrite={canWrite}
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
