import { createAsyncThunk, createSlice } from "@reduxjs/toolkit";
import {
	createUserWithEmailAndPassword,
	sendEmailVerification,
	signInWithEmailAndPassword,
} from "firebase/auth";

import { auth } from "../../firebase/configure";
import { getAuthErrorMessage } from "../../firebase/getAuthErrorMessage";

import type { Draft, PayloadAction, SerializedError } from "@reduxjs/toolkit";

export interface AuthInfoState {
	isSignInUpDialogOpen: boolean;
	isEMailVerifyDialogOpen: boolean;
	isEMailVerifyDialogForNewUser: boolean;

	userId: string;
	isProcessing: boolean;
	errorMessage?: string;
	isEMailVerified?: boolean;
}

const initialState: AuthInfoState = {
	isSignInUpDialogOpen: false,
	isEMailVerifyDialogOpen: false,
	isEMailVerifyDialogForNewUser: false,
	userId: auth.currentUser?.uid ?? "",
	isProcessing: false,
	errorMessage: undefined,
	isEMailVerified: auth.currentUser?.emailVerified,
};

function onAuthPending(state: Draft<AuthInfoState>) {
	console.log("onAuthPending");
	state.isProcessing = true;
	state.errorMessage = "";
}
function onAuthRejected(
	state: Draft<AuthInfoState>,
	action: { error: SerializedError }
) {
	console.log("onAuthRejected", action.error);
	state.isProcessing = false;
	state.errorMessage = getAuthErrorMessage(action.error);
}
function onAuthFulfilled(
	state: Draft<AuthInfoState>,
	action: PayloadAction<OnAuthFulfilledPayload>
) {
	console.log("onAuthFulfilled", action.payload);
	state.userId = action.payload.uid;
	state.isEMailVerified = action.payload.isEMailVerified;
	state.isSignInUpDialogOpen = false;
	if (action.payload.isEMailVerifyDialogOpen) {
		state.isEMailVerifyDialogOpen = true;
		state.isEMailVerifyDialogForNewUser = true;
	}
	state.isProcessing = false;
}
type OnAuthFulfilledPayload = {
	uid: string;
	isEMailVerified: boolean;
	isEMailVerifyDialogOpen?: boolean;
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
		closeEMailVerifyDialog: (state) => {
			state.isEMailVerifyDialogOpen = false;
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
			isEMailVerified: result.user.emailVerified,
		};
		if (!retVal.isEMailVerified) {
			console.log("SignUp -> sendEmailVerification", window.location.href);
			await sendEmailVerification(result.user, {
				url: window.location.href,
			});
			retVal.isEMailVerifyDialogOpen = true;
		}
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
			isEMailVerified: result.user.emailVerified,
		};
		return retVal;
	}
);

export const { setUserId, setSignInUpDialogOpen, closeEMailVerifyDialog } =
	authInfoSlice.actions;

export default authInfoSlice.reducer;
