import { configureStore } from "@reduxjs/toolkit";

import authInfoReducer from "./slices/authInfoSlice";
import messageDialogReducer from "./slices/messageDialogSlice";
import systemReducer from "./slices/systemSlice";

export const store = configureStore({
	reducer: {
		authInfo: authInfoReducer,
		system: systemReducer,
		messageDialog: messageDialogReducer,
	},
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
export type AppSelector<T> = (state: RootState) => T;
