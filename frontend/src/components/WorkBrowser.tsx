// WorkBrowser — train master/detail layout: train list + timetable grid.
// Ported from WorkBrowser.jsx.
import { useEffect, useState } from "react";

import { ApplyPatternDialog } from "./ApplyPatternDialog";
import { TimetableGrid } from "./TimetableGrid";
import { TrainHeaderBar } from "./TrainHeaderBar";
import { TrainInfoDialog } from "./TrainInfoDialog";
import { TrainListPanel } from "./TrainListPanel";

import type { AppliedRow } from "./ApplyPatternDialog";
import type { Strings } from "../i18n/strings";
import type {
	Color as EntityColor,
	Train as EntityTrain,
} from "../types/entities";
import type {
	Direction,
	Line,
	Station,
	StationOnLine,
	StopPattern,
	TimetableRow,
	Train,
	Work,
} from "../types/model";

type EntityTrainDraft = Omit<EntityTrain, "id" | "workId" | "createdAt">;
type EntityTrainUpdate = Pick<EntityTrain, "id"> &
	Omit<EntityTrain, "id" | "workId" | "createdAt">;

function modelTrainToEntityDraft(t: Train): EntityTrainDraft {
	return {
		description: "",
		trainNumber: t.trainNumber,
		direction: t.direction,
		dayCount: t.dayCount,
		maxSpeed: t.maxSpeed !== "" ? t.maxSpeed : undefined,
		speedType: t.speedType !== "" ? t.speedType : undefined,
		nominalTractiveCapacity:
			t.nominalTractiveCapacity !== "" ? t.nominalTractiveCapacity : undefined,
		carCount: t.carCount,
		destination: t.destination !== "" ? t.destination : undefined,
		beginRemarks: t.beginRemarks !== "" ? t.beginRemarks : undefined,
		afterRemarks: t.afterRemarks !== "" ? t.afterRemarks : undefined,
		remarks: t.remarks !== "" ? t.remarks : undefined,
		beforeDeparture: t.beforeDeparture !== "" ? t.beforeDeparture : undefined,
		afterArrive: t.afterArrive !== "" ? t.afterArrive : undefined,
		trainInfo: t.trainInfo !== "" ? t.trainInfo : undefined,
		isRideOnMoving: t.isRideOnMoving,
	};
}

type WorkBrowserProps = {
	readonly work: Work;
	readonly isLoadingTrains: boolean;
	readonly onCreateTrain: (draft: EntityTrainDraft) => void;
	readonly onUpdateTrain: (vars: EntityTrainUpdate) => void;
	readonly onDeleteTrain: (id: string) => void;
	readonly onSelectTrain: (id: string | null) => void;
	readonly onApplyPattern: (args: {
		rows: AppliedRow[];
		direction: Direction;
		destination: string;
		existingTrain: Train | null;
	}) => void;
	readonly stopPatterns: StopPattern[];
	readonly stations: Station[];
	readonly colors: EntityColor[];
	readonly stationsOnLine: StationOnLine[];
	readonly lines: Line[];
	readonly onCreateRow: (trainId: string, row: TimetableRow) => void;
	readonly onUpdateRow: (
		trainId: string,
		rowId: string,
		row: TimetableRow
	) => void;
	readonly onDeleteRow: (trainId: string, rowId: string) => void;
	readonly canWrite?: boolean;
	readonly t: Strings;
};

