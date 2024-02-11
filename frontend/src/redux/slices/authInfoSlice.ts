import { createAsyncThunk, createSlice } from "@reduxjs/toolkit";
import {
	createUserWithEmailAndPassword,
	sendEmailVerification,
	sendPasswordResetEmail,
	signInWithEmailAndPassword,
	signOut,
} from "firebase/auth";
import { t } from "i18next";

import { auth } from "../../firebase/configure";
import { getAuthErrorMessage } from "../../firebase/getAuthErrorMessage";

import { openMessageDialog } from "./messageDialogSlice";

import type { RootState } from "../store";
import type { Draft, PayloadAction, SerializedError } from "@reduxjs/toolkit";

export const ACTION_STATES = {
	INITIAL: 0,
	PENDING: 1,
	REJECTED: 2,
	FULFILLED: 3,
} as const;
export type ActionStateType =
	(typeof ACTION_STATES)[keyof typeof ACTION_STATES];

export type AuthInfoState = {
	isSignInUpDialogOpen: boolean;
	isEMailVerifyDialogOpen: boolean;
	isEMailVerifyDialogForNewUser: boolean;
	isPasswordResetMailSentDialogOpen: boolean;

	isAccountSettingDialogOpen: boolean;

	copyUserIdToClipboardState: ActionStateType;

	userId: string;
	email: string;
	jwt: string | undefined;

	isProcessing: boolean;
	errorMessage?: string;
	isEMailVerified?: boolean;
};

