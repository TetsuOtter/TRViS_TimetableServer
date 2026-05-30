// StationTrackManager — modal CRUD for the station_tracks of ONE station.
// Self-contained: owns its own useStationTracks query + mutations, so it can be
// dropped wherever a station id is in hand (here: the LineManager stations tab).
// A timetable row's stationTrackId picks one of these tracks.

import { useState } from "react";

import {
	useCreateStationTrack,
	useDeleteStationTrack,
	useStationTracks,
	useUpdateStationTrack,
} from "../api/hooks/useStationTracks";

import type { Strings } from "../i18n/strings";
import type { StationTrack } from "../types/entities";

type TrackDraft = Omit<StationTrack, "id" | "stationId" | "createdAt">;

const EMPTY: TrackDraft = {
	name: "",
	description: "",
	runInLimit: undefined,
	runOutLimit: undefined,
};

type StationTrackManagerProps = {
	readonly stationId: string;
	readonly stationName: string;
	readonly onClose: () => void;
	readonly t: Strings;
};

export const StationTrackManager = ({
	stationId,
	stationName,
	onClose,
	t,
}: StationTrackManagerProps) => {
	const { data: tracks, isLoading } = useStationTracks(stationId);
	const createMut = useCreateStationTrack(stationId);
	const updateMut = useUpdateStationTrack(stationId);
	const deleteMut = useDeleteStationTrack(stationId);

	const [editingId, setEditingId] = useState<string | null>(null);
	const [draft, setDraft] = useState<TrackDraft>(EMPTY);

	const startNew = () => {
		setDraft(EMPTY);
		setEditingId("new");
	};
	const startEdit = (tr: StationTrack) => {
		setDraft({
			name: tr.name,
			description: tr.description,
			runInLimit: tr.runInLimit,
			runOutLimit: tr.runOutLimit,
		});
		setEditingId(tr.id);
	};
	const cancel = () => {
		setEditingId(null);
		setDraft(EMPTY);
	};
	const save = () => {
		if (draft.name.trim() === "") return;
		const onError = (e: Error) => {
			alert(e.message);
		};
		if (editingId === "new") {
			createMut.mutate(draft, { onError, onSuccess: cancel });
		} else if (editingId !== null) {
			updateMut.mutate(
				{ id: editingId, ...draft },
				{ onError, onSuccess: cancel }
			);
		}
	};
	const set = <K extends keyof TrackDraft>(k: K, v: TrackDraft[K]) => {
		setDraft((p) => ({ ...p, [k]: v }));
	};

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div
				className="modal"
				style={{ maxWidth: 520 }}>
				<div className="modal-header">
					<span className="modal-title">
						🛤 {t.trackManager} — {stationName}
					</span>
					<button
						className="btn btn-ghost btn-sm"
						onClick={onClose}>
						✕
					</button>
				</div>
				<div className="modal-body">
					{editingId !== null && (
						<div
							style={{
								display: "flex",
								flexDirection: "column",
								gap: 8,
								padding: 12,
								marginBottom: 12,
								border: "1px solid var(--color-accent)",
								borderRadius: 6,
								background: "var(--color-bg)",
							}}>
							<div style={{ display: "flex", gap: 10 }}>
								<label style={{ flex: 2 }}>
									<div style={labelStyle}>{t.track}</div>
									<input
										autoFocus
										value={draft.name}
										placeholder="1 / 上2 ..."
										onChange={(e) => {
											set("name", e.target.value);
										}}
										style={inputStyle}
									/>
								</label>
								<label style={{ flex: 1 }}>
									<div style={labelStyle}>進入 (km/h)</div>
									<input
										type="number"
										min={0}
										value={draft.runInLimit ?? ""}
										onChange={(e) => {
											set(
												"runInLimit",
												e.target.value === ""
													? undefined
													: Number(e.target.value)
											);
										}}
										style={inputStyle}
									/>
								</label>
								<label style={{ flex: 1 }}>
									<div style={labelStyle}>進出 (km/h)</div>
									<input
										type="number"
										min={0}
										value={draft.runOutLimit ?? ""}
										onChange={(e) => {
											set(
												"runOutLimit",
												e.target.value === ""
													? undefined
													: Number(e.target.value)
											);
										}}
										style={inputStyle}
									/>
								</label>
							</div>
							<label>
								<div style={labelStyle}>{t.description}</div>
								<input
									value={draft.description}
									onChange={(e) => {
										set("description", e.target.value);
									}}
									style={inputStyle}
								/>
							</label>
							<div
								style={{
									display: "flex",
									gap: 8,
									justifyContent: "flex-end",
								}}>
								<button
									className="btn btn-ghost btn-sm"
									onClick={cancel}>
									{t.cancel}
								</button>
								<button
									className="btn btn-primary btn-sm"
									onClick={save}
									disabled={draft.name.trim() === ""}>
									{t.save}
								</button>
							</div>
						</div>
					)}

					{isLoading ? (
						<p style={{ color: "var(--color-text-muted)" }}>…</p>
					) : (tracks ?? []).length === 0 && editingId === null ? (
						<p
							style={{
								color: "var(--color-text-muted)",
								padding: "16px 0",
							}}>
							{t.noTracks}
						</p>
					) : (
						<div
							style={{
								display: "flex",
								flexDirection: "column",
								gap: 4,
							}}>
							{(tracks ?? []).map((tr) => (
								<div
									key={tr.id}
									style={{
										display: "flex",
										alignItems: "center",
										gap: 10,
										padding: "6px 10px",
										border: "1px solid var(--color-border)",
										borderRadius: 5,
									}}>
									<div style={{ flex: 1, minWidth: 0 }}>
										<span style={{ fontWeight: 500 }}>{tr.name}</span>
										{tr.description ? (
											<span
												style={{
													marginLeft: 8,
													fontSize: 12,
													color: "var(--color-text-muted)",
												}}>
												{tr.description}
											</span>
										) : null}
									</div>
									{(tr.runInLimit != null || tr.runOutLimit != null) && (
										<span
											style={{
												fontFamily: "var(--font-mono)",
												fontSize: 11,
												color: "var(--color-text-muted)",
											}}>
											{tr.runInLimit ?? "—"}/{tr.runOutLimit ?? "—"}
										</span>
									)}
									<button
										className="btn btn-ghost btn-sm"
										onClick={() => {
											startEdit(tr);
										}}>
										{t.edit}
									</button>
									<button
										className="btn btn-ghost btn-sm"
										style={{ color: "var(--color-danger)" }}
										onClick={() => {
											deleteMut.mutate(tr.id, {
												onError: (e: Error) => {
													alert(e.message);
												},
											});
										}}>
										🗑
									</button>
								</div>
							))}
						</div>
					)}

					{editingId === null && (
						<button
							className="btn btn-secondary btn-sm"
							style={{ marginTop: 12 }}
							onClick={startNew}>
							＋ {t.newTrack}
						</button>
					)}
				</div>
			</div>
		</div>
	);
};

const labelStyle: React.CSSProperties = {
	fontSize: 12,
	color: "var(--color-text-muted)",
	marginBottom: 2,
};
const inputStyle: React.CSSProperties = {
	width: "100%",
	padding: "6px 8px",
	border: "1px solid var(--color-border)",
	borderRadius: 4,
	background: "var(--color-content)",
	color: "var(--color-text)",
	fontSize: 14,
};
