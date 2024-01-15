import { AppSelector } from "../store";

export const userIdSelector: AppSelector<string> = (state) =>
	state.authInfo.userId;

export const isLoggedInSelector: AppSelector<boolean> = (state) =>
	userIdSelector(state) !== "";
