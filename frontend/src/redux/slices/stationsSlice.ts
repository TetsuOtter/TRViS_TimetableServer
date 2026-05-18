import { createAsyncThunk, createSlice } from "@reduxjs/toolkit";

import { ResponseError } from "../../oas";
import { API_RES_HEADER_X_TOTAL_COUNT } from "../../utils/Constants";
import { stationApiSelector } from "../selectors/apiSelector";
import { stationListSelector } from "../selectors/stationsSelector";
import { currentShowingWorkGroupIdSelector } from "../selectors/workGroupsSelector";

import { setCurrentShowingWorkGroup } from "./workGroupsSlice";

import type { Station } from "../../oas";
import type { DateToNumberObjectType } from "../../utils/DateToNumberType";
import type { RootState } from "../store";
import type { PayloadAction } from "@reduxjs/toolkit";

export type StationsState = {
	stationList: DateToNumberObjectType<Station>[];

	isLoading: boolean;

	currentPageFrom1: number;
	perPage: number;
	totalItemsCount: number;
	topId: string | undefined;

	isEditing: boolean;
	editErrorMessage: string | undefined;
	editTargetStationId: string | undefined;

	currentShowingStation: DateToNumberObjectType<Station> | undefined;
};

const initialState: StationsState = {
	stationList: [],

	isLoading: false,

	currentPageFrom1: 1,
	perPage: 5,
	totalItemsCount: 0,
	topId: undefined,

	isEditing: false,
	editErrorMessage: undefined,
	editTargetStationId: undefined,

	currentShowingStation: undefined,
};

export const stationsSlice = createSlice({
	name: "stations",
	initialState: initialState,
	reducers: {
		setIsLoading: (state, action: PayloadAction<boolean>) => {
			state.isLoading = action.payload;
		},
		setIsEditing: (
			state,
			action: PayloadAction<{ isEditing: boolean; targetId?: string }>
		) => {
			state.isEditing = action.payload.isEditing;
			state.editErrorMessage = undefined;
			state.editTargetStationId = action.payload.targetId;
		},
		setTotalItemsCount: (state, action: PayloadAction<number>) => {
			state.totalItemsCount = action.payload;
		},
		setCurrentShowingStation: (
			state,
			action: PayloadAction<DateToNumberObjectType<Station> | undefined>
		) => {
			state.currentShowingStation = action.payload;
		},
		setStationList: (
			state,
			action: PayloadAction<DateToNumberObjectType<Station>[]>
		) => {
			state.stationList = action.payload;
		},
	},
	extraReducers: (builder) => {
		builder
			.addCase(reloadStations.pending, (state) => {
				state.isLoading = true;
			})
			.addCase(reloadStations.rejected, (state) => {
				state.isLoading = false;
			})
			.addCase(
				reloadStations.fulfilled,
				(state, action: PayloadAction<DateToNumberObjectType<Station>[]>) => {
					state.isLoading = false;
					state.stationList = action.payload;
				}
			);

		builder
			.addCase(createStation.pending, (state) => {
				state.editErrorMessage = undefined;
			})
			.addCase(createStation.rejected, (state, { payload, error }) => {
				console.log("createStation.rejected", payload, error);
				if (typeof payload === "string") {
					state.editErrorMessage = payload;
				} else {
					state.editErrorMessage = "Unknown error";
				}
			})
			.addCase(createStation.fulfilled, (state) => {
				state.isEditing = false;
				state.editTargetStationId = undefined;
			});
	},
});

export type ReloadStationsPayloadType = {
	topId?: string;
	currentPageFrom1: number;
	perPage: number;
};
export const reloadStations = createAsyncThunk<
	DateToNumberObjectType<Station>[],
	ReloadStationsPayloadType | undefined,
	{ state: RootState }
>("stations/reloadStations", async (payload, { dispatch, getState }) => {
	const state = getState();
	const stationsState = payload ?? state.stations;
	const api = stationApiSelector(state);
	const workGroupsId = currentShowingWorkGroupIdSelector(state);
	if (workGroupsId === undefined) {
		throw new Error("WorkGroupsId is undefined");
	}

	const resultRaw = await api.getStationListRaw({
		workGroupId: workGroupsId,
		top: stationsState.topId ?? state.stations.topId,
		p: stationsState.currentPageFrom1,
		limit: stationsState.perPage,
	});

	const totalCountStr = resultRaw.raw.headers.get(API_RES_HEADER_X_TOTAL_COUNT);
	const totalCount = totalCountStr != null ? Number(totalCountStr) : undefined;
	dispatch(stationsSlice.actions.setTotalItemsCount(totalCount ?? 0));

	const result = await resultRaw.value();
	return result.map((station) => ({
		...station,
		createdAt: station.createdAt?.getTime(),
	}));
});

