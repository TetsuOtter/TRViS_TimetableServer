import { createAsyncThunk, createSlice } from "@reduxjs/toolkit";
import {
	createUserWithEmailAndPassword,
	sendEmailVerification,
	sendPasswordResetEmail,
	signInWithEmailAndPassword,
} from "firebase/auth";
import { t } from "i18next";

import { auth } from "../../firebase/configure";
import { getAuthErrorMessage } from "../../firebase/getAuthErrorMessage";

import { openMessageDialog } from "./messageDialogSlice";

import type { Draft, PayloadAction, SerializedError } from "@reduxjs/toolkit";

export interface AuthInfoState {
	isSignInUpDialogOpen: boolean;
	isEMailVerifyDialogOpen: boolean;
	isEMailVerifyDialogForNewUser: boolean;
	isPasswordResetMailSentDialogOpen: boolean;

	userId: string;
	isProcessing: boolean;
	errorMessage?: string;
	isEMailVerified?: boolean;
}

const initialState: AuthInfoState = {
	isSignInUpDialogOpen: false,
	isEMailVerifyDialogOpen: false,
	isEMailVerifyDialogForNewUser: false,
	isPasswordResetMailSentDialogOpen: false,

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

		builder
			.addCase(sendPasswordResetMailThunk.pending, () => {
				console.log("sendPasswordResetMailThunk.pending");
			})
			.addCase(sendPasswordResetMailThunk.rejected, (state, action) => {
				console.log("sendPasswordResetMailThunk.rejected", action.error);
				state.errorMessage = getAuthErrorMessage(action.error);
			})
			.addCase(sendPasswordResetMailThunk.fulfilled, (state, action) => {
				console.log("sendPasswordResetMailThunk.fulfilled", action.payload);
				state.isPasswordResetMailSentDialogOpen = true;
			});
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
export const sendPasswordResetMailThunk = createAsyncThunk(
	"authInfo/sendPasswordResetMail",
	async (
		payload: { email: string },
		{ dispatch, rejectWithValue, fulfillWithValue }
	) => {
		const { email } = payload;
		console.log("sendPasswordResetMail", email);
		try {
			await sendPasswordResetEmail(auth, email);
			dispatch(
				openMessageDialog({
					title: t("Password reset mail sent"),
					message: t("Please check your inbox and follow the instructions."),
				})
			);
			return fulfillWithValue(undefined);
		} catch (error) {
			console.log("sendPasswordResetMail failed", error);
			if (error instanceof Error) {
				dispatch(
					openMessageDialog({
						title: t("Error"),
						message: getAuthErrorMessage(error),
					})
				);
			}
			return rejectWithValue(error);
		}
	}
);

export const { setUserId, setSignInUpDialogOpen, closeEMailVerifyDialog } =
	authInfoSlice.actions;

export default authInfoSlice.reducer;
