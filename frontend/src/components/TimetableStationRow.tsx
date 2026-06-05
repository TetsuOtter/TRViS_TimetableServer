// StationRow — one data row in the TimetableGrid table.
import { useState } from "react";
import type { CSSProperties } from "react";

import { BBCodeEditButton } from "./BBCodeEditButton";
import { ColorCell } from "./TimetableColorCell";
import { TimeCell } from "./TimetableTimeCell";
import { TrackCell } from "./TimetableTrackCell";

import type { Strings } from "../i18n/strings";
import type { RowFormat } from "../lib/timeUtils";
import type { Color as EntityColor } from "../types/entities";
import type { TimetableRow } from "../types/model";

export type StationRowProps = {
	readonly row: TimetableRow;
	readonly idx: number;
	readonly isLast: boolean;
	readonly fmt: Partial<RowFormat>;
	readonly colors: EntityColor[];
	readonly onUpdateRow: <K extends keyof TimetableRow>(
		idx: number,
		key: K,
		val: TimetableRow[K]
	) => void;
	readonly onUpdateRowFields: (
		idx: number,
		partial: Partial<TimetableRow>
	) => void;
	readonly onDeleteRow: (idx: number) => void;
	readonly onOpenDetail: () => void;
	readonly canWrite: boolean;
	readonly t: Strings;
};

export const StationRow = ({
	row,
	idx,
	isLast,
	fmt,
	colors,
	onUpdateRow,
	onUpdateRowFields,
	onDeleteRow,
	onOpenDetail,
	canWrite,
	t,
}: StationRowProps) => {
	const [remarksDraft, setRemarksDraft] = useState(row.remarks);
	// Track previous prop value to reseed draft when row data changes (e.g. after
	// a mutation refetch). Using useState avoids ref access during render.
	const [prevRemarks, setPrevRemarks] = useState(row.remarks);
	if (prevRemarks !== row.remarks) {
		setPrevRemarks(row.remarks);
		setRemarksDraft(row.remarks);
	}

	const rowStyle: CSSProperties = row.isPass
		? { background: "var(--color-pass-bg)" }
		: row.isOperationOnlyStop
			? { background: "var(--color-oponly-bg)" }
			: isLast
				? { background: "var(--color-laststop-bg)" }
				: {};

	return (
		<tr
			key={row.id}
			style={rowStyle}>
			<td className="row-num">
				{idx + 1}
				{isLast ? (
					<div
						style={{
							fontSize: 9,
							color: "var(--color-success)",
							fontWeight: 600,
							marginTop: -2,
						}}>{`
						終
					`}</div>
				) : null}
			</td>
			<td>
				<div
					className="station-cell"
					onDoubleClick={canWrite ? onOpenDetail : undefined}
					title={
						(row.stationDeleted ?? false)
							? "この駅は削除されています"
							: canWrite
								? "ダブルクリックで詳細編集"
								: undefined
					}>
					<span
						className={`station-name ${!(row.stationName !== "") ? "empty" : ""} ${(row.stationDeleted ?? false) ? "tombstone" : ""}`}>
						{Boolean(row.stationName) || "(駅名未設定)"}
					</span>
					{(row.stationDeleted ?? false) ? (
						<span
							className="tombstone-tag"
							title="この駅は削除されています">{`
							削除済み
						`}</span>
					) : null}
				</div>
			</td>
			<td style={row.isPass ? { color: "oklch(0.55 0.12 15)" } : {}}>
				<TimeCell
					value={row.arrive}
					displayText={
						row.arriveDisplayText !== "" ? row.arriveDisplayText : undefined
					}
					formattedValue={
						row.arriveDisplayText != null ? undefined : fmt.arriveFormatted
					}
					onChange={(v) => {
						onUpdateRowFields(idx, { arrive: v, arriveDisplayText: "" });
					}}
					onChangeText={(v) => {
						onUpdateRowFields(idx, { arrive: "", arriveDisplayText: v });
					}}
					muted={!!(row.arriveHidden ?? false)}
					readOnly={!canWrite}
				/>
			</td>
			<td style={row.isPass ? { color: "oklch(0.55 0.12 15)" } : {}}>
				{isLast &&
				!(row.departure !== "") &&
				row.departureDisplayText == null ? (
					<span
						className="time-display"
						style={{
							textAlign: "center",
							justifyContent: "center",
							opacity: 0.35,
							fontFamily: "var(--font-mono)",
							fontSize: 12,
							cursor: "default",
						}}>{`
						==
					`}</span>
				) : (
					<TimeCell
						value={row.departure}
						displayText={
							row.departureDisplayText !== ""
								? row.departureDisplayText
								: undefined
						}
						formattedValue={
							row.departureDisplayText != null
								? undefined
								: fmt.departureFormatted
						}
						onChange={(v) => {
							onUpdateRowFields(idx, {
								departure: v,
								departureDisplayText: "",
							});
						}}
						onChangeText={(v) => {
							onUpdateRowFields(idx, {
								departure: "",
								departureDisplayText: v,
							});
						}}
						muted={!!(row.departureHidden ?? false)}
						readOnly={!canWrite}
					/>
				)}
			</td>
			<td className="toggle-cell">
				<input
					type="checkbox"
					className="toggle-check"
					checked={!!row.isPass}
					disabled={!canWrite}
					onChange={(e) => {
						onUpdateRow(idx, "isPass", e.target.checked);
					}}
				/>
			</td>
			<td>
				<TrackCell
					row={row}
					onChange={(id) => {
						onUpdateRow(idx, "stationTrackId", id);
					}}
					disabled={!canWrite}
					t={t}
				/>
			</td>
			<td>
				<ColorCell
					row={row}
					colors={colors}
					onChange={(id) => {
						onUpdateRow(idx, "colorIdMarker", id);
					}}
					disabled={!canWrite}
					t={t}
				/>
			</td>
			<td>
				<div style={{ display: "flex", alignItems: "center", gap: 3 }}>
					<input
						className="remarks-input"
						value={remarksDraft}
						readOnly={!canWrite}
						onChange={(e) => {
							if (!canWrite) return;
							setRemarksDraft(e.target.value);
						}}
						onBlur={(e) => {
							if (!canWrite) return;
							const v = e.target.value;
							if (v !== row.remarks) {
								onUpdateRow(idx, "remarks", v);
							}
						}}
						placeholder="—"
					/>
					{canWrite ? (
						<BBCodeEditButton
							title="記事"
							value={remarksDraft}
							onChange={(v) => {
								setRemarksDraft(v);
								onUpdateRow(idx, "remarks", v);
							}}
							multiline={false}
						/>
					) : null}
				</div>
			</td>
			<td>
				<div className="actions-cell">
					{canWrite ? (
						<button
							type="button"
							className="row-action-btn"
							onClick={onOpenDetail}
							title={t.detail}>{`
							⚙
						`}</button>
					) : null}
					{canWrite ? (
						<button
							type="button"
							className="row-action-btn del"
							onClick={() => {
								onDeleteRow(idx);
							}}
							title={t.delete}>{`
							✕
						`}</button>
					) : null}
				</div>
			</td>
		</tr>
	);
};