export const createStation = createAsyncThunk<
	void,
	DateToNumberObjectType<Station>,
	{ state: RootState }
>(
	"stations/createStation",
	async (
		payload,
		{ dispatch, getState, rejectWithValue, fulfillWithValue }
	) => {
		const state = getState();
		const api = stationApiSelector(state);
		const workGroupsId = currentShowingWorkGroupIdSelector(state);
		if (workGroupsId === undefined) {
			throw new Error("WorkGroupsId is undefined");
		}

		try {
			const resultRaw = await api.createStationRaw({
				workGroupId: workGroupsId,
				station: {
					...payload,
					createdAt:
						payload.createdAt != null ? new Date(payload.createdAt) : undefined,
				},
			});
			const result = await resultRaw.value();

			await dispatch(
				reloadStations({
					topId: result.stationsId,
					currentPageFrom1: 1,
					perPage: state.stations.perPage,
				})
			);
		} catch (e) {
			if (e instanceof ResponseError) {
				const errorObj = await e.response.json();
				console.log("createStation errorObj", errorObj);
				return rejectWithValue(errorObj.message ?? e.message);
			}
			console.log("createStation error General", e);
			return rejectWithValue("Unknown error");
		}

		return fulfillWithValue(undefined);
	}
);
export const updateStation = createAsyncThunk<
	void,
	DateToNumberObjectType<Station>,
	{ state: RootState }
>(
	"stations/updateStation",
	async (
		payload,
		{ dispatch, getState, rejectWithValue, fulfillWithValue }
	) => {
		const state = getState();
		const api = stationApiSelector(state);
		const stationsId = payload.stationsId;
		if (stationsId === undefined) {
			throw new Error("StationsId is undefined");
		}

		try {
			await api.updateStation({
				stationId: stationsId,
				station: {
					...payload,
					createdAt:
						payload.createdAt != null ? new Date(payload.createdAt) : undefined,
				},
			});

			await dispatch(reloadStations(undefined));
		} catch (e) {
			if (e instanceof ResponseError) {
				const errorObj = await e.response.json();
				console.log("updateStation errorObj", errorObj);
				return rejectWithValue(errorObj.message ?? e.message);
			}
			console.log("updateStation error General", e);
			return rejectWithValue("Unknown error");
		}

		return fulfillWithValue(undefined);
	}
);

export const deleteStation = createAsyncThunk<
	void,
	{ stationId: string },
	{ state: RootState }
>(
	"stations/deleteStation",
	async ({ stationId }, { dispatch, getState, rejectWithValue }) => {
		const state = getState();
		const api = stationApiSelector(state);

		try {
			await api.deleteStation({
				stationId: stationId,
			});
		} catch (e) {
			if (e instanceof ResponseError) {
				const errorObj = await e.response.json();
				return rejectWithValue(errorObj);
			}
			throw e;
		}

		await dispatch(
			reloadStations({
				topId: state.stations.topId,
				currentPageFrom1: state.stations.currentPageFrom1,
				perPage: state.stations.perPage,
			})
		);
		return;
	}
);

export const setCurrentShowingStation = createAsyncThunk<
	void,
	{ stationId: string },
	{ state: RootState }
>(
	"stations/setCurrentShowingStation",
	async ({ stationId }, { dispatch, getState, rejectWithValue }) => {
		const state = getState();
		const api = stationApiSelector(state);

		const stationsList = stationListSelector(state);
		const station = stationsList.find(
			(station) => station.stationsId === stationId
		);
		if (station !== undefined) {
			dispatch(stationsSlice.actions.setCurrentShowingStation(station));
			return;
		}

		try {
			dispatch(stationsSlice.actions.setCurrentShowingStation(undefined));
			const getStationResult = await api.getStation({
				stationId: stationId,
			});

			if (getStationResult.workGroupsId == null) {
				throw new Error("workGroupsId is undefined");
			}

			const stationToStore: DateToNumberObjectType<Station> & {
				workGroupsId: NonNullable<Station["workGroupsId"]>;
			} = {
				...getStationResult,
				// null警告回避のため、個別で代入する
				workGroupsId: getStationResult.workGroupsId,
				createdAt: getStationResult.createdAt?.getTime(),
			};
			dispatch(stationsSlice.actions.setStationList([stationToStore]));
			dispatch(stationsSlice.actions.setCurrentShowingStation(stationToStore));

			await dispatch(
				setCurrentShowingWorkGroup({ workGroupId: stationToStore.workGroupsId })
			);
			return;
		} catch (e) {
			if (e instanceof ResponseError) {
				const errorObj = await e.response.json();
				return rejectWithValue(errorObj);
			}
			throw e;
		}
	}
);

export const { setIsLoading, setIsEditing } = stationsSlice.actions;

export default stationsSlice.reducer;