const initialState: AuthInfoState = {
	isSignInUpDialogOpen: false,
	isEMailVerifyDialogOpen: false,
	isEMailVerifyDialogForNewUser: false,
	isPasswordResetMailSentDialogOpen: false,

	isAccountSettingDialogOpen: false,

	copyUserIdToClipboardState: ACTION_STATES.INITIAL,

	userId: auth.currentUser?.uid ?? "",
	email: auth.currentUser?.email ?? "",
	jwt: undefined,

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
	state.email = action.payload.email;
	state.jwt = action.payload.jwt;

	state.isEMailVerified = action.payload.isEMailVerified;
	state.isSignInUpDialogOpen = false;
	if (action.payload.isEMailVerifyDialogOpen === true) {
		state.isEMailVerifyDialogOpen = true;
		state.isEMailVerifyDialogForNewUser = true;
	}
	state.isProcessing = false;
}
type OnAuthFulfilledPayload = {
	uid: string;
	email: string;
	jwt: string;

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

		setAccountSettingDialogOpen: (state, action: PayloadAction<boolean>) => {
			state.isAccountSettingDialogOpen = action.payload;
		},

		setIsProcessing: (state, action: PayloadAction<boolean>) => {
			state.isProcessing = action.payload;
		},

		setCopyUserIdToClipboardState: (
			state,
			action: PayloadAction<ActionStateType>
		) => {
			state.copyUserIdToClipboardState = action.payload;
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
			.addCase(signOutThunk.pending, (state) => {
				state.isProcessing = true;
				console.log("signOutThunk.pending");
			})
			.addCase(signOutThunk.rejected, (state, action) => {
				console.log("signOutThunk.rejected", action.error);
				state.isProcessing = false;
			})
			.addCase(signOutThunk.fulfilled, (state) => {
				console.log("signOutThunk.fulfilled");
				state.userId = "";
				state.email = "";
				state.jwt = undefined;
				state.isEMailVerified = false;
				state.isAccountSettingDialogOpen = false;
				state.isProcessing = false;
			});

		builder
			.addCase(sendPasswordResetMailThunk.pending, (state) => {
				console.log("sendPasswordResetMailThunk.pending");
				state.isProcessing = true;
			})
			.addCase(sendPasswordResetMailThunk.rejected, (state, action) => {
				console.log("sendPasswordResetMailThunk.rejected", action.error);
				state.errorMessage = getAuthErrorMessage(action.error);
				state.isProcessing = false;
			})
			.addCase(sendPasswordResetMailThunk.fulfilled, (state, action) => {
				console.log("sendPasswordResetMailThunk.fulfilled", action.payload);
				state.isPasswordResetMailSentDialogOpen = true;
				state.isProcessing = false;
			});

		builder
			.addCase(reloadUserThunk.pending, (state) => {
				console.log("reloadUserThunk.pending");
				state.isProcessing = true;
			})
			.addCase(reloadUserThunk.rejected, (state, action) => {
				console.log("reloadUserThunk.rejected", action.error);
				state.isProcessing = false;
			})
			.addCase(reloadUserThunk.fulfilled, (state, action) => {
				console.log("reloadUserThunk.fulfilled", action.payload);
				state.isProcessing = false;
				state.email = action.payload.email;
				state.isEMailVerified = action.payload.isEMailVerified;
				state.jwt = action.payload.jwt;
			});

		builder
			.addCase(copyUserIdToClipboardThunk.pending, (state) => {
				console.log("copyUserIdToClipboardThunk.pending");
				state.copyUserIdToClipboardState = ACTION_STATES.PENDING;
			})
			.addCase(copyUserIdToClipboardThunk.rejected, (state) => {
				console.log("copyUserIdToClipboardThunk.rejected");
				state.copyUserIdToClipboardState = ACTION_STATES.REJECTED;
			})
			.addCase(copyUserIdToClipboardThunk.fulfilled, (state) => {
				console.log("copyUserIdToClipboardThunk.fulfilled");
				state.copyUserIdToClipboardState = ACTION_STATES.INITIAL;
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
			email: result.user.email ?? "",
			jwt: await result.user.getIdToken(),

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
			email: result.user.email ?? "",
			jwt: await result.user.getIdToken(),

			isEMailVerified: result.user.emailVerified,
		};
		return retVal;
	}
);
export const signOutThunk = createAsyncThunk(
	"authInfo/signOut",
	async (_: void, { dispatch }) => {
		console.log("signOutThunk");
		try {
			await signOut(auth);
		} catch (error) {
			console.log("signOutThunk failed", error);
			if (error instanceof Error) {
				dispatch(
					openMessageDialog({
						title: t("Error"),
						message: getAuthErrorMessage(error),
					})
				);
			}
			throw error;
		}
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

export const reloadUserThunk = createAsyncThunk(
	"authInfo/reloadUser",
	async (_, { dispatch }) => {
		try {
			await auth.currentUser?.reload();
			return {
				email: auth.currentUser?.email ?? "",
				jwt: await auth.currentUser?.getIdToken(),
				isEMailVerified: auth.currentUser?.emailVerified ?? false,
			};
		} catch (error) {
			if (error instanceof Error) {
				dispatch(
					openMessageDialog({
						title: t("Error"),
						message: getAuthErrorMessage(error),
					})
				);
			}
			throw error;
		}
	}
);

export const copyUserIdToClipboardThunk = createAsyncThunk<
	void,
	void,
	{ state: RootState }
>("authInfo/copyUserIdToClipboard", async (_, { dispatch, getState }) => {
	const userId = getState().authInfo.userId;
	console.log("copyUserIdToClipboardThunk", userId);
	try {
		await new Promise((resolve) => {
			setTimeout(resolve, 250);
		});
		await navigator.clipboard.writeText(userId);
		dispatch(setCopyUserIdToClipboardState(ACTION_STATES.FULFILLED));
	} catch (error) {
		console.log("copyUserIdToClipboardThunk failed", error);
		dispatch(setCopyUserIdToClipboardState(ACTION_STATES.REJECTED));
	} finally {
		await new Promise((resolve) => {
			setTimeout(resolve, 3000);
		});
	}
});

export const {
	setUserId,
	setSignInUpDialogOpen,
	closeEMailVerifyDialog,
	setAccountSettingDialogOpen,
	setCopyUserIdToClipboardState,
} = authInfoSlice.actions;

export default authInfoSlice.reducer;
