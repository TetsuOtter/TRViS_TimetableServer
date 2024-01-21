import { memo, useCallback } from "react";

import { AccountCircle, Brightness4, Brightness7 } from "@mui/icons-material";
import {
	AppBar,
	Badge,
	Box,
	Button,
	IconButton,
	MenuItem,
	Select,
	SvgIcon,
	Toolbar,
	Typography,
} from "@mui/material";
import { useTranslation } from "react-i18next";

// eslint-plugin-importがクエリパラメータに対応していないため
// eslint-disable-next-line import/no-unresolved
import TRViS_AppIcon2 from "../assets/TRViS_AppIcon2.svg?react";
import { auth } from "../firebase/configure";
import { useAppThemeMode } from "../hooks/appThemeModeHook";
import { LANGUAGE_NAMES } from "../i18n";
import { useAppDispatch, useAppSelector } from "../redux/hooks";
import {
	isEMailVerifiedSelector,
	isLoggedInSelector,
} from "../redux/selectors/authInfoSelector";
import {
	setAccountSettingDialogOpen,
	setSignInUpDialogOpen,
} from "../redux/slices/authInfoSlice";
import { setAppThemeMode } from "../redux/slices/systemSlice";

import AccountSettingDialog from "./auth/AccountSettingDialog";
import EMailVerifyDialog from "./auth/EMailVerifyDialog";
import SignInUpDialog from "./auth/SignInUpDialog";

import type { I18N_LANGUAGE_TYPE } from "../i18n";
import type { SelectChangeEvent } from "@mui/material";

const MyAppBar = () => {
	const { t, i18n } = useTranslation();
	const dispatch = useAppDispatch();
	const isLoggedIn = useAppSelector(isLoggedInSelector);
	const isEmailVerified = useAppSelector(isEMailVerifiedSelector);
	const appThemeMode = useAppThemeMode();

	const handleAppThemeModeChange = useCallback(() => {
		dispatch(setAppThemeMode(appThemeMode === "dark" ? "light" : "dark"));
	}, [appThemeMode, dispatch]);
	const changeLanguage = useCallback(
		(event: SelectChangeEvent<I18N_LANGUAGE_TYPE>) => {
			i18n.changeLanguage(event.target.value);
			auth.languageCode = event.target.value;
		},
		[i18n]
	);
	const handleOpenSignInUpForm = useCallback(() => {
		dispatch(setSignInUpDialogOpen(true));
	}, [dispatch]);
	const handleOpenAccountSettingDialog = useCallback(() => {
		dispatch(setAccountSettingDialogOpen(true));
	}, [dispatch]);

	const currentLanguage = i18n.language as I18N_LANGUAGE_TYPE;

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
					<Select
						value={currentLanguage}
						size="small"
						onChange={changeLanguage}>
						{Object.keys(LANGUAGE_NAMES).map((languageKey) => (
							<MenuItem
								key={languageKey}
								value={languageKey}>
								{LANGUAGE_NAMES[languageKey as I18N_LANGUAGE_TYPE]}
							</MenuItem>
						))}
					</Select>
					<IconButton
						sx={{ mx: 1 }}
						onClick={handleAppThemeModeChange}
						color="inherit">
						{appThemeMode === "dark" ? <Brightness7 /> : <Brightness4 />}
					</IconButton>
					{isLoggedIn ? (
						<IconButton
							color="inherit"
							onClick={handleOpenAccountSettingDialog}>
							<Badge
								color="warning"
								variant="dot"
								overlap="circular"
								invisible={isEmailVerified}>
								<AccountCircle />
							</Badge>
						</IconButton>
					) : (
						<Button
							color="inherit"
							onClick={handleOpenSignInUpForm}>
							{t("Sign In/Up")}
						</Button>
					)}
				</Toolbar>
			</AppBar>
			<SignInUpDialog />
			<EMailVerifyDialog />
			<AccountSettingDialog />
		</Box>
	);
};

export default memo(MyAppBar);