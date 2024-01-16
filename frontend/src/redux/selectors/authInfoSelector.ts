import type { AppSelector } from "../store";

export const userIdSelector: AppSelector<string> = (state) =>
	state.authInfo.userId;

export const isLoggedInSelector: AppSelector<boolean> = (state) =>
	userIdSelector(state) !== "";

export const isSignInUpDialogOpenSelector: AppSelector<boolean> = (state) =>
	state.authInfo.isSignInUpDialogOpen;

export const tmpEmailSelector: AppSelector<string> = (state) =>
	state.authInfo.tmpEmail;

export const tmpPasswordSelector: AppSelector<string> = (state) =>
	state.authInfo.tmpPassword;