export const WorkBrowser = ({
	work,
	isLoadingTrains,
	onCreateTrain,
	onUpdateTrain,
	onDeleteTrain,
	onSelectTrain,
	onApplyPattern,
	stopPatterns,
	stations,
	colors,
	stationsOnLine,
	lines,
	onCreateRow,
	onUpdateRow,
	onDeleteRow,
	canWrite = true,
	t,
}: WorkBrowserProps) => {
	const [preferredTrainId, setPreferredTrainId] = useState<string | null>(null);
	const [showInfo, setShowInfo] = useState(false);
	const [showApplyPattern, setShowApplyPattern] = useState(false);
	const [applyTargetTrain, setApplyTargetTrain] = useState<Train | null>(null);
	// Derived effective selection: user's preferred train if still valid, else auto-select first.
	// This avoids calling setState inside a useEffect (set-state-in-effect).
	const firstTrainId = work.trains[0]?.id ?? null;
	const selectedTrainId =
		preferredTrainId !== null &&
		work.trains.some((tr) => tr.id === preferredTrainId)
			? preferredTrainId
			: firstTrainId;
	const selectedTrain = work.trains.find((tr) => tr.id === selectedTrainId);

	const selectTrain = (id: string | null) => {
		setPreferredTrainId(id);
		// Parent is notified via the sync effect below after state update
	};

	// Keep App.tsx's currentTrain in sync when the effective selection changes
	// (async train load, deletion, etc.). Calling a callback prop in an effect
	// is a normal side-effect, not a set-state-in-effect violation.
	useEffect(() => {
		onSelectTrain(selectedTrainId);
	}, [selectedTrainId, onSelectTrain]);

	const updateTrain = (updated: Train) => {
		onUpdateTrain({ id: updated.id, ...modelTrainToEntityDraft(updated) });
	};
	const addTrain = () => {
		const draft: EntityTrainDraft = {
			description: "",
			trainNumber: "0000M",
			direction: 1,
			destination: undefined,
			maxSpeed: "100",
			speedType: "近郊型",
			nominalTractiveCapacity: undefined,
			carCount: 10,
			dayCount: 0,
			isRideOnMoving: false,
			beginRemarks: undefined,
			afterRemarks: undefined,
			remarks: undefined,
			beforeDeparture: undefined,
			afterArrive: undefined,
			trainInfo: undefined,
		};
		onCreateTrain(draft);
	};

	return (
		<div
			style={{
				display: "grid",
				gridTemplateColumns: "240px 1fr",
				height: "100%",
				overflow: "hidden",
			}}>
			<TrainListPanel
				trains={work.trains}
				isLoadingTrains={isLoadingTrains}
				selectedId={selectedTrainId}
				onSelect={selectTrain}
				onAdd={addTrain}
				onAddViaPattern={() => {
					setShowApplyPattern(true);
				}}
				canWrite={canWrite}
				t={t}
			/>
			<div
				style={{
					display: "flex",
					flexDirection: "column",
					overflow: "hidden",
					background: "var(--color-content)",
				}}>
				{selectedTrain != null ? (
					<>
						<TrainHeaderBar
							train={selectedTrain}
							onOpenDialog={() => {
								setShowInfo(true);
							}}
							onApplyPattern={() => {
								setApplyTargetTrain(selectedTrain);
								setShowApplyPattern(true);
							}}
							canWrite={canWrite}
							t={t}
						/>
						<div style={{ flex: 1, overflow: "hidden" }}>
							<TimetableGrid
								train={selectedTrain}
								stations={stations}
								colors={colors}
								onCreateRow={(r) => {
									onCreateRow(selectedTrain.id, r);
								}}
								onUpdateRow={(rowId, r) => {
									onUpdateRow(selectedTrain.id, rowId, r);
								}}
								onDeleteRow={(rowId) => {
									onDeleteRow(selectedTrain.id, rowId);
								}}
								canWrite={canWrite}
								t={t}
							/>
						</div>
						{showInfo ? (
							<TrainInfoDialog
								train={selectedTrain}
								onSave={updateTrain}
								onDelete={onDeleteTrain}
								onClose={() => {
									setShowInfo(false);
								}}
								t={t}
							/>
						) : null}
					</>
				) : (
					<div
						className="empty-state"
						style={{ flex: 1, justifyContent: "center" }}>
						<svg
							width="64"
							height="64"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							strokeWidth="1">
							<rect
								x="3"
								y="6"
								width="18"
								height="12"
								rx="2"
							/>
							<path d="M3 10h18M9 14h.01M15 14h.01" />
						</svg>
						<p>{t.noTrains}</p>
						{canWrite ? (
							<div style={{ display: "flex", gap: 8, marginTop: 4 }}>
								<button
									type="button"
									className="btn btn-secondary btn-sm"
									onClick={() => {
										setShowApplyPattern(true);
									}}>{`
									🧩 パターンから作成
								`}</button>
								<button
									type="button"
									className="btn btn-primary btn-sm"
									onClick={addTrain}>
									{`
									＋ `}
									{t.newTrain}
								</button>
							</div>
						) : null}
					</div>
				)}
			</div>

			{showApplyPattern ? (
				<ApplyPatternDialog
					stopPatterns={stopPatterns ?? []}
					stations={stations ?? []}
					stationsOnLine={stationsOnLine ?? []}
					lines={lines ?? []}
					t={t}
					existingTrain={applyTargetTrain}
					onApply={({ rows, direction, destination }) => {
						onApplyPattern({
							rows,
							direction,
							destination,
							existingTrain: applyTargetTrain,
						});
					}}
					onClose={() => {
						setShowApplyPattern(false);
						setApplyTargetTrain(null);
					}}
				/>
			) : null}
		</div>
	);
};
