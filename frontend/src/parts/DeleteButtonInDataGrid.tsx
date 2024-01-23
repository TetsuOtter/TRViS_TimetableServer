import { useCallback, useState } from "react";

import { Delete } from "@mui/icons-material";
import {
	Box,
	Button,
	CircularProgress,
	ClickAwayListener,
	Divider,
	IconButton,
	Stack,
	Tooltip,
	Typography,
} from "@mui/material";
import { useTranslation } from "react-i18next";

import { useActionWithProcessing } from "../redux/actionWithProcessingHook";

import type { RootState } from "../redux/store";
import type { GridColDef, GridValidRowModel } from "@mui/x-data-grid";
import type { AsyncThunk } from "@reduxjs/toolkit";

type DeleteButtonProps<Returned, ThunkArg> = {
	disabled?: boolean;
	thunkArg: ThunkArg;
	thunk: AsyncThunk<Returned, ThunkArg, { state: RootState }>;
};

const DeleteButton = <Returned, ThunkArg>(
	props: DeleteButtonProps<Returned, ThunkArg>
) => {
	const { t } = useTranslation();
	const [dispatchDelete, isProcessing] = useActionWithProcessing(props.thunk);
	const [isTooltipOpen, setIsTooltipOpen] = useState(false);
	const [errorMessage, setErrorMessage] = useState("");

	const handleDelete = useCallback(() => {
		setErrorMessage("");
		dispatchDelete(props.thunkArg)
			.then(() => {
				setIsTooltipOpen(false);
			})
			.catch((e) => {
				if (e.message) {
					setErrorMessage(e.message);
				} else {
					console.error(e);
					setErrorMessage(t("Failed to delete the work group."));
				}
			});
	}, [dispatchDelete, props.thunkArg, t]);

	const handleCloseTooltip = useCallback(() => {
		setIsTooltipOpen(false);
	}, []);
	const handleOpenTooltip = useCallback(() => {
		setErrorMessage("");
		setIsTooltipOpen(true);
	}, []);

	return (
		<Tooltip
			open={isTooltipOpen}
			disableFocusListener
			disableHoverListener
			disableTouchListener
			arrow
			title={
				<ClickAwayListener onClickAway={handleCloseTooltip}>
					<Stack
						spacing={1}
						alignItems="center">
						<Typography variant="body1">{t("Delete this data?")}</Typography>
						<Typography
							variant="body2"
							color="error">
							{t("This operation cannot be undone.")}
						</Typography>
						<Divider sx={{ width: "100%" }} />
						<Typography
							variant="body1"
							color="error">
							{errorMessage}
						</Typography>
						<Box
							sx={{
								display: "flex",
								justifyContent: "space-around",
								width: "100%",
							}}>
							<Button
								onClick={handleCloseTooltip}
								disabled={isProcessing || !isTooltipOpen}
								sx={{ maxWidth: "60%" }}
								variant="outlined">
								{t("Cancel")}
							</Button>
							<Button
								onClick={handleDelete}
								disabled={isProcessing || !isTooltipOpen}
								startIcon={<Delete />}
								sx={{ maxWidth: "60%" }}
								variant="outlined"
								color="error">
								{t("Delete")}
							</Button>
						</Box>
					</Stack>
				</ClickAwayListener>
			}>
			<IconButton
				disabled={isProcessing || props.disabled}
				aria-label="delete"
				onClick={handleOpenTooltip}>
				{isProcessing ? <CircularProgress size={20} /> : <Delete />}
			</IconButton>
		</Tooltip>
	);
};

export default DeleteButton;

export const getDeleteButtonGridColDef = <R extends GridValidRowModel>(
	renderCell: GridColDef<R>["renderCell"]
): GridColDef<R> => {
	return {
		field: "deleteButton",
		headerName: "",
		sortable: false,
		disableColumnMenu: true,
		disableReorder: true,
		disableExport: true,
		width: 40,
		align: "center",
		editable: false,
		type: "actions",
		renderCell,
	};
};
