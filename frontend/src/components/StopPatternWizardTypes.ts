// StopPatternWizardTypes.ts — shared types for StopPatternWizard and its sub-steps
import type { Direction, StopPatternRow } from "../types/model";

/* Wizard-local working shape: direction may be null until chosen. */
export type WizardData = {
	id?: string;
	lineId: string;
	direction: Direction | null;
	name: string;
	fromStationId: string;
	toStationId: string;
	stopRows: StopPatternRow[];
};

export type SetData = React.Dispatch<React.SetStateAction<WizardData>>;
