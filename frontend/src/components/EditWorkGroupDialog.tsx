import { useCallback } from "react";

import { Send } from "@mui/icons-material";
import {
	Box,
	Button,
	Dialog,
	LinearProgress,
	Paper,
	Stack,
	TextField,
	Typography,
} from "@mui/material";
import { Controller, useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";

import { useAppDispatch, useAppSelector } from "../redux/hooks";
import {
	editErrorMessageSelector,
	editTargetWorkGroupSelector,
	isEditExistingWorkGroupSelector,
	isEditingSelector,
	isProcessingSelector,
} from "../redux/selectors/workGroupsSelector";
import { createWorkGroup, setIsEditing } from "../redux/slices/workGroupsSlice";
import {
	DESCRIPTION_MAX_LENGTH,
	DESCRIPTION_MIN_LENGTH,
	NAME_MAX_LENGTH,
	NAME_MIN_LENGTH,
} from "../utils/Constants";

import type { WorkGroup } from "../oas";
import type { DateToNumberObjectType } from "../utils/DateToNumberType";

type WorkGroupFormFields = {
	name: string;
	description: string;
};

export const EditWorkGroupDialog = () => {
	const { t } = useTranslation();
	const dispatch = useAppDispatch();

	const isOpen = useAppSelector(isEditingSelector);
	const isProcessing = useAppSelector(isProcessingSelector);
	const isEditExistingWorkGroup = useAppSelector(
		isEditExistingWorkGroupSelector
	);
	const errorMessage = useAppSelector(editErrorMessageSelector);
	const workGroup: DateToNumberObjectType<WorkGroup> = useAppSelector(
		editTargetWorkGroupSelector
	) ?? { name: "", description: "" };

	const { control, handleSubmit, reset } = useForm<WorkGroupFormFields>({
		mode: "all",
	});

	const handleUpdateOrAdd = useCallback(
		(v: WorkGroupFormFields) => {
			dispatch(createWorkGroup(v)).then(() => {
				reset();
			});
		},
		[dispatch, reset]
	);
	const handleCancel = useCallback(() => {
		dispatch(setIsEditing({ isEditing: false }));
	}, [dispatch]);

	return (
		<Dialog
			open={isOpen}
			onClose={handleCancel}>
			<Paper
				component="form"
				onSubmit={handleSubmit(handleUpdateOrAdd)}
				sx={{ p: "1em", width: "24em" }}>
				<Stack spacing={2}>
					<Typography variant="h6">
						{isEditExistingWorkGroup
							? t("Edit Work Group")
							: t("Create New Work Group")}
					</Typography>
					<Controller
						name="name"
						control={control}
						defaultValue={workGroup.name}
						rules={{
							required: { value: true, message: t("required") },
							minLength: {
								value: NAME_MIN_LENGTH,
								message: t("minLength", { minLength: NAME_MIN_LENGTH }),
							},
							maxLength: {
								value: NAME_MAX_LENGTH,
								message: t("maxLength", { maxLength: NAME_MAX_LENGTH }),
							},
						}}
						render={({ field, formState: { errors } }) => (
							<TextField
								{...field}
								disabled={isProcessing}
								label={t("Name")}
								type="text"
								variant="outlined"
								margin="normal"
								fullWidth
								error={errors.name?.message !== undefined}
								helperText={errors.name?.message}
							/>
						)}
					/>
					<Controller
						name="description"
						control={control}
						defaultValue={workGroup.description}
						rules={{
							required: { value: true, message: t("required") },
							minLength: {
								value: DESCRIPTION_MIN_LENGTH,
								message: t("minLength", { minLength: DESCRIPTION_MIN_LENGTH }),
							},
							maxLength: {
								value: DESCRIPTION_MAX_LENGTH,
								message: t("maxLength", { maxLength: DESCRIPTION_MAX_LENGTH }),
							},
						}}
						render={({ field, formState: { errors } }) => (
							<TextField
								{...field}
								disabled={isProcessing}
								label={t("Description")}
								type="text"
								multiline
								rows={4}
								variant="outlined"
								margin="normal"
								fullWidth
								error={errors.description?.message !== undefined}
								helperText={errors.description?.message}
							/>
						)}
					/>

					<LinearProgress
						sx={{ width: "100%", margin: "1em 0" }}
						variant={isProcessing ? "indeterminate" : "determinate"}
						value={isProcessing ? undefined : 0}
					/>

					{errorMessage !== undefined && (
						<Typography color="error">{errorMessage}</Typography>
					)}

					<Box sx={{ display: "flex", justifyContent: "space-between" }}>
						<Button
							variant="contained"
							onClick={handleCancel}
							disabled={isProcessing}>
							{t("Cancel")}
						</Button>
						<Button
							type="submit"
							variant="contained"
							onClick={handleSubmit(handleUpdateOrAdd)}
							endIcon={<Send />}
							disabled={isProcessing}>
							{isEditExistingWorkGroup ? t("Update") : t("Add")}
						</Button>
					</Box>
				</Stack>
			</Paper>
		</Dialog>
	);
};
