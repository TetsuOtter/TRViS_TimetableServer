import { useMediaQuery } from "@mui/material";

import { useAppSelector } from "../redux/hooks";
import { paletteModeSelector } from "../redux/selectors/systemSelector";

export const useAppThemeMode = () => {
	const prefersDarkMode = useMediaQuery("(prefers-color-scheme: dark)");
	const paletteMode = useAppSelector(paletteModeSelector);

	return paletteMode ?? (prefersDarkMode ? "dark" : "light");
};
