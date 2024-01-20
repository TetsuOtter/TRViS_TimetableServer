import { memo, useCallback } from "react";

import { Brightness4, Brightness7 } from "@mui/icons-material";
import {
	AppBar,
	Box,
	Button,
	Dialog,
	IconButton,
	MenuItem,
	Paper,
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
	isEMailVerifyDialogForNewUserSelector,
	isEMailVerifyDialogOpenSelector,
	isLoggedInSelector,
	isSignInUpDialogOpenSelector,
} from "../redux/selectors/authInfoSelector";
import {
	closeEMailVerifyDialog,
	setSignInUpDialogOpen,
} from "../redux/slices/authInfoSlice";
import { setAppThemeMode } from "../redux/slices/systemSlice";

import SignInUpForm from "./SignInUpForm";

import type { I18N_LANGUAGE_TYPE } from "../i18n";
import type { SelectChangeEvent } from "@mui/material";

const MyAppBar = () => {
	const { t, i18n } = useTranslation();
	const dispatch = useAppDispatch();
	const isLoggedIn = useAppSelector(isLoggedInSelector);
	const isSignInUpDialogOpen = useAppSelector(isSignInUpDialogOpenSelector);
	const isEmailVerifyDialogOpen = useAppSelector(
		isEMailVerifyDialogOpenSelector
	);
	const isEmailVerifyDialogForNewUser = useAppSelector(
		isEMailVerifyDialogForNewUserSelector
	);
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
	const handleCloseSignInUpForm = useCallback(() => {
		dispatch(setSignInUpDialogOpen(false));
	}, [dispatch]);
	const handleCloseEmailVerifyDialog = useCallback(() => {
		dispatch(closeEMailVerifyDialog());
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
						<>abc</>
					) : (
						<Button
							color="inherit"
							onClick={handleOpenSignInUpForm}>
							{t("Sign In")}
						</Button>
					)}
				</Toolbar>
			</AppBar>
			<Dialog
				open={isSignInUpDialogOpen}
				onClose={handleCloseSignInUpForm}>
				<SignInUpForm />
			</Dialog>
			<Dialog
				open={isEmailVerifyDialogOpen}
				onClose={handleCloseEmailVerifyDialog}>
				<Paper sx={{ p: "1.5em" }}>
					<Typography
						variant="h5"
						sx={{ m: "0.5em 0" }}>
						{isEmailVerifyDialogForNewUser
							? t("Welcome to TRViS Data Editor! 🎉")
							: t("Email verification")}
					</Typography>
					<Typography>
						{isEmailVerifyDialogForNewUser
							? t(
									"Before you can use TRViS Data Editor, you need to verify your email address."
								)
							: t("Verify link was sent to your email address.")}
					</Typography>
					<Typography>
						{t(
							"Please check your inbox and follow the instructions to verify your email address."
						)}
					</Typography>
					<Box sx={{ display: "flex", justifyContent: "center" }}>
						<Button
							variant="contained"
							sx={{ mt: "1em" }}
							onClick={handleCloseEmailVerifyDialog}
							autoFocus>
							{t("OK")}
						</Button>
					</Box>
				</Paper>
			</Dialog>
		</Box>
	);
};

export default memo(MyAppBar);
