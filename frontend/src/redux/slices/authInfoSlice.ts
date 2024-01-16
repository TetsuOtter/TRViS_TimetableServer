import { createSlice } from "@reduxjs/toolkit";

import type { PayloadAction } from "@reduxjs/toolkit";

export interface AuthInfoState {
	isSignInUpDialogOpen: boolean;
	userId: string;
	tmpEmail: string;
	tmpPassword: string;
}

const initialState: AuthInfoState = {
	isSignInUpDialogOpen: false,
	userId: "",
	tmpEmail: "",
	tmpPassword: "",
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
		setTmpEmail: (state, action: PayloadAction<string>) => {
			state.tmpEmail = action.payload;
		},
		setTmpPassword: (state, action: PayloadAction<string>) => {
			state.tmpPassword = action.payload;
		},
	},
});

export const { setUserId, setSignInUpDialogOpen, setTmpEmail, setTmpPassword } =
	authInfoSlice.actions;

export default authInfoSlice.reducer;
