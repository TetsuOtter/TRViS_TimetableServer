import type {
	Line,
	Project,
	ProjectStation,
	Station,
	StationOnLine,
	StopPattern,
	StopPatternRow,
	TimetableRow,
	Train,
	Work,
	WorkGroup,
} from "../types/entities";
import type {
	Line as ApiLine,
	Project as ApiProject,
	ProjectStation as ApiProjectStation,
	Station as ApiStation,
	StationOnLine as ApiStationOnLine,
	StopPattern as ApiStopPattern,
	StopPatternDirectionEnum,
	StopPatternRow as ApiStopPatternRow,
	TimetableRow as ApiTimetableRow,
	Train as ApiTrain,
	Work as ApiWork,
	WorkAffixContentTypeEnum,
	WorkETrainTimetableContentTypeEnum,
	WorkGroup as ApiWorkGroup,
} from "trvis-api";

// Project

export const fromApiProject = (api: ApiProject): Project => ({
	id: api.projectsId ?? "",
	name: api.name,
	description: api.description,
	createdAt: api.createdAt,
	privilegeType: api.privilegeType,
});

export const toApiProject = (
	x: Omit<Project, "id" | "createdAt" | "privilegeType">
): ApiProject => ({
	name: x.name,
	description: x.description,
});

// WorkGroup

export const fromApiWorkGroup = (api: ApiWorkGroup): WorkGroup => ({
	id: api.workGroupsId ?? "",
	projectId: api.projectsId ?? "",
	name: api.name,
	description: api.description,
	createdAt: api.createdAt,
	privilegeType: api.privilegeType,
});

export const toApiWorkGroup = (
	x: Omit<WorkGroup, "id" | "projectId" | "createdAt" | "privilegeType">
): ApiWorkGroup => ({
	name: x.name,
	description: x.description,
});

// Work

export const fromApiWork = (api: ApiWork): Work => ({
	id: api.worksId ?? "",
	workGroupId: api.workGroupsId ?? "",
	name: api.name,
	description: api.description,
	affectDate: api.affectDate,
	affixContentType: api.affixContentType,
	affixContent: api.affixContent,
	remarks: api.remarks,
	hasETrainTimetable: api.hasETrainTimetable,
	eTrainTimetableContentType: api.eTrainTimetableContentType,
	eTrainTimetableContent: api.eTrainTimetableContent,
	createdAt: api.createdAt,
});

export const toApiWork = (
	x: Omit<Work, "id" | "workGroupId" | "createdAt">
): ApiWork => ({
	name: x.name,
	description: x.description,
	affectDate: x.affectDate,
	affixContentType: x.affixContentType as WorkAffixContentTypeEnum | undefined,
	affixContent: x.affixContent,
	remarks: x.remarks,
	hasETrainTimetable: x.hasETrainTimetable,
	eTrainTimetableContentType: x.eTrainTimetableContentType as
		| WorkETrainTimetableContentTypeEnum
		| undefined,
	eTrainTimetableContent: x.eTrainTimetableContent,
});

// Train

export const fromApiTrain = (api: ApiTrain): Train => ({
	id: api.trainsId ?? "",
	workId: api.worksId ?? "",
	description: api.description,
	trainNumber: api.trainNumber,
	direction: api.direction,
	dayCount: api.dayCount,
	maxSpeed: api.maxSpeed,
	speedType: api.speedType,
	nominalTractiveCapacity: api.nominalTractiveCapacity,
	carCount: api.carCount,
	destination: api.destination,
	beginRemarks: api.beginRemarks,
	afterRemarks: api.afterRemarks,
	remarks: api.remarks,
	beforeDeparture: api.beforeDeparture,
	afterArrive: api.afterArrive,
	trainInfo: api.trainInfo,
	isRideOnMoving: api.isRideOnMoving,
	createdAt: api.createdAt,
});

export const toApiTrain = (
	x: Omit<Train, "id" | "workId" | "createdAt">
): ApiTrain => ({
	description: x.description,
	trainNumber: x.trainNumber,
	direction: x.direction,
	dayCount: x.dayCount,
	maxSpeed: x.maxSpeed,
	speedType: x.speedType,
	nominalTractiveCapacity: x.nominalTractiveCapacity,
	carCount: x.carCount,
	destination: x.destination,
	beginRemarks: x.beginRemarks,
	afterRemarks: x.afterRemarks,
	remarks: x.remarks,
	beforeDeparture: x.beforeDeparture,
	afterArrive: x.afterArrive,
	trainInfo: x.trainInfo,
	isRideOnMoving: x.isRideOnMoving,
});

