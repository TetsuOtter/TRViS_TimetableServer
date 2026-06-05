// TrackCell — per-row track picker for TimetableGrid.
// Fetched ON DEMAND (only when picker is opened) so opening a timetable
// doesn't fan out a tracks query per station.
import { useState } from "react";

import { useStationTracks } from "../api/hooks/useStationTracks";

import type { Strings } from "../i18n/strings";
import type { TimetableRow } from "../types/model";

export type TrackCellProps = {
	readonly row: TimetableRow;
	readonly onChange: (id: string | undefined) => void;
	readonly disabled?: boolean;
	readonly t: Strings;
};

export const TrackCell = ({ row, onChange, disabled, t }: TrackCellProps) => {
	const [open, setOpen] = useState(false);
	const stationId = row.stationId ?? "";
	const { data: tracks } = useStationTracks(
		stationId,
		open && stationId !== ""
	);
	const deleted = !!(row.trackDeleted ?? false);

	if (!open) {
		return (
			<button
				type="button"
				className="track-pick"
				disabled={stationId === "" || disabled}
				onClick={() => {
					setOpen(true);
				}}
				title={
					deleted
						? `${row.trackName}（${t.deleted}）`
						: row.trackName !== ""
							? row.trackName
							: t.none
				}
				style={
					deleted
						? {
								color: "var(--color-danger)",
								textDecoration: "line-through",
							}
						: undefined
				}>
				{row.trackName !== "" ? (
					row.trackName
				) : (
					<span style={{ opacity: 0.4 }}>{`—`}</span>
				)}
				{deleted ? (
					<span style={{ marginLeft: 3, textDecoration: "none" }}>{`⚠`}</span>
				) : null}
			</button>
		);
	}
	return (
		<select
			className="color-select"
			autoFocus
			value={row.stationTrackId ?? ""}
			style={deleted ? { color: "var(--color-danger)" } : undefined}
			onChange={(e) => {
				onChange(e.target.value === "" ? undefined : e.target.value);
				setOpen(false);
			}}
			onBlur={() => {
				setOpen(false);
			}}>
			<option value="">
				{`— `}
				{t.none}
				{` —`}
			</option>
			{deleted && row.stationTrackId !== undefined ? (
				<option value={row.stationTrackId}>
					{`
					⚠ `}
					{row.trackName}
					{`（`}
					{t.deleted}
					{`）
				`}
				</option>
			) : null}
			{(tracks ?? []).map((tr) => (
				<option
					key={tr.id}
					value={tr.id}>
					{tr.name}
				</option>
			))}
		</select>
	);
};
