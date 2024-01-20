import { createAsyncThunk, createSlice } from "@reduxjs/toolkit";
import {
	createUserWithEmailAndPassword,
	signInWithEmailAndPassword,
} from "firebase/auth";

import { auth } from "../../firebase/configure";
import { getAuthErrorMessage } from "../../firebase/getAuthErrorMessage";

import type { Draft, PayloadAction, SerializedError } from "@reduxjs/toolkit";

export interface AuthInfoState {
	isSignInUpDialogOpen: boolean;
	userId: string;
	isProcessing: boolean;
	errorMessage?: string;
}

const initialState: AuthInfoState = {
	isSignInUpDialogOpen: false,
	userId: "",
	isProcessing: false,
	errorMessage: undefined,
};

function onAuthPending(state: Draft<AuthInfoState>) {
	state.isProcessing = true;
	state.errorMessage = "";
}
function onAuthRejected(
	state: Draft<AuthInfoState>,
	action: { error: SerializedError }
) {
	state.isProcessing = false;
	state.errorMessage = getAuthErrorMessage(action.error);
}
function onAuthFulfilled(
	state: Draft<AuthInfoState>,
	action: PayloadAction<OnAuthFulfilledPayload>
) {
	state.userId = action.payload.uid;
	state.isSignInUpDialogOpen = false;
	state.isProcessing = false;
}
type OnAuthFulfilledPayload = {
	uid: string;
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
			state.errorMessage = undefined;
		},
	},
	extraReducers: (builder) => {
		builder
			.addCase(createAccountWithEmailAndPasswordThunk.pending, onAuthPending)
			.addCase(createAccountWithEmailAndPasswordThunk.rejected, onAuthRejected)
			.addCase(
				createAccountWithEmailAndPasswordThunk.fulfilled,
				onAuthFulfilled
			);
		builder
			.addCase(signInWithEmailAndPasswordThunk.pending, onAuthPending)
			.addCase(signInWithEmailAndPasswordThunk.rejected, onAuthRejected)
			.addCase(signInWithEmailAndPasswordThunk.fulfilled, onAuthFulfilled);
	},
});

export const createAccountWithEmailAndPasswordThunk = createAsyncThunk(
	"authInfo/createAccountWithEmailAndPassword",
	async (payload: { email: string; password: string }) => {
		const { email, password } = payload;
		console.log("createAccountWithEmailAndPasswordThunk", email, password);
		const result = await createUserWithEmailAndPassword(auth, email, password);
		const retVal: OnAuthFulfilledPayload = {
			uid: result.user.uid,
		};
		return retVal;
	}
);
export const signInWithEmailAndPasswordThunk = createAsyncThunk(
	"authInfo/signInWithEmailAndPassword",
	async (payload: { email: string; password: string }) => {
		const { email, password } = payload;
		console.log("signInWithEmailAndPasswordThunk", email, password);
		const result = await signInWithEmailAndPassword(auth, email, password);
		const retVal: OnAuthFulfilledPayload = {
			uid: result.user.uid,
		};
		return retVal;
	}
);

export const { setUserId, setSignInUpDialogOpen } = authInfoSlice.actions;

export default authInfoSlice.reducer;
