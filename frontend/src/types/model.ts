// Data model for the TRViS Timetable Editor.
// Mirrors the design prototype's in-memory shapes (Project > WorkGroup > Work >
// Train > TimetableRow, plus Line / Station / StationOnLine / StopPattern).

export type Direction = 1 | -1;

/** Normal station row uses "station"; a full-width info row uses 2. */
export type RecordType = "station" | "info" | 2 | "2";

/** undefined = auto (omit HH when same as last shown), true = always, false = never */
export type ShowHH = boolean | undefined;

export interface TimetableRow {
	id: string;
	stationId?: string;
	stationName: string;
	/** true when stationId points at a soft-deleted station — the editor shows
	 * the resolved name with a "(削除済み)" tombstone instead of a live link. */
	stationDeleted?: boolean;
	fullName?: string;
	/** internal "HH:MM:SS" (or "" / free text) */
	arrive: string;
	/** internal "HH:MM:SS" (or "" / free text) */
	departure: string;
	/** literal string shown instead of the arrive time (e.g. "↓") */
	arriveDisplayText?: string;
	/** literal string shown instead of the departure time */
	departureDisplayText?: string;
	arriveHidden?: boolean;
	departureHidden?: boolean;
	trackName: string;
	trackHidden?: boolean;
	/** FK to a station_tracks entity (the row's track). */
	stationTrackId?: string;
	/** true when stationTrackId points at a soft-deleted track (tombstone). */
	trackDeleted?: boolean;
	/** FK to a colors entity (the row's marker color). */
	colorIdMarker?: string;
	/** backend-resolved name of colorIdMarker (present even when deleted). */
	colorName?: string;
	/** true when colorIdMarker points at a soft-deleted color (tombstone). */
	colorDeleted?: boolean;
	isPass: boolean;
	isOperationOnlyStop: boolean;
	isLastStop?: boolean;
	hasBracket?: boolean;
	recordType: RecordType;
	driveTime_MM: number;
	driveTime_SS: number;
	runInLimit: number | "";
	runOutLimit: number | "";
	remarks: string;
	workType: string;
	showHH?: ShowHH;
	location_m?: number;
	longitude_deg?: number;
	latitude_deg?: number;
	onStationDetectRadius_m?: number;
}

export interface Train {
	id: string;
	trainNumber: string;
	direction: Direction;
	destination: string;
	maxSpeed: string;
	speedType: string;
	nominalTractiveCapacity: string;
	carCount: number;
	workType: string;
	dayCount: number;
	isRideOnMoving: boolean;
	beginRemarks: string;
	afterRemarks: string;
	remarks: string;
	beforeDeparture: string;
	afterArrive: string;
	trainInfo: string;
	nextTrainId: string;
	timetableRows: TimetableRow[];
}

export interface Work {
	id: string;
	name: string;
	affectDate: string;
	remarks: string;
	trains: Train[];
}

export interface WorkGroup {
	id: string;
	name: string;
	description: string;
	works: Work[];
}

export interface Project {
	id: string;
	name: string;
	description: string;
	workGroups: WorkGroup[];
}

export interface Line {
	id: string;
	name: string;
	description: string;
}

/** Stations are project-global; lat/lon/detect-radius live here. */
export interface Station {
	id: string;
	stationName: string;
	fullName: string;
	longitude_deg?: number;
	latitude_deg?: number;
	onStationDetectRadius_m?: number;
	alwaysShowHH?: boolean;
}

/** A station's membership on a particular line (km-post + optional overrides). */
export interface StationOnLine {
	id: string;
	lineId: string;
	stationId: string;
	/** backend-resolved name of the referenced station (present even when the
	 * station is soft-deleted; the line editor shows it as a tombstone). */
	stationName?: string;
	/** true when stationId points at a soft-deleted station. */
	stationDeleted?: boolean;
	location_m: number;
	longitude_deg?: number;
	latitude_deg?: number;
	trackHiddenByDefault?: boolean;
}

export interface StopPatternRow {
	stationId: string;
	trackName?: string;
	trackHidden?: boolean;
	driveTime_MM?: number;
	driveTime_SS?: number;
	isOperationOnlyStop?: boolean;
	isPass?: boolean;
	/** travel time from the previous station (minutes) */
	runMin?: number;
	/** travel time from the previous station (seconds) */
	runSec?: number;
	/** dwell time at this station (minutes) */
	dwellMin?: number;
	/** dwell time at this station (seconds) */
	dwellSec?: number;
	/** ADDED for prototype port: dwell time at this station (minutes) — used by StopPatternWizard/ApplyPatternDialog */
	dwellTime_MM?: number;
	/** ADDED for prototype port: dwell time at this station (seconds) — used by StopPatternWizard/ApplyPatternDialog */
	dwellTime_SS?: number;
	showArrive?: boolean;
	showDeparture?: boolean;
	arrive?: string;
	departure?: string;
	runInLimit?: number | "";
	runOutLimit?: number | "";
	remarks?: string;
	alwaysShowHH?: boolean;
}

export interface StopPattern {
	id: string;
	name: string;
	lineId: string;
	fromStationId: string;
	toStationId: string;
	direction: Direction;
	rows: StopPatternRow[];
	/** ADDED for prototype port: the wizard/apply-dialog read & write `stopRows`
	 * (the `rows` field is kept untouched for other consumers). */
	stopRows?: StopPatternRow[];
}

export interface AppData {
	projects: Project[];
	lines: Line[];
	stations: Station[];
	stationsOnLine: StationOnLine[];
	stopPatterns: StopPattern[];
}
