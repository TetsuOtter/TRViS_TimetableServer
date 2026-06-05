// StopPatternWizard.tsx — Stop pattern wizard with edit support
// Ported 1:1 from the design prototype StopPatternWizard.jsx.
import { useState } from "react";

import { StepIndicator } from "./StepIndicator";
import { StopPatternStep1 } from "./StopPatternStep1";
import { StopPatternStep2 } from "./StopPatternStep2";
import { StopPatternStep3 } from "./StopPatternStep3";

import type { WizardData } from "./StopPatternWizardTypes";
import type { Strings } from "../i18n/strings";
import type {
	Direction,
	Line,
	Station,
	StationOnLine,
	StopPattern,
} from "../types/model";

/* ── Main wizard ─────────────────────────────────────────────────────────── */
export type StopPatternWizardProps = {
	readonly lines: Line[];
	readonly stations: Station[];
	readonly stationsOnLine: StationOnLine[];
	readonly t: Strings;
	readonly editPattern: StopPattern | null;
	readonly onSave: (sp: StopPattern) => void;
	readonly onClose: () => void;
};

export const StopPatternWizard = ({
	lines,
	stations,
	stationsOnLine,
	t,
	onSave,
	onClose,
	editPattern,
}: StopPatternWizardProps) => {
	const isEdit = !(editPattern == null);
	const [step, setStep] = useState(0);
	const [data, setData] = useState<WizardData>(
		isEdit && editPattern != null
			? {
					id: editPattern.id,
					lineId: editPattern.lineId,
					direction: editPattern.direction,
					name: editPattern.name,
					fromStationId: editPattern.fromStationId,
					toStationId: editPattern.toStationId,
					stopRows: editPattern.stopRows ?? editPattern.rows ?? [],
				}
			: {
					lineId: lines[0]?.id ?? "",
					direction: 1,
					name: "",
					fromStationId: "",
					toStationId: "",
					stopRows: [],
				}
	);

	const stepLabels = [t.step1, t.step2, t.step3];
	const canNext =
		step === 0
			? !!(
					data.lineId !== "" &&
					data.direction !== null &&
					data.fromStationId !== "" &&
					data.toStationId !== "" &&
					data.fromStationId !== data.toStationId
				)
			: true;

	const handleFinish = () => {
		const id =
			isEdit && editPattern != null ? editPattern.id : "sp" + Date.now();
		// canNext (step 0) guarantees a non-null direction by the time the
		// finish button is reachable; default to 1 defensively.
		const direction: Direction = data.direction ?? 1;
		const sp: StopPattern = {
			id,
			name: data.name,
			lineId: data.lineId,
			fromStationId: data.fromStationId,
			toStationId: data.toStationId,
			direction,
			rows: data.stopRows,
			stopRows: data.stopRows,
		};
		onSave(sp);
		onClose();
	};

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div
				className="modal"
				style={{ maxWidth: 680, width: "100%" }}>
				<div className="modal-header">
					<span className="modal-title">
						{`
						🧩`}{" "}
						{isEdit
							? `停車パターンを編集: ${editPattern?.name ?? "(無名)"}`
							: t.stopPattern}
					</span>
					<button
						type="button"
						className="btn btn-ghost btn-sm"
						onClick={onClose}>{`
						✕
					`}</button>
				</div>
				<div className="modal-body">
					<StepIndicator
						step={step}
						labels={stepLabels}
					/>
					{step === 0 && (
						<StopPatternStep1
							data={data}
							setData={setData}
							lines={lines}
							stations={stations}
							stationsOnLine={stationsOnLine}
							t={t}
						/>
					)}
					{step === 1 && (
						<StopPatternStep2
							data={data}
							setData={setData}
							stations={stations}
							stationsOnLine={stationsOnLine}
							t={t}
						/>
					)}
					{step === 2 && (
						<StopPatternStep3
							data={data}
							stations={stations}
							lines={lines}
							t={t}
						/>
					)}
				</div>
				<div className="modal-footer">
					<button
						type="button"
						className="btn btn-secondary"
						onClick={onClose}>
						{t.cancel}
					</button>
					{step > 0 && (
						<button
							type="button"
							className="btn btn-secondary"
							onClick={() => {
								setStep((s) => s - 1);
							}}>
							{t.back}
						</button>
					)}
					{step < 2 ? (
						<button
							type="button"
							className="btn btn-primary"
							onClick={() => {
								setStep((s) => s + 1);
							}}
							disabled={!canNext}>
							{t.next}
						</button>
					) : (
						<button
							type="button"
							className="btn btn-primary"
							onClick={handleFinish}>
							{isEdit ? "更新" : t.finish}
						</button>
					)}
				</div>
			</div>
		</div>
	);
};
