// snake_case adapters for the code-first API (openapi-typescript schema).
//
// The old openapi-generator client camelCased every field and parsed
// date-time into `Date`; openapi-typescript does NEITHER — wire fields stay
// snake_case and date/date-time fields are typed `string`. So the new
// adapters read `api.projects_id` / `api.created_at` and wrap dates with
// `new Date(...)` themselves.
//
// LOCKED fan-out pattern (every migrated entity copies this shape):
//   - `from*`: snake_case in → camelCase entity out; `?? ""` for the id;
//     `new Date(api.<x>) ` guarded by `!== undefined` for every date field.
//   - `to*`:   only the user-writable fields (readOnly props omitted).
//
// Lives beside the old `adapters.ts` during P4.5→P4.6; P4.6 deletes the old
// one and the imports collapse.

import type { components } from "./schema";
import type {
	Color,
	InviteKey,
	Line,
	Project,
	ProjectStation,
	StationOnLine,
	StationTrack,
	StopPattern,
	StopPatternRow,
	TimetableRow,
	Train,
	Work,
	WorkGroup,
} from "../types/entities";

// MySQL tinyint(1) booleans arrive as 0/1 (the schema types them boolean).
// Coerce to a real boolean, preserving undefined for absent fields.
const toBool = (v: boolean | number | undefined): boolean | undefined =>
	v === undefined ? undefined : Boolean(v);

