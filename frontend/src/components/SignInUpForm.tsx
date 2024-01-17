import { memo, useCallback, useState } from "react";

import { Visibility, VisibilityOff } from "@mui/icons-material";
import {
	Button,
	FormControl,
	FormHelperText,
	IconButton,
	InputAdornment,
	InputLabel,
	LinearProgress,
	OutlinedInput,
	Paper,
	TextField,
	Typography,
	styled,
} from "@mui/material";
import { Controller, useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";

import { useAppDispatch, useAppSelector } from "../redux/hooks";
import {
	errorMessageSelector,
	isProcessingSelector,
} from "../redux/selectors/authInfoSelector";
import {
	createAccountWithEmailAndPasswordThunk,
	signInWithEmailAndPasswordThunk,
} from "../redux/slices/authInfoSlice";

const StyledButton = styled(Button)(() => ({
	display: "block",
	margin: "1em auto",
	marginBottom: "0",
	width: "80%",
}));

const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_MAX_LENGTH = 32;

type SignInUpFormFields = {
	email: string;
	password: string;
};

const SignInUpForm = () => {
	const { t } = useTranslation();
	const dispatch = useAppDispatch();
	const isProcessing = useAppSelector(isProcessingSelector);
	const errorMessage = useAppSelector(errorMessageSelector);
	const [isPasswordVisible, setIsPasswordVisible] = useState(false);
	const { control, handleSubmit } = useForm<SignInUpFormFields>({
		mode: "all",
	});

	const handleSignIn = useCallback(
		(v: SignInUpFormFields) => {
			console.log("handleSignIn", v);
			dispatch(signInWithEmailAndPasswordThunk(v));
		},
		[dispatch]
	);
	const handleSignUp = useCallback(
		(v: SignInUpFormFields) => {
			console.log("handleSignUp", v);
			dispatch(createAccountWithEmailAndPasswordThunk(v));
		},
		[dispatch]
	);
	const handleForgotPassword = useCallback(() => {
		console.log("handleForgotPassword");
	}, []);

	const handleShowHidePassword = useCallback(() => {
		setIsPasswordVisible((v) => !v);
	}, []);

	console.log("SignInUpForm", { isProcessing, errorMessage });

	return (
		<Paper
			component="form"
			onSubmit={handleSubmit(handleSignIn)}
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

			<Controller
				name="email"
				control={control}
				defaultValue=""
				rules={{
					required: { value: true, message: t("required") },
					pattern: {
						value: /^.+@.+\.[A-Za-z]{2,}$/i,
						message: t("invalidEmail"),
					},
				}}
				render={({ field, formState: { errors } }) => (
					<TextField
						{...field}
						disabled={isProcessing}
						label={t("Email")}
						type="email"
						autoComplete="email"
						variant="outlined"
						margin="normal"
						fullWidth
						error={errors.email?.message !== undefined}
						helperText={errors.email?.message}
					/>
				)}
			/>
			<Controller
				name="password"
				control={control}
				defaultValue=""
				rules={{
					required: { value: true, message: t("required") },
					validate: (v) => {
						const result = [];
						if (v.length < PASSWORD_MIN_LENGTH)
							result.push(t("minLength", { minLength: PASSWORD_MIN_LENGTH }));
						if (PASSWORD_MAX_LENGTH < v.length)
							result.push(t("maxLength", { maxLength: PASSWORD_MAX_LENGTH }));
						if (!/[a-z]/.test(v)) result.push(t("containsLowercase"));
						if (!/[A-Z]/.test(v)) result.push(t("containsUppercase"));
						if (!/[0-9]/.test(v)) result.push(t("containsNumber"));
						if (!/[^A-Za-z0-9]/.test(v))
							result.push(t("containsSymbolCharacter"));
						if (!/^[\x21-\x7e]+$/.test(v))
							result.push(t("containsOnlyAsciiPrintableCharactersExceptSpace"));
						return result.length === 0 ? undefined : result.join(" / ");
					},
				}}
				render={({ field, formState: { errors } }) => (
					<FormControl
						fullWidth
						variant="outlined">
						<InputLabel
							htmlFor="password"
							error={!!errors.password?.message}>
							{t("Password")}
						</InputLabel>
						<OutlinedInput
							{...field}
							id="password"
							disabled={isProcessing}
							type={isPasswordVisible ? "text" : "password"}
							error={!!errors.password?.message}
							autoComplete="current-password"
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
						<FormHelperText
							id="password-helper-text"
							error={true}>
							{errors.password?.message}
						</FormHelperText>
						<FormHelperText>
							{t(
								"{{minLength}} ~ {{maxLength}} characters contains at least 1 uppercase, 1 lowercase, 1 number, 1 symbol",
								{
									minLength: PASSWORD_MIN_LENGTH,
									maxLength: PASSWORD_MAX_LENGTH,
								}
							)}
							<br />
							{t("(only ascii printable characters except space are allowed)")}
						</FormHelperText>
					</FormControl>
				)}
			/>

			<LinearProgress
				sx={{ width: "100%", margin: "1em 0" }}
				variant={isProcessing ? "indeterminate" : "determinate"}
				value={isProcessing ? undefined : 0}
			/>

			{errorMessage && (
				<Typography
					variant="body2"
					color="error"
					sx={{ textAlign: "center" }}>
					{errorMessage}
				</Typography>
			)}

			<StyledButton
				variant="contained"
				disabled={isProcessing}
				type="submit"
				onClick={handleSubmit(handleSignIn)}>
				{t("Sign In")}
			</StyledButton>
			<StyledButton
				variant="outlined"
				disabled={isProcessing}
				onClick={handleSubmit(handleSignUp)}>
				{t("Sign Up")}
			</StyledButton>
			<StyledButton
				variant="text"
				disabled={isProcessing}
				onClick={handleForgotPassword}>
				{t("Forgot Password?")}
			</StyledButton>
		</Paper>
	);
};

export default memo(SignInUpForm);
