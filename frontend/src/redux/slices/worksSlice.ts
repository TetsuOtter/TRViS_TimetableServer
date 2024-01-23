import { createAsyncThunk, createSlice } from "@reduxjs/toolkit";

import { ResponseError } from "../../oas";
import { API_RES_HEADER_X_TOTAL_COUNT } from "../../utils/Constants";
import { workApiSelector } from "../selectors/apiSelector";
import { currentShowingWorkGroupIdSelector } from "../selectors/workGroupsSelector";
import { workListSelector } from "../selectors/worksSelector";

import type { Work } from "../../oas";
import type { DateToNumberObjectType } from "../../utils/DateToNumberType";
import type { RootState } from "../store";
import type { PayloadAction } from "@reduxjs/toolkit";

export interface WorksState {
	workList: DateToNumberObjectType<Work>[];

	isLoading: boolean;

	currentPageFrom1: number;
	perPage: number;
	totalItemsCount: number;
	topId: string | undefined;

	isEditing: boolean;
	editErrorMessage: string | undefined;
	editTargetWorkId: string | undefined;

	currentShowingWork: DateToNumberObjectType<Work> | undefined;
}

const initialState: WorksState = {
	workList: [],

	isLoading: false,

	currentPageFrom1: 1,
	perPage: 5,
	totalItemsCount: 0,
	topId: undefined,

	isEditing: false,
	editErrorMessage: undefined,
	editTargetWorkId: undefined,

	currentShowingWork: undefined,
};

export const worksSlice = createSlice({
	name: "works",
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
			state.editTargetWorkId = action.payload.targetId;
		},
		setTotalItemsCount: (state, action: PayloadAction<number>) => {
			state.totalItemsCount = action.payload;
		},
		setCurrentShowingWork: (
			state,
			action: PayloadAction<DateToNumberObjectType<Work> | undefined>
		) => {
			state.currentShowingWork = action.payload;
		},
		setWorkList: (
			state,
			action: PayloadAction<DateToNumberObjectType<Work>[]>
		) => {
			state.workList = action.payload;
		},
	},
	extraReducers: (builder) => {
		builder
			.addCase(reloadWorks.pending, (state) => {
				state.isLoading = true;
			})
			.addCase(reloadWorks.rejected, (state) => {
				state.isLoading = false;
			})
			.addCase(
				reloadWorks.fulfilled,
				(state, action: PayloadAction<DateToNumberObjectType<Work>[]>) => {
					state.isLoading = false;
					state.workList = action.payload;
				}
			);

		builder
			.addCase(createWork.pending, (state) => {
				state.editErrorMessage = undefined;
			})
			.addCase(createWork.rejected, (state, { payload, error }) => {
				console.log("createWork.rejected", payload, error);
				if (typeof payload === "string") {
					state.editErrorMessage = payload;
				} else {
					state.editErrorMessage = "Unknown error";
				}
			})
			.addCase(createWork.fulfilled, (state) => {
				state.isEditing = false;
				state.editTargetWorkId = undefined;
			});
	},
});

export type ReloadWorksPayloadType = {
	topId?: string;
	currentPageFrom1: number;
	perPage: number;
};
export const reloadWorks = createAsyncThunk<
	DateToNumberObjectType<Work>[],
	ReloadWorksPayloadType | undefined,
	{ state: RootState }
>("works/reloadWorks", async (payload, { dispatch, getState }) => {
	const state = getState();
	const worksState = payload ?? state.works;
	const api = workApiSelector(state);
	const workGroupsId = currentShowingWorkGroupIdSelector(state);
	if (workGroupsId === undefined) {
		throw new Error("WorkGroupsId is undefined");
	}

	const resultRaw = await api.getWorkListRaw({
		workGroupId: workGroupsId,
		top: worksState.topId ?? state.works.topId,
		p: worksState.currentPageFrom1,
		limit: worksState.perPage,
	});

	const totalCountStr = resultRaw.raw.headers.get(API_RES_HEADER_X_TOTAL_COUNT);
	const totalCount = totalCountStr ? Number(totalCountStr) : undefined;
	dispatch(worksSlice.actions.setTotalItemsCount(totalCount ?? 0));

	const result = await resultRaw.value();
	return result.map((work) => ({
		...work,
		createdAt: work.createdAt?.getTime(),
		affectDate: work.affectDate?.getTime(),
	}));
});

export const createWork = createAsyncThunk<
	void,
	DateToNumberObjectType<Work>,
	{ state: RootState }
>(
	"works/createWork",
	async (
		payload,
		{ dispatch, getState, rejectWithValue, fulfillWithValue }
	) => {
		const state = getState();
		const api = workApiSelector(state);
		const workGroupsId = currentShowingWorkGroupIdSelector(state);
		if (workGroupsId === undefined) {
			throw new Error("WorkGroupsId is undefined");
		}

		try {
			const resultRaw = await api.createWorkRaw({
				workGroupId: workGroupsId,
				work: {
					...payload,
					createdAt: payload.createdAt
						? new Date(payload.createdAt)
						: undefined,
					affectDate: payload.affectDate
						? new Date(payload.affectDate)
						: undefined,
				},
			});
			const result = await resultRaw.value();

			await dispatch(
				reloadWorks({
					topId: result.worksId,
					currentPageFrom1: 1,
					perPage: state.works.perPage,
				})
			);
		} catch (e) {
			if (e instanceof ResponseError) {
				const errorObj = await e.response.json();
				console.log("createWork errorObj", errorObj);
				return rejectWithValue(errorObj.message ?? e.message);
			}
			console.log("createWork error General", e);
			return rejectWithValue("Unknown error");
		}

		return fulfillWithValue(undefined);
	}
);

export const deleteWork = createAsyncThunk<
	void,
	{ workId: string },
	{ state: RootState }
>(
	"works/deleteWork",
	async ({ workId }, { dispatch, getState, rejectWithValue }) => {
		const state = getState();
		const api = workApiSelector(state);

		try {
			await api.deleteWork({
				workId: workId,
			});
		} catch (e) {
			if (e instanceof ResponseError) {
				const errorObj = await e.response.json();
				return rejectWithValue(errorObj);
			}
			throw e;
		}

		await dispatch(
			reloadWorks({
				topId: state.works.topId,
				currentPageFrom1: state.works.currentPageFrom1,
				perPage: state.works.perPage,
			})
		);
		return;
	}
);

export const setCurrentShowingWork = createAsyncThunk<
	void,
	{ workId: string },
	{ state: RootState }
>(
	"works/setCurrentShowingWork",
	async ({ workId }, { dispatch, getState, rejectWithValue }) => {
		const state = getState();
		const api = workApiSelector(state);

		const worksList = workListSelector(state);
		const work = worksList.find((work) => work.worksId === workId);
		if (work !== undefined) {
			dispatch(worksSlice.actions.setCurrentShowingWork(work));
			return;
		}

		try {
			dispatch(worksSlice.actions.setCurrentShowingWork(undefined));
			const getWorkResult = await api.getWork({
				workId: workId,
			});
			const workToStore = {
				...getWorkResult,
				createdAt: getWorkResult.createdAt?.getTime(),
				affectDate: getWorkResult.affectDate?.getTime(),
			};
			dispatch(worksSlice.actions.setWorkList([workToStore]));
			dispatch(worksSlice.actions.setCurrentShowingWork(workToStore));
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

export const { setIsLoading, setIsEditing } = worksSlice.actions;

export default worksSlice.reducer;
