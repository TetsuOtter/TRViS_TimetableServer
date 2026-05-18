import type { AppSelector } from "../store";
import type { PaletteMode } from "@mui/material";

export const paletteModeSelector: AppSelector<PaletteMode | undefined> = (
	state
) => state.system.themeMode;