// `affect_date` is an OpenAPI `format: date` (a calendar date, no time/zone) —
// unlike `created_at` etc. which are `date-time`. Round-trip it via LOCAL date
// parts so the day the user picked is preserved regardless of timezone.
// `new Date("2024-05-29")` anchors at UTC midnight and `Date#toISOString()` is
// UTC, so the naive `new Date(s)` / `.toISOString().slice(0,10)` round-trip
// shifts the date by a day in negative-UTC timezones.
const fromApiDateOnly = (s: string | undefined): Date | undefined => {
	if (s === undefined) {
		return undefined;
	}
	const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s);
	if (m === null) {
		// Tolerate an unexpected datetime form rather than throwing.
		return new Date(s);
	}
	return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
};
const toApiDateOnly = (d: Date | undefined): string | undefined => {
	if (d === undefined) {
		return undefined;
	}
	const pad = (n: number): string => String(n).padStart(2, "0");
	return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

type ApiProject = components["schemas"]["Project"];

export const fromApiProject = (api: ApiProject): Project => ({
	id: api.projects_id ?? "",
	name: api.name,
	description: api.description,
	createdAt:
		api.created_at !== undefined ? new Date(api.created_at) : undefined,
	privilegeType: api.privilege_type,
});

export const toApiProject = (
	x: Omit<Project, "id" | "createdAt" | "privilegeType">
): ApiProject => ({
	name: x.name,
	description: x.description,
});

// WorkGroup

type ApiWorkGroup = components["schemas"]["WorkGroup"];

export const fromApiWorkGroup = (api: ApiWorkGroup): WorkGroup => ({
	id: api.work_groups_id ?? "",
	projectId: api.projects_id ?? "",
	name: api.name,
	description: api.description,
	createdAt:
		api.created_at !== undefined ? new Date(api.created_at) : undefined,
	privilegeType: api.privilege_type,
});

export const toApiWorkGroup = (
	x: Omit<WorkGroup, "id" | "projectId" | "createdAt" | "privilegeType">
): ApiWorkGroup => ({
	name: x.name,
	description: x.description,
});

// Line

type ApiLine = components["schemas"]["Line"];

export const fromApiLine = (api: ApiLine): Line => ({
	id: api.lines_id ?? "",
	projectId: api.projects_id ?? "",
	name: api.name,
	description: api.description,
	createdAt:
		api.created_at !== undefined ? new Date(api.created_at) : undefined,
});

export const toApiLine = (
	x: Omit<Line, "id" | "projectId" | "createdAt">
): ApiLine => ({
	name: x.name,
	description: x.description,
});

// Color

type ApiColor = components["schemas"]["Color"];

export const fromApiColor = (api: ApiColor): Color => ({
	id: api.colors_id ?? "",
	projectId: api.projects_id ?? "",
	name: api.name,
	description: api.description,
	red: api.color_8bit?.red ?? 0,
	green: api.color_8bit?.green ?? 0,
	blue: api.color_8bit?.blue ?? 0,
	createdAt:
		api.created_at !== undefined ? new Date(api.created_at) : undefined,
});

export const toApiColor = (
	x: Omit<Color, "id" | "projectId" | "createdAt">
): ApiColor => ({
	name: x.name,
	description: x.description,
	color_8bit: { red: x.red, green: x.green, blue: x.blue },
	// color_real mirrors the 8-bit value (0-1 scale) so the stored pair stays
	// consistent; the backend marks color_real optional.
	color_real: { red: x.red / 255, green: x.green / 255, blue: x.blue / 255 },
});

// StationTrack

type ApiStationTrack = components["schemas"]["StationTrack"];

export const fromApiStationTrack = (api: ApiStationTrack): StationTrack => ({
	id: api.station_tracks_id ?? "",
	stationId: api.stations_id ?? "",
	name: api.name,
	description: api.description,
	runInLimit: api.run_in_limit,
	runOutLimit: api.run_out_limit,
	createdAt:
		api.created_at !== undefined ? new Date(api.created_at) : undefined,
});

export const toApiStationTrack = (
	x: Omit<StationTrack, "id" | "stationId" | "createdAt">
): ApiStationTrack => ({
	name: x.name,
	description: x.description,
	run_in_limit: x.runInLimit,
	run_out_limit: x.runOutLimit,
});

// ProjectStation

type ApiProjectStation = components["schemas"]["Station"];

export const fromApiProjectStation = (
	api: ApiProjectStation
): ProjectStation => ({
	id: api.stations_id ?? "",
	projectId: api.projects_id ?? "",
	name: api.name,
	fullName: api.full_name,
	longitude: api.location_lonlat?.longitude,
	latitude: api.location_lonlat?.latitude,
	onStationDetectRadiusM: api.on_station_detect_radius_m,
	alwaysShowHh: api.always_show_hh,
	createdAt:
		api.created_at !== undefined ? new Date(api.created_at) : undefined,
});

export const toApiProjectStation = (
	x: Omit<ProjectStation, "id" | "projectId" | "createdAt">
): ApiProjectStation => ({
	name: x.name,
	full_name: x.fullName,
	location_lonlat:
		x.longitude !== undefined && x.latitude !== undefined
			? { longitude: x.longitude, latitude: x.latitude }
			: undefined,
	on_station_detect_radius_m: x.onStationDetectRadiusM,
	always_show_hh: x.alwaysShowHh,
});

// Work

type ApiWork = components["schemas"]["Work"];

export const fromApiWork = (api: ApiWork): Work => ({
	id: api.works_id ?? "",
	workGroupId: api.work_groups_id ?? "",
	name: api.name,
	description: api.description,
	affectDate: fromApiDateOnly(api.affect_date),
	affixContentType: api.affix_content_type,
	affixContent: api.affix_content,
	remarks: api.remarks,
	hasETrainTimetable: api.has_e_train_timetable,
	eTrainTimetableContentType: api.e_train_timetable_content_type,
	eTrainTimetableContent: api.e_train_timetable_content,
	createdAt:
		api.created_at !== undefined ? new Date(api.created_at) : undefined,
});

export const toApiWork = (
	x: Omit<Work, "id" | "workGroupId" | "createdAt">
): ApiWork => ({
	name: x.name,
	description: x.description,
	affect_date: toApiDateOnly(x.affectDate),
	affix_content_type: x.affixContentType as ApiWork["affix_content_type"],
	affix_content: x.affixContent,
	remarks: x.remarks,
	has_e_train_timetable: x.hasETrainTimetable,
	e_train_timetable_content_type:
		x.eTrainTimetableContentType as ApiWork["e_train_timetable_content_type"],
	e_train_timetable_content: x.eTrainTimetableContent,
});

// Train

type ApiTrain = components["schemas"]["Train"];

export const fromApiTrain = (api: ApiTrain): Train => ({
	id: api.trains_id ?? "",
	workId: api.works_id ?? "",
	description: api.description,
	trainNumber: api.train_number,
	direction: api.direction,
	dayCount: api.day_count,
	maxSpeed: api.max_speed,
	speedType: api.speed_type,
	nominalTractiveCapacity: api.nominal_tractive_capacity,
	carCount: api.car_count,
	destination: api.destination,
	beginRemarks: api.begin_remarks,
	afterRemarks: api.after_remarks,
	remarks: api.remarks,
	beforeDeparture: api.before_departure,
	afterArrive: api.after_arrive,
	trainInfo: api.train_info,
	isRideOnMoving: toBool(api.is_ride_on_moving),
	createdAt:
		api.created_at !== undefined ? new Date(api.created_at) : undefined,
});

export const toApiTrain = (
	x: Omit<Train, "id" | "workId" | "createdAt">
): ApiTrain => ({
	description: x.description,
	train_number: x.trainNumber,
	direction: x.direction,
	day_count: x.dayCount,
	max_speed: x.maxSpeed,
	speed_type: x.speedType,
	nominal_tractive_capacity: x.nominalTractiveCapacity,
	car_count: x.carCount,
	destination: x.destination,
	begin_remarks: x.beginRemarks,
	after_remarks: x.afterRemarks,
	remarks: x.remarks,
	before_departure: x.beforeDeparture,
	after_arrive: x.afterArrive,
	train_info: x.trainInfo,
	is_ride_on_moving: x.isRideOnMoving,
});

// TimetableRow

type ApiTimetableRow = components["schemas"]["TimetableRow"];

export const fromApiTimetableRow = (api: ApiTimetableRow): TimetableRow => ({
	id: api.timetable_rows_id ?? "",
	trainId: api.trains_id ?? "",
	stationId: api.stations_id,
	stationTrackId: api.station_tracks_id,
	colorIdMarker: api.colors_id_marker,
	stationName: api.stations_name,
	stationIsDeleted: api.stations_is_deleted,
	stationTrackName: api.station_tracks_name,
	stationTrackIsDeleted: api.station_tracks_is_deleted,
	colorName: api.colors_name,
	colorIsDeleted: api.colors_is_deleted,
	description: api.description,
	driveTimeMm: api.drive_time_mm,
	driveTimeSs: api.drive_time_ss,
	// MySQL tinyint(1) comes back over the wire as 0/1 (not JSON true/false),
	// even though the schema types them as boolean. Coerce to real booleans so
	// round-tripping an edit doesn't re-send 0/1 — the backend's BoolValidationRule
	// rejects non-bool with HTTP 400. Preserve undefined for absent fields.
	isOperationOnlyStop: toBool(api.is_operation_only_stop),
	isPass: toBool(api.is_pass),
	hasBracket: toBool(api.has_bracket),
	isLastStop: toBool(api.is_last_stop),
	arriveTimeHh: api.arrive_time_hh,
	arriveTimeMm: api.arrive_time_mm,
	arriveTimeSs: api.arrive_time_ss,
	departureTimeHh: api.departure_time_hh,
	departureTimeMm: api.departure_time_mm,
	departureTimeSs: api.departure_time_ss,
	runInLimit: api.run_in_limit,
	runOutLimit: api.run_out_limit,
	remarks: api.remarks,
	arriveStr: api.arrive_str,
	departureStr: api.departure_str,
	markerText: api.marker_text,
	workType: api.work_type,
	createdAt:
		api.created_at !== undefined ? new Date(api.created_at) : undefined,
	updatedAt:
		api.updated_at !== undefined ? new Date(api.updated_at) : undefined,
});

export const toApiTimetableRow = (
	x: Omit<TimetableRow, "id" | "trainId" | "createdAt" | "updatedAt">
): ApiTimetableRow => ({
	stations_id: x.stationId,
	station_tracks_id: x.stationTrackId,
	colors_id_marker: x.colorIdMarker,
	// description is required (non-null) by the backend. Newly-constructed rows
	// (e.g. the 行を追加 picker, apply-pattern) don't set it, so default to "" —
	// same convention as train create/update (App.tsx handleUpdateTrain).
	description: x.description ?? "",
	drive_time_mm: x.driveTimeMm,
	drive_time_ss: x.driveTimeSs,
	is_operation_only_stop: x.isOperationOnlyStop,
	is_pass: x.isPass,
	has_bracket: x.hasBracket,
	is_last_stop: x.isLastStop,
	arrive_time_hh: x.arriveTimeHh,
	arrive_time_mm: x.arriveTimeMm,
	arrive_time_ss: x.arriveTimeSs,
	departure_time_hh: x.departureTimeHh,
	departure_time_mm: x.departureTimeMm,
	departure_time_ss: x.departureTimeSs,
	run_in_limit: x.runInLimit,
	run_out_limit: x.runOutLimit,
	remarks: x.remarks,
	arrive_str: x.arriveStr,
	departure_str: x.departureStr,
	marker_text: x.markerText,
	// work_type is UNIMPLEMENTED (実装準備中): the backend column is a TINYINT
	// enum whose only case is `none = 0`, so any free-text value (e.g. "荷役")
	// is rejected with `Unknown WorkAtStationType`, which would brick the WHOLE
	// row save and drop co-edited fields. Until the enum is implemented, omit it
	// from the write path so the field degrades gracefully (silently discarded),
	// exactly like the other model-only fields (showHH, arriveHidden). The input
	// is still editable in the UI — see UNIMPLEMENTED.md §3-3.
});

// StationOnLine

type ApiStationOnLine = components["schemas"]["StationOnLine"];

export const fromApiStationOnLine = (api: ApiStationOnLine): StationOnLine => ({
	id: api.stations_on_line_id ?? "",
	projectId: api.projects_id ?? "",
	lineId: api.lines_id ?? "",
	projectStationId: api.project_stations_id ?? "",
	projectStationName: api.project_stations_name,
	projectStationIsDeleted: api.project_stations_is_deleted,
	locationM: api.location_m ?? 0,
	longitude: api.location_lonlat?.longitude,
	latitude: api.location_lonlat?.latitude,
	trackHiddenByDefault: toBool(api.track_hidden_by_default),
	createdAt:
		api.created_at !== undefined ? new Date(api.created_at) : undefined,
});

export const toApiStationOnLine = (
	x: Omit<StationOnLine, "id" | "projectId" | "createdAt">
): ApiStationOnLine => ({
	lines_id: x.lineId,
	project_stations_id: x.projectStationId,
	location_m: x.locationM,
	location_lonlat:
		x.longitude !== undefined && x.latitude !== undefined
			? { longitude: x.longitude, latitude: x.latitude }
			: undefined,
	track_hidden_by_default: x.trackHiddenByDefault,
});

// StopPattern

type ApiStopPattern = components["schemas"]["StopPattern"];

export const fromApiStopPattern = (api: ApiStopPattern): StopPattern => ({
	id: api.stop_patterns_id ?? "",
	projectId: api.projects_id ?? "",
	lineId: api.lines_id ?? "",
	name: api.name ?? "",
	fromProjectStationId: api.from_project_stations_id,
	toProjectStationId: api.to_project_stations_id,
	direction: api.direction,
	createdAt:
		api.created_at !== undefined ? new Date(api.created_at) : undefined,
});

export const toApiStopPattern = (
	x: Omit<StopPattern, "id" | "projectId" | "createdAt">
): ApiStopPattern => ({
	lines_id: x.lineId,
	name: x.name,
	from_project_stations_id: x.fromProjectStationId,
	to_project_stations_id: x.toProjectStationId,
	direction: x.direction as 1 | -1 | undefined,
});

// StopPatternRow

type ApiStopPatternRow = components["schemas"]["StopPatternRow"];

export const fromApiStopPatternRow = (
	api: ApiStopPatternRow
): StopPatternRow => ({
	id: api.stop_pattern_rows_id ?? "",
	projectId: api.projects_id ?? "",
	stopPatternId: api.stop_patterns_id ?? "",
	projectStationId: api.project_stations_id ?? "",
	sortKey: api.sort_key,
	trackName: api.track_name,
	trackHidden: toBool(api.track_hidden),
	isOperationOnlyStop: toBool(api.is_operation_only_stop),
	isPass: toBool(api.is_pass),
	driveTimeMm: api.drive_time_mm,
	driveTimeSs: api.drive_time_ss,
	dwellTimeMm: api.dwell_time_mm,
	dwellTimeSs: api.dwell_time_ss,
	showArrive: toBool(api.show_arrive),
	showDeparture: toBool(api.show_departure),
	arriveStr: api.arrive_str,
	departureStr: api.departure_str,
	runInLimit: api.run_in_limit,
	runOutLimit: api.run_out_limit,
	remarks: api.remarks,
	alwaysShowHh: toBool(api.always_show_hh),
	createdAt:
		api.created_at !== undefined ? new Date(api.created_at) : undefined,
});

export const toApiStopPatternRow = (
	x: Omit<StopPatternRow, "id" | "projectId" | "stopPatternId" | "createdAt">
): ApiStopPatternRow => ({
	project_stations_id: x.projectStationId,
	sort_key: x.sortKey,
	track_name: x.trackName,
	track_hidden: x.trackHidden,
	is_operation_only_stop: x.isOperationOnlyStop,
	is_pass: x.isPass,
	drive_time_mm: x.driveTimeMm,
	drive_time_ss: x.driveTimeSs,
	dwell_time_mm: x.dwellTimeMm,
	dwell_time_ss: x.dwellTimeSs,
	show_arrive: x.showArrive,
	show_departure: x.showDeparture,
	arrive_str: x.arriveStr,
	departure_str: x.departureStr,
	run_in_limit: x.runInLimit,
	run_out_limit: x.runOutLimit,
	remarks: x.remarks,
	always_show_hh: x.alwaysShowHh,
});

// InviteKey

type ApiInviteKey = components["schemas"]["InviteKey"];

export const fromApiInviteKey = (api: ApiInviteKey): InviteKey => ({
	id: api.invite_keys_id ?? "",
	workGroupId: api.work_groups_id ?? "",
	description: api.description,
	privilegeType: api.privilege_type,
	validFrom:
		api.valid_from !== undefined ? new Date(api.valid_from) : undefined,
	expiresAt:
		api.expires_at !== undefined ? new Date(api.expires_at) : undefined,
	useLimit: api.use_limit,
	disabledAt:
		api.disabled_at !== undefined ? new Date(api.disabled_at) : undefined,
	createdAt:
		api.created_at !== undefined ? new Date(api.created_at) : undefined,
});

export const toApiInviteKey = (
	x: Pick<
		InviteKey,
		"description" | "privilegeType" | "validFrom" | "expiresAt" | "useLimit"
	>
): ApiInviteKey => ({
	description: x.description,
	privilege_type: x.privilegeType,
	valid_from: x.validFrom?.toISOString(),
	expires_at: x.expiresAt?.toISOString(),
	use_limit: x.useLimit,
});
