import { createSlice } from "@reduxjs/toolkit";

import type { PayloadAction } from "@reduxjs/toolkit";

export type SystemState = {
	isMessageDialogOpen: boolean;
	messageTitle?: string;
	messageBody?: string;
};

const initialState: SystemState = {
	isMessageDialogOpen: false,
	messageTitle: undefined,
	messageBody: undefined,
};

export type ErrorDialogOpenPayload = {
	title?: string;
	message: string;
};

export const messageDialogSlice = createSlice({
	name: "messageDialog",
	initialState: initialState,
	reducers: {
		openMessageDialog: (
			state,
			action: PayloadAction<ErrorDialogOpenPayload>
		) => {
			state.isMessageDialogOpen = true;
			state.messageTitle = action.payload.title;
			state.messageBody = action.payload.message;
		},
		closeMessageDialog: (state) => {
			state.isMessageDialogOpen = false;
		},
	},
});

export const { openMessageDialog, closeMessageDialog } =
	messageDialogSlice.actions;

export default messageDialogSlice.reducer;
