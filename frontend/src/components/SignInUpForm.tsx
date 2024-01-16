import type { ChangeEvent, ChangeEventHandler } from "react";
import { memo, useCallback, useState } from "react";

import { Visibility, VisibilityOff } from "@mui/icons-material";
import {
	Button,
	Divider,
	FormControl,
	FormHelperText,
	IconButton,
	InputAdornment,
	InputLabel,
	OutlinedInput,
	Paper,
	TextField,
	Typography,
	styled,
} from "@mui/material";
import { useTranslation } from "react-i18next";

import { useAppDispatch, useAppSelector } from "../redux/hooks";
import { tmpEmailSelector } from "../redux/selectors/authInfoSelector";
import { setTmpEmail, setTmpPassword } from "../redux/slices/authInfoSlice";

import type { TFunction } from "i18next";

const StyledButton = styled(Button)(() => ({
	display: "block",
	margin: "1em auto",
	marginBottom: "0",
	width: "80%",
}));

const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_MAX_LENGTH = 32;
const PASSWORD_VALIDATION_TYPES = {
	minLength: 0,
	maxLength: 1,
	containsUppercase: 2,
	containsLowercase: 3,
	containsNumber: 4,
	containsSymbolCharacter: 5,
	containsOnlyAsciiPrintableCharactersExceptSpace: 6,
} as const;
type PasswordValidationType =
	(typeof PASSWORD_VALIDATION_TYPES)[keyof typeof PASSWORD_VALIDATION_TYPES];
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const getPasswordValidationTypeNames = (
	t: TFunction<"translation", undefined>
): Record<PasswordValidationType, string> => ({
	[PASSWORD_VALIDATION_TYPES.minLength]: t("minLength"),
	[PASSWORD_VALIDATION_TYPES.maxLength]: t("maxLength"),
	[PASSWORD_VALIDATION_TYPES.containsUppercase]: t("containsUppercase"),
	[PASSWORD_VALIDATION_TYPES.containsLowercase]: t("containsLowercase"),
	[PASSWORD_VALIDATION_TYPES.containsNumber]: t("containsNumber"),
	[PASSWORD_VALIDATION_TYPES.containsSymbolCharacter]: t(
		"containsSymbolCharacter"
	),
	[PASSWORD_VALIDATION_TYPES.containsOnlyAsciiPrintableCharactersExceptSpace]:
		t("containsOnlyAsciiPrintableCharactersExceptSpace"),
});

const validatePassword = (
	password: string
): Record<PasswordValidationType, boolean> => ({
	[PASSWORD_VALIDATION_TYPES.minLength]: PASSWORD_MIN_LENGTH <= password.length,
	[PASSWORD_VALIDATION_TYPES.maxLength]: password.length <= PASSWORD_MAX_LENGTH,
	[PASSWORD_VALIDATION_TYPES.containsUppercase]: /[A-Z]/.test(password),
	[PASSWORD_VALIDATION_TYPES.containsLowercase]: /[a-z]/.test(password),
	[PASSWORD_VALIDATION_TYPES.containsNumber]: /[0-9]/.test(password),
	[PASSWORD_VALIDATION_TYPES.containsSymbolCharacter]: /[^A-Za-z0-9]/.test(
		password
	),
	[PASSWORD_VALIDATION_TYPES.containsOnlyAsciiPrintableCharactersExceptSpace]:
		/^[ -~]+$/.test(password),
});

const SignInUpForm = () => {
	const { t } = useTranslation();
	const dispatch = useAppDispatch();
	const [isPasswordVisible, setIsPasswordVisible] = useState(false);
	// eslint-disable-next-line @typescript-eslint/no-unused-vars
	const [passwordValidationResult, setPasswordValidationResult] = useState<
		Record<PasswordValidationType, boolean> | undefined
	>(undefined);

	const emailValue = useAppSelector(tmpEmailSelector);
	const passwordValue = useAppSelector((state) => state.authInfo.tmpPassword);

	const handleSignIn = useCallback(() => {}, []);
	const handleSignUp = useCallback(() => {}, []);
	const handleForgotPassword = useCallback(() => {}, []);

	const handleShowHidePassword = useCallback(() => {
		setIsPasswordVisible((v) => !v);
	}, []);

	const handlePasswordInputChange = useCallback(
		(event: ChangeEvent<HTMLTextAreaElement | HTMLInputElement>) => {
			setPasswordValidationResult(validatePassword(event.target.value));
			dispatch(setTmpPassword(event.target.value));
		},
		[dispatch]
	);
	const handleEmailInputChange: ChangeEventHandler<HTMLInputElement> =
		useCallback(
			(event) => {
				dispatch(setTmpEmail(event.target.value));
			},
			[dispatch]
		);

	return (
		<Paper
			sx={{
				p: "1.5em",
				maxWidth: "32em",
				alignItems: "center",
			}}>
			<Typography
				variant="h5"
				component="div">
				{t("Sign In/Up")}
			</Typography>

			<TextField
				label={t("Email")}
				type="email"
				value={emailValue}
				onChange={handleEmailInputChange}
				variant="outlined"
				margin="normal"
				fullWidth
			/>
			<FormControl
				fullWidth
				variant="outlined">
				<InputLabel htmlFor="password">{t("Password")}</InputLabel>
				<OutlinedInput
					id="password"
					type={isPasswordVisible ? "text" : "password"}
					value={passwordValue}
					onChange={handlePasswordInputChange}
					endAdornment={
						<InputAdornment position="end">
							<IconButton
								aria-label="toggle password visibility"
								onClick={handleShowHidePassword}
								onMouseDown={handleShowHidePassword}
								edge="end">
								{isPasswordVisible ? <VisibilityOff /> : <Visibility />}
							</IconButton>
						</InputAdornment>
					}
					label={t("Password")}
				/>
				<FormHelperText id="password-helper-text">
					{t(
						"8 ~ 32 characters contains at least 1 uppercase, 1 lowercase, 1 number, 1 symbol"
					)}
					<br />
					{t("(only ascii printable characters except space are allowed)")}
				</FormHelperText>
			</FormControl>

			<Divider />

			<StyledButton
				variant="contained"
				onClick={handleSignIn}>
				{t("Sign In")}
			</StyledButton>
			<StyledButton
				variant="outlined"
				onClick={handleSignUp}>
				{t("Sign Up")}
			</StyledButton>
			<StyledButton
				variant="text"
				onClick={handleForgotPassword}>
				{t("Forgot Password?")}
			</StyledButton>
		</Paper>
	);
};

export default memo(SignInUpForm);
