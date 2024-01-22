import { createAsyncThunk, createSlice } from "@reduxjs/toolkit";

import { ResponseError, type WorkGroup } from "../../oas";
import { API_RES_HEADER_X_TOTAL_COUNT } from "../../utils/Constants";
import { workGroupApiSelector } from "../selectors/apiSelector";

import type { DateToNumberObjectType } from "../../utils/DateToNumberType";
import type { RootState } from "../store";
import type { PayloadAction } from "@reduxjs/toolkit";

export interface WorkGroupsState {
	workGroupList: DateToNumberObjectType<WorkGroup>[];

	isLoading: boolean;

	currentPageFrom1: number;
	perPage: number;
	totalItemsCount: number;
	topId: string | undefined;

	isEditing: boolean;
	editErrorMessage: string | undefined;
	editTargetWorkGroupId: string | undefined;
}

const initialState: WorkGroupsState = {
	workGroupList: [],

	isLoading: false,

	currentPageFrom1: 1,
	perPage: 5,
	totalItemsCount: 0,
	topId: undefined,

	isEditing: false,
	editErrorMessage: undefined,
	editTargetWorkGroupId: undefined,
};

export const workGroupsSlice = createSlice({
	name: "workGroups",
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
			state.editTargetWorkGroupId = action.payload.targetId;
		},
		setTotalItemsCount: (state, action: PayloadAction<number>) => {
			state.totalItemsCount = action.payload;
		},
	},
	extraReducers: (builder) => {
		builder
			.addCase(reloadWorkGroups.pending, (state) => {
				state.isLoading = true;
			})
			.addCase(reloadWorkGroups.rejected, (state) => {
				state.isLoading = false;
			})
			.addCase(
				reloadWorkGroups.fulfilled,
				(state, action: PayloadAction<DateToNumberObjectType<WorkGroup>[]>) => {
					state.isLoading = false;
					state.workGroupList = action.payload;
				}
			);

		builder
			.addCase(createWorkGroup.pending, (state) => {
				state.editErrorMessage = undefined;
			})
			.addCase(createWorkGroup.rejected, (state, { payload, error }) => {
				console.log("createWorkGroup.rejected", payload, error);
				if (typeof payload === "string") {
					state.editErrorMessage = payload;
				} else {
					state.editErrorMessage = "Unknown error";
				}
			})
			.addCase(createWorkGroup.fulfilled, (state) => {
				state.isEditing = false;
				state.editTargetWorkGroupId = undefined;
			});
	},
});

export type ReloadWorkGroupsPayloadType = {
	topId?: string;
	currentPageFrom1: number;
	perPage: number;
};
export const reloadWorkGroups = createAsyncThunk<
	DateToNumberObjectType<WorkGroup>[],
	ReloadWorkGroupsPayloadType | undefined,
	{ state: RootState }
>("workGroups/reloadWorkGroups", async (payload, { dispatch, getState }) => {
	const state = getState();
	const workGroupsState = payload ?? state.workGroups;
	const api = workGroupApiSelector(state);

	const resultRaw = await api.getWorkGroupListRaw({
		top: workGroupsState.topId ?? state.workGroups.topId,
		p: workGroupsState.currentPageFrom1,
		limit: workGroupsState.perPage,
	});

	const totalCountStr = resultRaw.raw.headers.get(API_RES_HEADER_X_TOTAL_COUNT);
	const totalCount = totalCountStr ? Number(totalCountStr) : undefined;
	dispatch(workGroupsSlice.actions.setTotalItemsCount(totalCount ?? 0));

	const result = await resultRaw.value();
	return result.map((workGroup) => ({
		...workGroup,
		createdAt: workGroup.createdAt?.getTime(),
	}));
});

export const createWorkGroup = createAsyncThunk<
	void,
	DateToNumberObjectType<WorkGroup>,
	{ state: RootState }
>(
	"workGroups/createWorkGroup",
	async (
		payload,
		{ dispatch, getState, rejectWithValue, fulfillWithValue }
	) => {
		const state = getState();
		const api = workGroupApiSelector(state);

		try {
			const resultRaw = await api.createWorkGroupRaw({
				workGroup: {
					...payload,
					createdAt: payload.createdAt
						? new Date(payload.createdAt)
						: undefined,
				},
			});
			const result = await resultRaw.value();

			await dispatch(
				reloadWorkGroups({
					topId: result.workGroupsId,
					currentPageFrom1: 1,
					perPage: state.workGroups.perPage,
				})
			);
		} catch (e) {
			if (e instanceof ResponseError) {
				const errorObj = await e.response.json();
				console.log("createWorkGroup errorObj", errorObj);
				return rejectWithValue(errorObj.message ?? e.message);
			}
			console.log("createWorkGroup error General", e);
			return rejectWithValue("Unknown error");
		}

		return fulfillWithValue(undefined);
	}
);

export const { setIsLoading, setIsEditing } = workGroupsSlice.actions;

export default workGroupsSlice.reducer;
