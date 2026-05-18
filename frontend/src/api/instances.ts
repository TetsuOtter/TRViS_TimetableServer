import {
	LineApi,
	ProjectApi,
	ProjectStationApi,
	StationApi,
	StationOnLineApi,
	StopPatternApi,
	StopPatternRowApi,
	TimetableRowApi,
	TrainApi,
	WorkApi,
	WorkGroupApi,
} from "trvis-api";

import { apiConfig } from "./client";

export const lineApi = new LineApi(apiConfig);
export const projectApi = new ProjectApi(apiConfig);
export const projectStationApi = new ProjectStationApi(apiConfig);
export const stationApi = new StationApi(apiConfig);
export const stationOnLineApi = new StationOnLineApi(apiConfig);
export const stopPatternApi = new StopPatternApi(apiConfig);
export const stopPatternRowApi = new StopPatternRowApi(apiConfig);
export const timetableRowApi = new TimetableRowApi(apiConfig);
export const trainApi = new TrainApi(apiConfig);
export const workApi = new WorkApi(apiConfig);
export const workGroupApi = new WorkGroupApi(apiConfig);
