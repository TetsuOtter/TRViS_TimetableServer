import { createSlice } from "@reduxjs/toolkit";

import type { PaletteMode } from "@mui/material";
import type { PayloadAction } from "@reduxjs/toolkit";

export type SystemState = {
	themeMode: PaletteMode | undefined;
};

const initialState: SystemState = {
	themeMode: undefined,
};

export const systemSlice = createSlice({
	name: "system",
	initialState: initialState,
	reducers: {
		setAppThemeMode: (
			state,
			action: PayloadAction<PaletteMode | undefined>
		) => {
			state.themeMode = action.payload;
		},
	},
});

export const { setAppThemeMode } = systemSlice.actions;

export default systemSlice.reducer;