// TimetableRow

export const fromApiTimetableRow = (api: ApiTimetableRow): TimetableRow => ({
	id: api.timetableRowsId ?? "",
	trainId: api.trainsId ?? "",
	stationId: api.stationsId,
	stationTrackId: api.stationTracksId,
	colorIdMarker: api.colorsIdMarker,
	description: api.description,
	driveTimeMm: api.driveTimeMm,
	driveTimeSs: api.driveTimeSs,
	isOperationOnlyStop: api.isOperationOnlyStop,
	isPass: api.isPass,
	hasBracket: api.hasBracket,
	isLastStop: api.isLastStop,
	arriveTimeHh: api.arriveTimeHh,
	arriveTimeMm: api.arriveTimeMm,
	arriveTimeSs: api.arriveTimeSs,
	departureTimeHh: api.departureTimeHh,
	departureTimeMm: api.departureTimeMm,
	departureTimeSs: api.departureTimeSs,
	runInLimit: api.runInLimit,
	runOutLimit: api.runOutLimit,
	remarks: api.remarks,
	arriveStr: api.arriveStr,
	departureStr: api.departureStr,
	markerText: api.markerText,
	workType: api.workType,
	createdAt: api.createdAt,
	updatedAt: api.updatedAt,
});

export const toApiTimetableRow = (
	x: Omit<TimetableRow, "id" | "trainId" | "createdAt" | "updatedAt">
): ApiTimetableRow => ({
	stationsId: x.stationId,
	stationTracksId: x.stationTrackId,
	colorsIdMarker: x.colorIdMarker,
	description: x.description,
	driveTimeMm: x.driveTimeMm,
	driveTimeSs: x.driveTimeSs,
	isOperationOnlyStop: x.isOperationOnlyStop,
	isPass: x.isPass,
	hasBracket: x.hasBracket,
	isLastStop: x.isLastStop,
	arriveTimeHh: x.arriveTimeHh,
	arriveTimeMm: x.arriveTimeMm,
	arriveTimeSs: x.arriveTimeSs,
	departureTimeHh: x.departureTimeHh,
	departureTimeMm: x.departureTimeMm,
	departureTimeSs: x.departureTimeSs,
	runInLimit: x.runInLimit,
	runOutLimit: x.runOutLimit,
	remarks: x.remarks,
	arriveStr: x.arriveStr,
	departureStr: x.departureStr,
	markerText: x.markerText,
	workType: x.workType,
});

// Line

export const fromApiLine = (api: ApiLine): Line => ({
	id: api.linesId ?? "",
	projectId: api.projectsId ?? "",
	name: api.name,
	description: api.description,
	createdAt: api.createdAt,
});

export const toApiLine = (
	x: Omit<Line, "id" | "projectId" | "createdAt">
): ApiLine => ({
	name: x.name,
	description: x.description,
});

// ProjectStation

export const fromApiProjectStation = (
	api: ApiProjectStation
): ProjectStation => ({
	id: api.projectStationsId ?? "",
	projectId: api.projectsId ?? "",
	name: api.name,
	fullName: api.fullName,
	longitude: api.locationLonlat?.longitude,
	latitude: api.locationLonlat?.latitude,
	onStationDetectRadiusM: api.onStationDetectRadiusM,
	alwaysShowHh: api.alwaysShowHh,
	createdAt: api.createdAt,
});

export const toApiProjectStation = (
	x: Omit<ProjectStation, "id" | "projectId" | "createdAt">
): ApiProjectStation => ({
	name: x.name,
	fullName: x.fullName,
	locationLonlat:
		x.longitude !== undefined && x.latitude !== undefined
			? { longitude: x.longitude, latitude: x.latitude }
			: undefined,
	onStationDetectRadiusM: x.onStationDetectRadiusM,
	alwaysShowHh: x.alwaysShowHh,
});

// Station

export const fromApiStation = (api: ApiStation): Station => ({
	id: api.stationsId ?? "",
	workGroupId: api.workGroupsId ?? "",
	name: api.name,
	description: api.description,
	locationKm: api.locationKm,
	recordType: api.recordType,
	longitude: api.locationLonlat?.longitude,
	latitude: api.locationLonlat?.latitude,
	onStationDetectRadiusM: api.onStationDetectRadiusM,
	createdAt: api.createdAt,
});

