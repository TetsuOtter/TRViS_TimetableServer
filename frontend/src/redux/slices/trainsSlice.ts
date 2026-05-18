import { createAsyncThunk, createSlice } from "@reduxjs/toolkit";

import { ResponseError } from "../../oas";
import { API_RES_HEADER_X_TOTAL_COUNT } from "../../utils/Constants";
import { trainApiSelector } from "../selectors/apiSelector";
import { trainListSelector } from "../selectors/trainsSelector";
import { currentShowingWorkIdSelector } from "../selectors/worksSelector";

import { setCurrentShowingWork } from "./worksSlice";

import type { Train } from "../../oas";
import type { DateToNumberObjectType } from "../../utils/DateToNumberType";
import type { RootState } from "../store";
import type { PayloadAction } from "@reduxjs/toolkit";

export type TrainsState = {
	trainList: DateToNumberObjectType<Train>[];

	isLoading: boolean;

	currentPageFrom1: number;
	perPage: number;
	totalItemsCount: number;
	topId: string | undefined;

	isEditing: boolean;
	editErrorMessage: string | undefined;
	editTargetTrainId: string | undefined;

	currentShowingTrain: DateToNumberObjectType<Train> | undefined;
};

const initialState: TrainsState = {
	trainList: [],

	isLoading: false,

	currentPageFrom1: 1,
	perPage: 5,
	totalItemsCount: 0,
	topId: undefined,

	isEditing: false,
	editErrorMessage: undefined,
	editTargetTrainId: undefined,

	currentShowingTrain: undefined,
};

export const trainsSlice = createSlice({
	name: "trains",
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
			state.editTargetTrainId = action.payload.targetId;
		},
		setTotalItemsCount: (state, action: PayloadAction<number>) => {
			state.totalItemsCount = action.payload;
		},
		setCurrentShowingTrain: (
			state,
			action: PayloadAction<DateToNumberObjectType<Train> | undefined>
		) => {
			state.currentShowingTrain = action.payload;
		},
		setTrainList: (
			state,
			action: PayloadAction<DateToNumberObjectType<Train>[]>
		) => {
			state.trainList = action.payload;
		},
	},
	extraReducers: (builder) => {
		builder
			.addCase(reloadTrains.pending, (state) => {
				state.isLoading = true;
			})
			.addCase(reloadTrains.rejected, (state) => {
				state.isLoading = false;
			})
			.addCase(
				reloadTrains.fulfilled,
				(state, action: PayloadAction<DateToNumberObjectType<Train>[]>) => {
					state.isLoading = false;
					state.trainList = action.payload;
				}
			);

		builder
			.addCase(createTrain.pending, (state) => {
				state.editErrorMessage = undefined;
			})
			.addCase(createTrain.rejected, (state, { payload, error }) => {
				console.log("createTrain.rejected", payload, error);
				if (typeof payload === "string") {
					state.editErrorMessage = payload;
				} else {
					state.editErrorMessage = "Unknown error";
				}
			})
			.addCase(createTrain.fulfilled, (state) => {
				state.isEditing = false;
				state.editTargetTrainId = undefined;
			});
	},
});

export type ReloadTrainsPayloadType = {
	topId?: string;
	currentPageFrom1: number;
	perPage: number;
};
export const reloadTrains = createAsyncThunk<
	DateToNumberObjectType<Train>[],
	ReloadTrainsPayloadType | undefined,
	{ state: RootState }
>("trains/reloadTrains", async (payload, { dispatch, getState }) => {
	const state = getState();
	const trainsState = payload ?? state.trains;
	const api = trainApiSelector(state);
	const worksId = currentShowingWorkIdSelector(state);
	if (worksId === undefined) {
		throw new Error("TrainGroupsId is undefined");
	}

	const resultRaw = await api.getTrainListRaw({
		workId: worksId,
		top: trainsState.topId ?? state.trains.topId,
		p: trainsState.currentPageFrom1,
		limit: trainsState.perPage,
	});

	const totalCountStr = resultRaw.raw.headers.get(API_RES_HEADER_X_TOTAL_COUNT);
	const totalCount = totalCountStr != null ? Number(totalCountStr) : undefined;
	dispatch(trainsSlice.actions.setTotalItemsCount(totalCount ?? 0));

	const result = await resultRaw.value();
	return result.map((train) => ({
		...train,
		createdAt: train.createdAt?.getTime(),
	}));
});

