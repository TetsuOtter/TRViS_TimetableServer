import { PaletteMode } from "@mui/material";
import { AppSelector } from "../store";

export const paletteModeSelector: AppSelector<PaletteMode | undefined> = (
	state
) => state.system.themeMode;
