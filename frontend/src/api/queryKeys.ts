export const queryKeys = {
	projects: () => ["projects"] as const,
	project: (id: string) => ["projects", id] as const,
	workGroups: (projectId: string) =>
		["projects", projectId, "workGroups"] as const,
	workGroup: (projectId: string, id: string) =>
		["projects", projectId, "workGroups", id] as const,
	works: (workGroupId: string) => ["workGroups", workGroupId, "works"] as const,
	work: (workGroupId: string, id: string) =>
		["workGroups", workGroupId, "works", id] as const,
	trains: (workId: string) => ["works", workId, "trains"] as const,
	train: (workId: string, id: string) =>
		["works", workId, "trains", id] as const,
	timetableRows: (trainId: string) =>
		["trains", trainId, "timetableRows"] as const,
	timetableRow: (trainId: string, id: string) =>
		["trains", trainId, "timetableRows", id] as const,
	lines: (projectId: string) => ["projects", projectId, "lines"] as const,
	line: (projectId: string, id: string) =>
		["projects", projectId, "lines", id] as const,
	projectStations: (projectId: string) =>
		["projects", projectId, "projectStations"] as const,
	projectStation: (projectId: string, id: string) =>
		["projects", projectId, "projectStations", id] as const,
	stations: (workGroupId: string) =>
		["workGroups", workGroupId, "stations"] as const,
	station: (workGroupId: string, id: string) =>
		["workGroups", workGroupId, "stations", id] as const,
	stationsOnLine: (lineId: string) =>
		["lines", lineId, "stationsOnLine"] as const,
	stationOnLine: (lineId: string, id: string) =>
		["lines", lineId, "stationsOnLine", id] as const,
	stopPatterns: (lineId: string) => ["lines", lineId, "stopPatterns"] as const,
	stopPattern: (lineId: string, id: string) =>
		["lines", lineId, "stopPatterns", id] as const,
	stopPatternRows: (stopPatternId: string) =>
		["stopPatterns", stopPatternId, "stopPatternRows"] as const,
	stopPatternRow: (stopPatternId: string, id: string) =>
		["stopPatterns", stopPatternId, "stopPatternRows", id] as const,
} as const;
