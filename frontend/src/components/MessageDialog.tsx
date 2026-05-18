import { useCallback } from "react";

import { Box, Button, Dialog, Paper, Typography } from "@mui/material";
import { useTranslation } from "react-i18next";

import { useAppDispatch, useAppSelector } from "../redux/hooks";
import {
	messageBodySelector,
	messageTitleSelector,
	isMessageDialogOpenSelector,
} from "../redux/selectors/messageDialogSelector";
import { closeMessageDialog } from "../redux/slices/messageDialogSlice";

const MessageDialog = () => {
	const { t } = useTranslation();
	const dispatch = useAppDispatch();

	const isOpen = useAppSelector(isMessageDialogOpenSelector);
	const messageTitle = useAppSelector(messageTitleSelector);
	const messageBody = useAppSelector(messageBodySelector);

	const handleClose = useCallback(() => {
		dispatch(closeMessageDialog());
	}, [dispatch]);

	return (
		<Dialog
			open={isOpen}
			onClose={handleClose}>
			<Paper sx={{ p: "1em" }}>
				{messageTitle && <Typography variant="h5">{messageTitle}</Typography>}
				<Typography variant="body1">{messageBody}</Typography>
				<Box sx={{ display: "flex", justifyContent: "flex-end" }}>
					<Button
						onClick={handleClose}
						variant="contained"
						sx={{ mt: "1em" }}>
						{t("OK")}
					</Button>
				</Box>
			</Paper>
		</Dialog>
	);
};

export default MessageDialog;
