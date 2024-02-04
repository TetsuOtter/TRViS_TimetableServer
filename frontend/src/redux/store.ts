import { configureStore } from "@reduxjs/toolkit";

import authInfoReducer from "./slices/authInfoSlice";
import messageDialogReducer from "./slices/messageDialogSlice";
import systemReducer from "./slices/systemSlice";
import trainsReducer from "./slices/trainsSlice";
import workGroupsReducer from "./slices/workGroupsSlice";
import worksReducer from "./slices/worksSlice";

import type { AsyncThunk } from "@reduxjs/toolkit";

export const store = configureStore({
	reducer: {
		authInfo: authInfoReducer,
		system: systemReducer,
		messageDialog: messageDialogReducer,

		workGroups: workGroupsReducer,
		works: worksReducer,
		trains: trainsReducer,
	},
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
export type AppSelector<T> = (state: RootState) => T;
export type AppAsyncThunk<Returned, ThunkArg> = AsyncThunk<
	Returned,
	ThunkArg,
	{ state: RootState }
>;
