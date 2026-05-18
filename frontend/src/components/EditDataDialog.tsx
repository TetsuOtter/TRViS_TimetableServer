import { useCallback, useMemo, useState } from "react";

import { Send } from "@mui/icons-material";
import {
	Box,
	Button,
	Dialog,
	LinearProgress,
	Paper,
	Stack,
	Typography,
} from "@mui/material";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";

import { useActionWithProcessing } from "../redux/actionWithProcessingHook";
import { useAppDispatch, useAppSelector } from "../redux/hooks";

import { FieldTypes } from "./FormParts/FieldTypes";
import { FormElement } from "./FormParts/FormElement";

import type { EditDataFormSetting } from "./FormParts/FieldTypes";
import type { setIsEditingPayloadType } from "../redux/payloadTypes";
import type { AppAsyncThunk, AppSelector } from "../redux/store";
import type { ActionCreatorWithPayload } from "@reduxjs/toolkit";
import type { FieldValues } from "react-hook-form";

type EditWorkDialogProps<T extends FieldValues> = Readonly<{
	formSettings: EditDataFormSetting<T>[];
	isEditingSelector: AppSelector<boolean>;
	setIsEditing: ActionCreatorWithPayload<setIsEditingPayloadType>;

	editModeTitle: string;
	createModeTitle: string;

	getId: (data: T) => string | undefined;
	initialStateSelector: AppSelector<T | undefined>;
	createData: AppAsyncThunk<void, T>;
	updateData: AppAsyncThunk<void, T>;
}>;

export const EditDataDialog = <T extends FieldValues>({
	formSettings,
	isEditingSelector,
	setIsEditing,

	editModeTitle,
	createModeTitle,

	getId,
	initialStateSelector,
	createData,
	updateData,
}: EditWorkDialogProps<T>) => {
	const { t } = useTranslation();
	const dispatch = useAppDispatch();
	const [errorMessage, setErrorMessage] = useState<string | undefined>(
		undefined
	);

	const initialState = useAppSelector(initialStateSelector);
	const isAddNew = useMemo(
		() => initialState == null || getId(initialState) == null,
		[getId, initialState]
	);
	const isOpen = useAppSelector(isEditingSelector);

	const [dispatchCreateOrUpdate, isProcessing] = useActionWithProcessing(
		isAddNew ? createData : updateData
	);

	const { control, handleSubmit, reset } = useForm<T>({
		mode: "all",
	});

	const handleUpdateOrAdd = useCallback(
		async (v: T) => {
			setErrorMessage(undefined);
			try {
				const nextState = { ...initialState, ...v };
				formSettings.forEach((settings) => {
					if (settings.type === FieldTypes.NUMBER) {
						const num = nextState[settings.name] as unknown;
						if (num != null && typeof num !== "number") {
							nextState[settings.name] = Number(
								num
							) as (typeof nextState)[typeof settings.name];
						}
					} else if (settings.type === FieldTypes.SELECT) {
						const item = nextState[settings.name] as string;
						if (item != null) {
							nextState[settings.name] = settings.items[item]
								?.value as (typeof nextState)[typeof settings.name];
						}
					}
				});
				await dispatchCreateOrUpdate(nextState);
				reset(nextState);
				dispatch(setIsEditing({ isEditing: false }));
			} catch (e) {
				console.error(`handleUpdateOrAdd(isAddNew: ${isAddNew})`, e);
				if (e instanceof Error) {
					setErrorMessage(e.message);
				} else if (typeof e === "string") {
					setErrorMessage(e);
				} else {
					setErrorMessage(t("Unknown error"));
				}
			}
		},
		[
			dispatch,
			dispatchCreateOrUpdate,
			formSettings,
			initialState,
			isAddNew,
			reset,
			setIsEditing,
			t,
		]
	);
	const handleCancel = useCallback(() => {
		dispatch(setIsEditing({ isEditing: false }));
		reset();
	}, [dispatch, reset, setIsEditing]);

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
						{isAddNew ? createModeTitle : editModeTitle}
					</Typography>
					{formSettings.map((settings) => (
						<FormElement<T>
							key={settings.name}
							settings={settings}
							control={control}
							data={initialState}
							isProcessing={isProcessing}
						/>
					))}

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
							{isAddNew ? t("Add") : t("Update")}
						</Button>
					</Box>
				</Stack>
			</Paper>
		</Dialog>
	);
};