export const createTrain = createAsyncThunk<
	void,
	DateToNumberObjectType<Train>,
	{ state: RootState }
>(
	"trains/createTrain",
	async (
		payload,
		{ dispatch, getState, rejectWithValue, fulfillWithValue }
	) => {
		const state = getState();
		const api = trainApiSelector(state);
		const worksId = currentShowingWorkIdSelector(state);
		if (worksId === undefined) {
			throw new Error("WorksId is undefined");
		}

		try {
			const resultRaw = await api.createTrainRaw({
				workId: worksId,
				train: {
					...payload,
					createdAt:
						payload.createdAt != null ? new Date(payload.createdAt) : undefined,
				},
			});
			const result = await resultRaw.value();

			await dispatch(
				reloadTrains({
					topId: result.trainsId,
					currentPageFrom1: 1,
					perPage: state.trains.perPage,
				})
			);
		} catch (e) {
			if (e instanceof ResponseError) {
				const errorObj = await e.response.json();
				console.log("createTrain errorObj", errorObj);
				return rejectWithValue(errorObj.message ?? e.message);
			}
			console.log("createTrain error General", e);
			return rejectWithValue("Unknown error");
		}

		return fulfillWithValue(undefined);
	}
);
export const updateTrain = createAsyncThunk<
	void,
	DateToNumberObjectType<Train>,
	{ state: RootState }
>(
	"trains/updateTrain",
	async (
		payload,
		{ dispatch, getState, rejectWithValue, fulfillWithValue }
	) => {
		const state = getState();
		const api = trainApiSelector(state);
		const trainsId = payload.trainsId;
		if (trainsId === undefined) {
			throw new Error("TrainsId is undefined");
		}

		try {
			await api.updateTrain({
				trainId: trainsId,
				train: {
					...payload,
					createdAt:
						payload.createdAt != null ? new Date(payload.createdAt) : undefined,
				},
			});

			await dispatch(reloadTrains(undefined));
		} catch (e) {
			if (e instanceof ResponseError) {
				const errorObj = await e.response.json();
				console.log("updateTrain errorObj", errorObj);
				return rejectWithValue(errorObj.message ?? e.message);
			}
			console.log("updateTrain error General", e);
			return rejectWithValue("Unknown error");
		}

		return fulfillWithValue(undefined);
	}
);

export const deleteTrain = createAsyncThunk<
	void,
	{ trainId: string },
	{ state: RootState }
>(
	"trains/deleteTrain",
	async ({ trainId }, { dispatch, getState, rejectWithValue }) => {
		const state = getState();
		const api = trainApiSelector(state);

		try {
			await api.deleteTrain({
				trainId: trainId,
			});
		} catch (e) {
			if (e instanceof ResponseError) {
				const errorObj = await e.response.json();
				return rejectWithValue(errorObj);
			}
			throw e;
		}

		await dispatch(
			reloadTrains({
				topId: state.trains.topId,
				currentPageFrom1: state.trains.currentPageFrom1,
				perPage: state.trains.perPage,
			})
		);
		return;
	}
);

export const setCurrentShowingTrain = createAsyncThunk<
	void,
	{ trainId: string },
	{ state: RootState }
>(
	"trains/setCurrentShowingTrain",
	async ({ trainId }, { dispatch, getState, rejectWithValue }) => {
		const state = getState();
		const api = trainApiSelector(state);

		const trainsList = trainListSelector(state);
		const train = trainsList.find((train) => train.trainsId === trainId);
		if (train !== undefined) {
			dispatch(trainsSlice.actions.setCurrentShowingTrain(train));
			return;
		}

		try {
			dispatch(trainsSlice.actions.setCurrentShowingTrain(undefined));
			const getTrainResult = await api.getTrain({
				trainId: trainId,
			});
			const parentId = getTrainResult.worksId;
			if (parentId == null) {
				throw new Error("Works ID is undefined");
			}

			const trainToStore = {
				...getTrainResult,
				createdAt: getTrainResult.createdAt?.getTime(),
			};
			dispatch(trainsSlice.actions.setTrainList([trainToStore]));
			dispatch(trainsSlice.actions.setCurrentShowingTrain(trainToStore));

			await dispatch(setCurrentShowingWork({ workId: parentId }));
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

export const { setIsLoading, setIsEditing } = trainsSlice.actions;

export default trainsSlice.reducer;
