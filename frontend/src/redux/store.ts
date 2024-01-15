import { configureStore } from "@reduxjs/toolkit";

import authInfoReducer from "./slices/authInfoSlice";
import systemReducer from "./slices/systemSlice";

export const store = configureStore({
	reducer: {
		authInfo: authInfoReducer,
		system: systemReducer,
	},
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
export type AppSelector<T> = (state: RootState) => T;
