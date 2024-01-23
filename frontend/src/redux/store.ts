import { configureStore } from "@reduxjs/toolkit";

import authInfoReducer from "./slices/authInfoSlice";
import messageDialogReducer from "./slices/messageDialogSlice";
import systemReducer from "./slices/systemSlice";
import workGroupsReducer from "./slices/workGroupsSlice";
import worksReducer from "./slices/worksSlice";

export const store = configureStore({
	reducer: {
		authInfo: authInfoReducer,
		system: systemReducer,
		messageDialog: messageDialogReducer,

		workGroups: workGroupsReducer,
		works: worksReducer,
	},
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
export type AppSelector<T> = (state: RootState) => T;
