import { useCallback, useMemo, useState } from "react";

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
import { DatePicker } from "@mui/x-date-pickers";
import { Controller, useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";

import { useActionWithProcessing } from "../redux/actionWithProcessingHook";
import { useAppDispatch, useAppSelector } from "../redux/hooks";

import type { setIsEditingPayloadType } from "../redux/payloadTypes";
import type { AppAsyncThunk, AppSelector } from "../redux/store";
import type { TextFieldProps } from "@mui/material";
import type { ActionCreatorWithPayload } from "@reduxjs/toolkit";
import type {
	Control,
	FieldValues,
	Path,
	RegisterOptions,
} from "react-hook-form";

export const FieldTypes = {
	TEXT: "text",
	NUMBER: "number",
	EMAIL: "email",
	PASSWORD: "password",
	DATE: "date",
	TIME: "time",
	DATETIME_LOCAL: "datetime-local",
	URL: "url",
	TEL: "tel",
	COLOR: "color",
} as const;
export type FieldTypes = (typeof FieldTypes)[keyof typeof FieldTypes];
type StringFieldTypes = (typeof FieldTypes)[
	| "TEXT"
	| "PASSWORD"
	| "EMAIL"
	| "URL"
	| "TEL"];
type StringFieldSettings = {
	type: StringFieldTypes;
	minLength?: number;
	maxLength?: number;
};
type TextFieldSettings = {
	type: (typeof FieldTypes)["TEXT"];
	isMultiline?: boolean;
	rows?: number;
};
export type EditDataFormSetting<T extends FieldValues> = {
	name: Path<T>;
	label: string;
	type: FieldTypes;
	isRequired: boolean;
} & (
	| StringFieldSettings
	| TextFieldSettings
	| {
			type: (typeof FieldTypes)["NUMBER"];
			min?: number;
			max?: number;
	  }
	| {
			type: (typeof FieldTypes)["DATE"];
	  }
);
const isStringField = <T extends FieldValues>(
	settings: EditDataFormSetting<T>
): settings is EditDataFormSetting<T> & StringFieldSettings =>
	settings.type === FieldTypes.TEXT ||
	settings.type === FieldTypes.PASSWORD ||
	settings.type === FieldTypes.EMAIL ||
	settings.type === FieldTypes.URL ||
	settings.type === FieldTypes.TEL;
const isTextField = <T extends FieldValues>(
	settings: EditDataFormSetting<T>
): settings is EditDataFormSetting<T> & TextFieldSettings =>
	settings.type === FieldTypes.TEXT;

const FormElement = <T extends FieldValues>(props: {
	settings: EditDataFormSetting<T>;
	control: Control<T>;
	data: T | undefined;
	isProcessing: boolean;
}) => {
	const { t } = useTranslation();
	const { data, settings } = props;

	const propsMinMaxLength: RegisterOptions<T, Path<T>> = {};
	if (isStringField(settings)) {
		if (settings.minLength !== undefined) {
			propsMinMaxLength.minLength = {
				value: settings.minLength,
				message: t("minLength", { minLength: settings.minLength }),
			};
		}
		if (settings.maxLength !== undefined) {
			propsMinMaxLength.maxLength = {
				value: settings.maxLength,
				message: t("maxLength", { maxLength: settings.maxLength }),
			};
		}
	}

	const textFieldProps: Partial<TextFieldProps> = {};
	if (isTextField(settings)) {
		textFieldProps.multiline = settings.isMultiline;
		textFieldProps.rows = settings.rows;
	}

	return (
		<Controller
			name={settings.name}
			control={props.control}
			defaultValue={data?.[settings.name]}
			rules={{
				required: { value: settings.isRequired, message: t("required") },
				...propsMinMaxLength,
			}}
			render={({ field, formState: { errors } }) =>
				settings.type === FieldTypes.DATE ? (
					<DatePicker
						{...field}
						disabled={props.isProcessing}
						label={settings.label}
						slotProps={{
							textField: {
								variant: "outlined",
								margin: "normal",
								fullWidth: true,
								error: errors[settings.name]?.message !== undefined,
								helperText: <>{errors[settings.name]?.message}</>,
							},
						}}
					/>
				) : (
					<TextField
						{...field}
						disabled={props.isProcessing}
						label={settings.label}
						type={settings.type}
						variant="outlined"
						margin="normal"
						fullWidth
						{...textFieldProps}
						error={errors[settings.name]?.message !== undefined}
						helperText={<>{errors[settings.name]?.message}</>}
					/>
				)
			}
		/>
	);
};

type EditWorkDialogProps<T extends FieldValues> = {
	formSettings: EditDataFormSetting<T>[];
	isEditingSelector: AppSelector<boolean>;
	setIsEditing: ActionCreatorWithPayload<setIsEditingPayloadType>;

	editModeTitle: string;
	createModeTitle: string;

	getId: (data: T) => string | undefined;
	initialStateSelector: AppSelector<T | undefined>;
	createData: AppAsyncThunk<void, T>;
	updateData: AppAsyncThunk<void, T>;
};

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
				await dispatchCreateOrUpdate({ ...initialState, ...v });
				reset();
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
		[dispatchCreateOrUpdate, initialState, isAddNew, reset, t]
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
							{isAddNew ? t("Add") : t("New")}
						</Button>
					</Box>
				</Stack>
			</Paper>
		</Dialog>
	);
};
