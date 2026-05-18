export type Project = {
	id: string;
	name: string;
	description: string;
	createdAt?: Date;
	privilegeType?: "read" | "write" | "admin";
};

export type WorkGroup = {
	id: string;
	projectId: string;
	name: string;
	description: string;
	createdAt?: Date;
	privilegeType?: "read" | "write" | "admin";
};

export type Work = {
	id: string;
	workGroupId: string;
	name: string;
	description: string;
	affectDate?: Date;
	affixContentType?: string;
	affixContent?: string;
	remarks?: string;
	hasETrainTimetable?: boolean;
	eTrainTimetableContentType?: string;
	eTrainTimetableContent?: string;
	createdAt?: Date;
};

export type Train = {
	id: string;
	workId: string;
	description: string;
	trainNumber: string;
	direction: number;
	dayCount: number;
	maxSpeed?: string;
	speedType?: string;
	nominalTractiveCapacity?: string;
	carCount?: number;
	destination?: string;
	beginRemarks?: string;
	afterRemarks?: string;
	remarks?: string;
	beforeDeparture?: string;
	afterArrive?: string;
	trainInfo?: string;
	isRideOnMoving?: boolean;
	createdAt?: Date;
};

export type TimetableRow = {
	id: string;
	trainId: string;
	stationId?: string;
	stationTrackId?: string;
	colorIdMarker?: string;
	description?: string;
	driveTimeMm?: number;
	driveTimeSs?: number;
	isOperationOnlyStop?: boolean;
	isPass?: boolean;
	hasBracket?: boolean;
	isLastStop?: boolean;
	arriveTimeHh?: number;
	arriveTimeMm?: number;
	arriveTimeSs?: number;
	departureTimeHh?: number;
	departureTimeMm?: number;
	departureTimeSs?: number;
	runInLimit?: number;
	runOutLimit?: number;
	remarks?: string;
	arriveStr?: string;
	departureStr?: string;
	markerText?: string;
	workType?: string;
	createdAt?: Date;
	updatedAt?: Date;
};

export type Line = {
	id: string;
	projectId: string;
	name: string;
	description: string;
	createdAt?: Date;
};

export type ProjectStation = {
	id: string;
	projectId: string;
	name: string;
	fullName?: string;
	longitude?: number;
	latitude?: number;
	onStationDetectRadiusM?: number;
	alwaysShowHh?: boolean;
	createdAt?: Date;
};

export type Station = {
	id: string;
	workGroupId: string;
	name: string;
	description: string;
	locationKm: number;
	recordType: number;
	longitude?: number;
	latitude?: number;
	onStationDetectRadiusM?: number;
	createdAt?: Date;
};

export type StationOnLine = {
	id: string;
	projectId: string;
	lineId: string;
	projectStationId: string;
	locationM: number;
	longitude?: number;
	latitude?: number;
	trackHiddenByDefault?: boolean;
	createdAt?: Date;
};

export type StopPattern = {
	id: string;
	projectId: string;
	lineId: string;
	name: string;
	fromProjectStationId?: string;
	toProjectStationId?: string;
	direction?: number;
	createdAt?: Date;
};

export type StopPatternRow = {
	id: string;
	projectId: string;
	stopPatternId: string;
	projectStationId: string;
	sortKey?: number;
	trackName?: string;
	trackHidden?: boolean;
	isOperationOnlyStop?: boolean;
	isPass?: boolean;
	driveTimeMm?: number;
	driveTimeSs?: number;
	dwellTimeMm?: number;
	dwellTimeSs?: number;
	showArrive?: boolean;
	showDeparture?: boolean;
	arriveStr?: string;
	departureStr?: string;
	runInLimit?: number;
	runOutLimit?: number;
	remarks?: string;
	alwaysShowHh?: boolean;
	createdAt?: Date;
};
