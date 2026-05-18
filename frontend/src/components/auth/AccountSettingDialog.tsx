import { useCallback } from "react";

import { CheckCircle, ContentCopy, Error, Refresh } from "@mui/icons-material";
import {
	Box,
	Button,
	Chip,
	CircularProgress,
	Dialog,
	Divider,
	IconButton,
	InputAdornment,
	OutlinedInput,
	Paper,
	Typography,
} from "@mui/material";
import { useTranslation } from "react-i18next";

import { useAppDispatch, useAppSelector } from "../../redux/hooks";
import {
	copyUserIdToClipboardStateSelector,
	emailSelector,
	isAccountSettingDialogOpenSelector,
	isEMailVerifiedSelector,
	isProcessingSelector,
	userIdSelector,
} from "../../redux/selectors/authInfoSelector";
import {
	ACTION_STATES,
	copyUserIdToClipboardThunk,
	reloadUserThunk,
	setAccountSettingDialogOpen,
	signOutThunk,
} from "../../redux/slices/authInfoSlice";

const AccountSettingDialog = () => {
	const { t } = useTranslation();
	const dispatch = useAppDispatch();

	const isOpen = useAppSelector(isAccountSettingDialogOpenSelector);

	const userId = useAppSelector(userIdSelector);
	const email = useAppSelector(emailSelector);
	const isEmailVerified = useAppSelector(isEMailVerifiedSelector);
	const isProcessing = useAppSelector(isProcessingSelector);

	const userIdCopyState = useAppSelector(copyUserIdToClipboardStateSelector);

	const handleSignOut = useCallback(() => {
		dispatch(signOutThunk());
	}, [dispatch]);
	const handleClose = useCallback(() => {
		dispatch(setAccountSettingDialogOpen(false));
	}, [dispatch]);
	const handleReloadVerified = useCallback(() => {
		dispatch(reloadUserThunk());
	}, [dispatch]);

	const copyUserId = useCallback(() => {
		dispatch(copyUserIdToClipboardThunk());
	}, [dispatch]);

	return (
		<Dialog
			open={isOpen}
			onClose={handleClose}>
			<Paper sx={{ p: "1.5em" }}>
				<Typography variant="h5">{t("Account Setting")}</Typography>

				<Box
					sx={{
						display: "flex",
						alignItems: "center",
						justifyContent: "space-between",
						mt: "1em",
					}}>
					<Typography>{t("User ID")}</Typography>
					<OutlinedInput
						size="small"
						disabled
						value={userId}
						type="text"
						sx={{
							fontFamily: "monospace",
							minWidth: "19em",
							ml: "1em",
						}}
						endAdornment={
							<InputAdornment position="end">
								<IconButton
									edge="end"
									disabled={userIdCopyState !== ACTION_STATES.INITIAL}
									onClick={copyUserId}>
									{userIdCopyState === ACTION_STATES.INITIAL ? (
										<ContentCopy />
									) : userIdCopyState === ACTION_STATES.PENDING ? (
										<CircularProgress size="1em" />
									) : userIdCopyState === ACTION_STATES.FULFILLED ? (
										<CheckCircle color="success" />
									) : (
										<Error color="error" />
									)}
								</IconButton>
							</InputAdornment>
						}
					/>
				</Box>

				<Box
					sx={{
						display: "flex",
						alignItems: "center",
						justifyContent: "space-between",
						mt: "1em",
					}}>
					<Typography>{t("e-mail")}</Typography>
					<Typography>{email}</Typography>
					<Chip
						size="small"
						disabled={isProcessing}
						color={isEmailVerified === true ? "success" : "error"}
						variant={isEmailVerified === true ? "outlined" : "filled"}
						label={isEmailVerified === true ? t("Verified") : t("Unverified")}
						onDelete={
							isEmailVerified === true ? undefined : handleReloadVerified
						}
						deleteIcon={<Refresh />}
					/>
				</Box>

				<Divider sx={{ mt: "1em" }} />

				<Box sx={{ display: "flex", justifyContent: "flex-end" }}>
					<Button
						onClick={handleSignOut}
						disabled={isProcessing}
						variant="outlined"
						sx={{ mt: "1em" }}>
						{t("Sign Out")}
					</Button>
				</Box>

				<Box sx={{ display: "flex", justifyContent: "flex-end" }}>
					<Button
						onClick={handleClose}
						variant="contained"
						sx={{ mt: "1em" }}>
						{t("Close")}
					</Button>
				</Box>
			</Paper>
		</Dialog>
	);
};

export default AccountSettingDialog;
