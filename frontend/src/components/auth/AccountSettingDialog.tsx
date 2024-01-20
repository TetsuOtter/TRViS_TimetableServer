import { useCallback } from "react";

import { Box, Button, Dialog, Paper, Typography } from "@mui/material";
import { useTranslation } from "react-i18next";

import { useAppDispatch, useAppSelector } from "../../redux/hooks";
import {
	isAccountSettingDialogOpenSelector,
	isProcessingSelector,
} from "../../redux/selectors/authInfoSelector";
import {
	setAccountSettingDialogOpen,
	signOutThunk,
} from "../../redux/slices/authInfoSlice";

const AccountSettingDialog = () => {
	const { t } = useTranslation();
	const dispatch = useAppDispatch();

	const isOpen = useAppSelector(isAccountSettingDialogOpenSelector);
	const isProcessing = useAppSelector(isProcessingSelector);

	const handleSignOut = useCallback(() => {
		dispatch(signOutThunk());
	}, [dispatch]);
	const handleClose = useCallback(() => {
		dispatch(setAccountSettingDialogOpen(false));
	}, [dispatch]);

	return (
		<Dialog
			open={isOpen}
			onClose={handleClose}>
			<Paper sx={{ p: "1.5em" }}>
				<Typography variant="h5">{t("Account Setting")}</Typography>
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
