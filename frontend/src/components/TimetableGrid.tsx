// TimetableGrid — spreadsheet-style timetable row editor. Ported from TimetableGrid.jsx.
import { useCallback, useMemo, useState } from "react";

import { TRViSTime } from "../lib/timeUtils";

import { RowDetailModal } from "./TimetableRowDetailModal";
import { StationPickerModal } from "./TimetableStationPickerModal";
import { StationRow } from "./TimetableStationRow";

import type { Strings } from "../i18n/strings";
import type { RowFormat } from "../lib/timeUtils";
import type { Color as EntityColor } from "../types/entities";
import type { Station, TimetableRow, Train } from "../types/model";

type TimetableGridProps = {
	readonly train: Train;
	readonly stations: Station[];
	readonly colors: EntityColor[];
	readonly onCreateRow: (row: TimetableRow) => void;
	readonly onUpdateRow: (rowId: string, row: TimetableRow) => void;
	readonly onDeleteRow: (rowId: string) => void;
	readonly canWrite?: boolean;
	readonly t: Strings;
};

export const TimetableGrid = ({
	train,
	stations,
	colors,
	onCreateRow,
	onUpdateRow,
	onDeleteRow,
	canWrite = true,
	t,
}: TimetableGridProps) => {
	const rows = train.timetableRows ?? [];
	const [detailRow, setDetailRow] = useState<TimetableRow | null>(null);
	const [showPicker, setShowPicker] = useState(false);

	const rowFormats = useMemo(() => TRViSTime.computeRowFormats(rows), [rows]);

	const lastStationIdx = rows.length - 1;

	const effectiveLastStop = (row: TimetableRow, idx: number): boolean => {
		if (idx === lastStationIdx) return row.isLastStop !== false;
		return !!(row.isLastStop ?? false);
	};

	const updateRow = useCallback(
		<K extends keyof TimetableRow>(
			idx: number,
			key: K,
			val: TimetableRow[K]
		) => {
			const row = rows[idx];
			if (row == null) return;
			const updated = { ...row, [key]: val };
			onUpdateRow(updated.id, updated);
		},

		[rows, onUpdateRow]
	);

	// Apply several fields in ONE update (one spread, one PUT). Needed where two
	// related fields change together (time vs its display-text): two single-key
	// updateRow calls would each read the same stale rows[idx] and the second
	// would clobber the first.
	const updateRowFields = useCallback(
		(idx: number, partial: Partial<TimetableRow>) => {
			const row = rows[idx];
			if (row == null) return;
			onUpdateRow(row.id, { ...row, ...partial });
		},

		[rows, onUpdateRow]
	);

	const deleteRow = (idx: number) => {
		const row = rows[idx];
		if (row == null) return;
		onDeleteRow(row.id);
	};

	const saveDetail = (updated: TimetableRow) => {
		onUpdateRow(updated.id, updated);
	};

	return (
		<div
			className="timetable-grid-wrap"
			style={{ display: "flex", flexDirection: "column", height: "100%" }}>
			<style>{`
        .timetable-grid-wrap { font-size: 13px; }
        .tgrid { border-collapse: collapse; width: 100%; table-layout: fixed; }
        .tgrid th {
          position: sticky; top: 0; z-index: 10;
          background: var(--color-content); border-bottom: 2px solid var(--color-border);
          border-right: 1px solid var(--color-border);
          padding: 0 6px; height: 32px; font-size: 11px; font-weight: 600;
          text-align: center; color: var(--color-text-muted); user-select: none;
          letter-spacing: 0.04em; white-space: nowrap;
        }
        .tgrid th.lh { text-align: left; padding-left: 10px; }
        .tgrid td {
          border-bottom: 1px solid var(--color-border);
          border-right: 1px solid var(--color-border);
          height: var(--row-h); padding: 0 6px; vertical-align: middle; position: relative;
        }
        .tgrid td:last-child { border-right: none; }
        .tgrid tr:hover td { background: color-mix(in srgb, var(--color-accent-bg) 30%, transparent); }
        .tgrid tr.drag-over td { border-top: 2px solid var(--color-accent); }
        .row-num { font-size: 10px; color: var(--color-text-muted); text-align: center; user-select: none; width: 28px; }
        .station-cell { display: flex; align-items: center; gap: 5px; padding-left: 2px; }
        .station-name { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
        .station-name.empty { color: var(--color-text-muted); font-weight: 400; font-style: italic; }
        .station-name.tombstone { color: var(--color-danger); text-decoration: line-through; text-decoration-color: color-mix(in srgb, var(--color-danger) 55%, transparent); }
        .tombstone-tag { flex-shrink: 0; font-size: 9px; font-weight: 600; line-height: 1; padding: 2px 4px; border-radius: 3px; color: var(--color-danger); background: color-mix(in srgb, var(--color-danger) 14%, transparent); border: 1px solid color-mix(in srgb, var(--color-danger) 40%, transparent); white-space: nowrap; }
        .drag-handle { cursor: grab; opacity: 0.3; font-size: 13px; flex-shrink: 0; }
        .drag-handle:hover { opacity: 0.7; }
        .tgrid tr:active .drag-handle { cursor: grabbing; }
        .time-display { font-family: var(--font-mono); font-size: 12px; display: block; text-align: center; cursor: text; width: 100%; padding: 4px 2px; border-radius: 3px; }
        .time-display:hover { background: var(--color-accent-bg); }
        .toggle-cell { text-align: center; vertical-align: middle; }
        .toggle-check { width: 14px; height: 14px; cursor: pointer; accent-color: var(--color-accent); }
        .remarks-input { border: none; outline: none; background: transparent; width: 100%; font-size: 12px; color: var(--color-text); font-family: inherit; padding: 0; }
        .remarks-input:focus { outline: 1px solid var(--color-accent); border-radius: 3px; }
        .tgrid tbody tr:last-child td { border-bottom: none; }
        .row-action-btn { padding: 3px 6px; border-radius: 3px; font-size: 11px; background: var(--color-bg); border: 1px solid var(--color-border); color: var(--color-text-muted); cursor: pointer; line-height: 1; }
        .row-action-btn:hover { background: var(--color-accent-bg); color: var(--color-accent); border-color: var(--color-accent); }
        .row-action-btn.del:hover { background: #fee2e2; color: var(--color-danger); border-color: var(--color-danger); }
        .track-input { border: none; outline: none; background: transparent; text-align: center; width: 100%; font-size: 12px; font-family: var(--font-mono); color: inherit; }
        .track-input:focus { outline: 1px solid var(--color-accent); border-radius: 3px; }
        .color-select { flex: 1; min-width: 0; border: none; outline: none; background: transparent; font-size: 12px; color: inherit; cursor: pointer; padding: 2px 0; }
        .color-select:focus { outline: 1px solid var(--color-accent); border-radius: 3px; }
        .track-pick { width: 100%; border: none; background: transparent; text-align: left; font-size: 12px; color: inherit; cursor: pointer; padding: 3px 4px; border-radius: 3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .track-pick:hover:not(:disabled) { background: var(--color-accent-bg); }
        .track-pick:disabled { cursor: default; opacity: 0.5; }
        .tgrid-scroll { flex: 1; overflow: auto; }
        .add-row-bar { padding: 8px 12px; border-top: 1px solid var(--color-border); background: var(--color-content); display: flex; align-items: center; gap: 12px; }
        .actions-cell { display: flex; gap: 3px; justify-content: flex-end; }
      `}</style>

			<div className="tgrid-scroll">
				<table className="tgrid">
					<colgroup>
						<col style={{ width: 28 }} />
						<col style={{ width: 128 }} />
						<col style={{ width: 80 }} />
						<col style={{ width: 80 }} />
						<col style={{ width: 42 }} />
						<col style={{ width: 88 }} />
						<col style={{ width: 104 }} />
						<col style={{ width: "auto" }} />
						<col style={{ width: 64 }} />
					</colgroup>
					<thead>
						<tr>
							<th>{`#`}</th>
							<th className="lh">{t.stationName}</th>
							<th>{t.arrive}</th>
							<th>{t.depart}</th>
							<th title={t.pass}>{`通`}</th>
							<th className="lh">{t.track}</th>
							<th className="lh">{t.color}</th>
							<th className="lh">{t.remarks}</th>
							<th />
						</tr>
					</thead>
					<tbody>
						{rows.map((row, idx) => {
							const isLast = effectiveLastStop(row, idx);
							const fmt: Partial<RowFormat> = rowFormats[idx] ?? {};

							return (
								<StationRow
									key={row.id}
									row={row}
									idx={idx}
									isLast={isLast}
									fmt={fmt}
									colors={colors}
									onUpdateRow={updateRow}
									onUpdateRowFields={updateRowFields}
									onDeleteRow={deleteRow}
									onOpenDetail={() => {
										setDetailRow(row);
									}}
									canWrite={canWrite}
									t={t}
								/>
							);
						})}
					</tbody>
				</table>
			</div>

			<div className="add-row-bar">
				{canWrite ? (
					<button
						type="button"
						className="btn btn-secondary btn-sm"
						onClick={() => {
							setShowPicker(true);
						}}>
						{`
						＋ `}
						{t.addRow}
					</button>
				) : null}
				{rows.length > 0 && (
					<span style={{ fontSize: 11, color: "var(--color-text-muted)" }}>
						{rows.length}
						{` 駅 · 通過 `}
						{rows.filter((r) => r.isPass).length}
						{` · 運停`} {rows.filter((r) => r.isOperationOnlyStop).length}
					</span>
				)}
				<span style={{ flex: 1 }} />
				<span style={{ fontSize: 11, color: "var(--color-text-muted)" }}>{`
					※ 最後の行は自動的に終着駅扱いになります
				`}</span>
			</div>

			{detailRow != null ? (
				<RowDetailModal
					row={detailRow}
					isFirstRow={rows[0]?.id === detailRow.id}
					isLastIdx={rows[lastStationIdx]?.id === detailRow.id}
					allRows={rows}
					t={t}
					onSave={saveDetail}
					onClose={() => {
						setDetailRow(null);
					}}
				/>
			) : null}

			{showPicker ? (
				<StationPickerModal
					stations={stations}
					usedStationIds={
						new Set(rows.map((r) => r.stationId ?? "").filter(Boolean))
					}
					onPick={(picked) => {
						const newRow: TimetableRow = {
							id: "",
							stationId: picked.id,
							stationName: picked.stationName,
							fullName: picked.fullName,
							arrive: "",
							departure: "",
							trackName: "",
							isPass: false,
							isOperationOnlyStop: false,
							hasBracket: false,
							isLastStop: undefined,
							recordType: "station",
							driveTime_MM: 0,
							driveTime_SS: 0,
							runInLimit: "",
							runOutLimit: "",
							remarks: "",
							workType: "",
						};
						onCreateRow(newRow);
						setShowPicker(false);
					}}
					onClose={() => {
						setShowPicker(false);
					}}
				/>
			) : null}
		</div>
	);
};
