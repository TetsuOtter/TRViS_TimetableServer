import { createSlice } from "@reduxjs/toolkit";

import type { PayloadAction } from "@reduxjs/toolkit";

export interface AuthInfoState {
	isSignInUpDialogOpen: boolean;
	userId: string;
}

const initialState: AuthInfoState = {
	isSignInUpDialogOpen: false,
	userId: "",
};

export const authInfoSlice = createSlice({
	name: "authInfo",
	initialState: initialState,
	reducers: {
		setUserId: (state, action: PayloadAction<string>) => {
			state.userId = action.payload;
		},
		setSignInUpDialogOpen: (state, action: PayloadAction<boolean>) => {
			state.isSignInUpDialogOpen = action.payload;
		},
	},
});

export const { setUserId, setSignInUpDialogOpen } = authInfoSlice.actions;

export default authInfoSlice.reducer;
