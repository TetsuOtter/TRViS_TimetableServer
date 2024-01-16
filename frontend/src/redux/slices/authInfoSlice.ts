import { createSlice } from "@reduxjs/toolkit";

import type { PayloadAction } from "@reduxjs/toolkit";

export interface AuthInfoState {
	userId: string;
}

const initialState: AuthInfoState = {
	userId: "",
};

export const authInfoSlice = createSlice({
	name: "authInfo",
	initialState: initialState,
	reducers: {
		setUserId: (state, action: PayloadAction<string>) => {
			state.userId = action.payload;
		},
	},
});

export const { setUserId } = authInfoSlice.actions;

export default authInfoSlice.reducer;
