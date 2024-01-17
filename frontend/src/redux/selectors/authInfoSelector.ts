import type { AppSelector } from "../store";

export const userIdSelector: AppSelector<string> = (state) =>
	state.authInfo.userId;

export const isLoggedInSelector: AppSelector<boolean> = (state) =>
	userIdSelector(state) !== "";

export const isSignInUpDialogOpenSelector: AppSelector<boolean> = (state) =>
	state.authInfo.isSignInUpDialogOpen;

export const isProcessingSelector: AppSelector<boolean> = (state) =>
	state.authInfo.isProcessing;

export const errorMessageSelector: AppSelector<string | undefined> = (state) =>
	state.authInfo.errorMessage;
