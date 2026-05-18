import type { AppSelector } from "../store";

export const isMessageDialogOpenSelector: AppSelector<boolean> = (state) =>
	state.messageDialog.isMessageDialogOpen;

export const messageTitleSelector: AppSelector<string | undefined> = (state) =>
	state.messageDialog.messageTitle;

export const messageBodySelector: AppSelector<string | undefined> = (state) =>
	state.messageDialog.messageBody;
