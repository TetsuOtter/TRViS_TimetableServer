import { PaletteMode } from "@mui/material";
import { PayloadAction, createSlice } from "@reduxjs/toolkit";

export interface SystemState {
	themeMode: PaletteMode | undefined;
}

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