export const toApiStation = (
	x: Omit<Station, "id" | "workGroupId" | "createdAt">
): ApiStation => ({
	name: x.name,
	description: x.description,
	locationKm: x.locationKm,
	recordType: x.recordType,
	locationLonlat:
		x.longitude !== undefined && x.latitude !== undefined
			? { longitude: x.longitude, latitude: x.latitude }
			: undefined,
	onStationDetectRadiusM: x.onStationDetectRadiusM,
});

// StationOnLine

export const fromApiStationOnLine = (api: ApiStationOnLine): StationOnLine => ({
	id: api.stationsOnLineId ?? "",
	projectId: api.projectsId ?? "",
	lineId: api.linesId,
	projectStationId: api.projectStationsId,
	locationM: api.locationM,
	longitude: api.locationLonlat?.longitude,
	latitude: api.locationLonlat?.latitude,
	trackHiddenByDefault: api.trackHiddenByDefault,
	createdAt: api.createdAt,
});

export const toApiStationOnLine = (
	x: Omit<StationOnLine, "id" | "projectId" | "createdAt">
): ApiStationOnLine => ({
	linesId: x.lineId,
	projectStationsId: x.projectStationId,
	locationM: x.locationM,
	locationLonlat:
		x.longitude !== undefined && x.latitude !== undefined
			? { longitude: x.longitude, latitude: x.latitude }
			: undefined,
	trackHiddenByDefault: x.trackHiddenByDefault,
});

// StopPattern

export const fromApiStopPattern = (api: ApiStopPattern): StopPattern => ({
	id: api.stopPatternsId ?? "",
	projectId: api.projectsId ?? "",
	lineId: api.linesId,
	name: api.name,
	fromProjectStationId: api.fromProjectStationsId,
	toProjectStationId: api.toProjectStationsId,
	direction: api.direction,
	createdAt: api.createdAt,
});

export const toApiStopPattern = (
	x: Omit<StopPattern, "id" | "projectId" | "createdAt">
): ApiStopPattern => ({
	linesId: x.lineId,
	name: x.name,
	fromProjectStationsId: x.fromProjectStationId,
	toProjectStationsId: x.toProjectStationId,
	direction: x.direction as StopPatternDirectionEnum | undefined,
});

// StopPatternRow

export const fromApiStopPatternRow = (
	api: ApiStopPatternRow
): StopPatternRow => ({
	id: api.stopPatternRowsId ?? "",
	projectId: api.projectsId ?? "",
	stopPatternId: api.stopPatternsId ?? "",
	projectStationId: api.projectStationsId,
	sortKey: api.sortKey,
	trackName: api.trackName,
	trackHidden: api.trackHidden,
	isOperationOnlyStop: api.isOperationOnlyStop,
	isPass: api.isPass,
	driveTimeMm: api.driveTimeMm,
	driveTimeSs: api.driveTimeSs,
	dwellTimeMm: api.dwellTimeMm,
	dwellTimeSs: api.dwellTimeSs,
	showArrive: api.showArrive,
	showDeparture: api.showDeparture,
	arriveStr: api.arriveStr,
	departureStr: api.departureStr,
	runInLimit: api.runInLimit,
	runOutLimit: api.runOutLimit,
	remarks: api.remarks,
	alwaysShowHh: api.alwaysShowHh,
	createdAt: api.createdAt,
});

export const toApiStopPatternRow = (
	x: Omit<StopPatternRow, "id" | "projectId" | "stopPatternId" | "createdAt">
): ApiStopPatternRow => ({
	projectStationsId: x.projectStationId,
	sortKey: x.sortKey,
	trackName: x.trackName,
	trackHidden: x.trackHidden,
	isOperationOnlyStop: x.isOperationOnlyStop,
	isPass: x.isPass,
	driveTimeMm: x.driveTimeMm,
	driveTimeSs: x.driveTimeSs,
	dwellTimeMm: x.dwellTimeMm,
	dwellTimeSs: x.dwellTimeSs,
	showArrive: x.showArrive,
	showDeparture: x.showDeparture,
	arriveStr: x.arriveStr,
	departureStr: x.departureStr,
	runInLimit: x.runInLimit,
	runOutLimit: x.runOutLimit,
	remarks: x.remarks,
	alwaysShowHh: x.alwaysShowHh,
});
