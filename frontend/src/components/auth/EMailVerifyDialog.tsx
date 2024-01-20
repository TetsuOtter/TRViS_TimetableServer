import { useCallback } from "react";

import { Box, Button, Dialog, Paper, Typography } from "@mui/material";
import { useTranslation } from "react-i18next";

import { useAppDispatch, useAppSelector } from "../../redux/hooks";
import {
	isEMailVerifyDialogForNewUserSelector,
	isEMailVerifyDialogOpenSelector,
} from "../../redux/selectors/authInfoSelector";
import { closeEMailVerifyDialog } from "../../redux/slices/authInfoSlice";

const EMailVerifyDialog = () => {
	const { t } = useTranslation();
	const dispatch = useAppDispatch();

	const isEmailVerifyDialogOpen = useAppSelector(
		isEMailVerifyDialogOpenSelector
	);
	const isEmailVerifyDialogForNewUser = useAppSelector(
		isEMailVerifyDialogForNewUserSelector
	);

	const handleCloseEmailVerifyDialog = useCallback(() => {
		dispatch(closeEMailVerifyDialog());
	}, [dispatch]);

	return (
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
	);
};

export default EMailVerifyDialog;
