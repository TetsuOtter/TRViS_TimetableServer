import { memo, useCallback } from "react";

import { Brightness4, Brightness7 } from "@mui/icons-material";
import {
	AppBar,
	Box,
	Button,
	IconButton,
	SvgIcon,
	Toolbar,
	Typography,
} from "@mui/material";

// eslint-plugin-importがクエリパラメータに対応していないため
// eslint-disable-next-line import/no-unresolved
import TRViS_AppIcon2 from "../assets/TRViS_AppIcon2.svg?react";
import { useAppThemeMode } from "../hooks/appThemeModeHook";
import { useAppDispatch, useAppSelector } from "../redux/hooks";
import { isLoggedInSelector } from "../redux/selectors/authInfoSelector";
import { setAppThemeMode } from "../redux/slices/systemSlice";

const MyAppBar = () => {
	const dispatch = useAppDispatch();
	const isLoggedIn = useAppSelector(isLoggedInSelector);
	const appThemeMode = useAppThemeMode();

	const handleAppThemeModeChange = useCallback(() => {
		dispatch(setAppThemeMode(appThemeMode === "dark" ? "light" : "dark"));
	}, [appThemeMode, dispatch]);

	return (
		<Box sx={{ flexGrow: 1 }}>
			<AppBar position="sticky">
				<Toolbar>
					<SvgIcon
						fontSize="large"
						sx={{
							mr: "0.5em",
						}}
						component={TRViS_AppIcon2}
						inheritViewBox
					/>
					<Typography
						variant="h5"
						component="div"
						sx={{ flexGrow: 1 }}>
						TRViS Data Editor
					</Typography>
					<IconButton
						sx={{ mx: 1 }}
						onClick={handleAppThemeModeChange}
						color="inherit">
						{appThemeMode === "dark" ? <Brightness7 /> : <Brightness4 />}
					</IconButton>
					{isLoggedIn ? <>abc</> : <Button color="inherit">Login</Button>}
				</Toolbar>
			</AppBar>
		</Box>
	);
};

export default memo(MyAppBar);
