import { createSelector } from "@reduxjs/toolkit";
import {
	ApiInfoApi,
	AuthApi,
	ColorApi,
	Configuration,
	DumpApi,
	InviteKeyApi,
	StationApi,
	StationTrackApi,
	TimetableRowApi,
	TrainApi,
	WorkApi,
	WorkGroupApi,
} from "trvis-api";

import { oasConfigParamsDefaultValue } from "../../api-config";

import { jwtSelector } from "./authInfoSelector";

import type { AppSelector } from "../store";
import type { ConfigurationParameters } from "trvis-api";

const oasConfigParamsWithTokenSelector: AppSelector<ConfigurationParameters> =
	createSelector([jwtSelector], (jwt) => ({
		...oasConfigParamsDefaultValue,
		accessToken: jwt,
	}));

const oasConfigWithTokenSelector = createSelector(
	[oasConfigParamsWithTokenSelector],
	(apiConfig) => new Configuration(apiConfig)
);

export const apiInfoApiSelector = createSelector(
	[oasConfigWithTokenSelector],
	(apiConfig) => new ApiInfoApi(apiConfig)
);

export const authApiSelector = createSelector(
	[oasConfigWithTokenSelector],
	(apiConfig) => new AuthApi(apiConfig)
);

export const colorApiSelector = createSelector(
	[oasConfigWithTokenSelector],
	(apiConfig) => new ColorApi(apiConfig)
);

export const dumpApiSelector = createSelector(
	[oasConfigWithTokenSelector],
	(apiConfig) => new DumpApi(apiConfig)
);

export const inviteKeyApiSelector = createSelector(
	[oasConfigWithTokenSelector],
	(apiConfig) => new InviteKeyApi(apiConfig)
);

export const stationApiSelector = createSelector(
	[oasConfigWithTokenSelector],
	(apiConfig) => new StationApi(apiConfig)
);

export const stationTrackApiSelector = createSelector(
	[oasConfigWithTokenSelector],
	(apiConfig) => new StationTrackApi(apiConfig)
);

export const timetableRowApiSelector = createSelector(
	[oasConfigWithTokenSelector],
	(apiConfig) => new TimetableRowApi(apiConfig)
);

export const trainApiSelector = createSelector(
	[oasConfigWithTokenSelector],
	(apiConfig) => new TrainApi(apiConfig)
);

export const workApiSelector = createSelector(
	[oasConfigWithTokenSelector],
	(apiConfig) => new WorkApi(apiConfig)
);

export const workGroupApiSelector = createSelector(
	[oasConfigWithTokenSelector],
	(apiConfig) => new WorkGroupApi(apiConfig)
);
