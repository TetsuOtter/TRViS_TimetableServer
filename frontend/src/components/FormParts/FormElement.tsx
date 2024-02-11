import {
	TextField,
	Select,
	MenuItem,
	Switch,
	FormControlLabel,
} from "@mui/material";
import { DatePicker } from "@mui/x-date-pickers";
import { Controller } from "react-hook-form";
import { useTranslation } from "react-i18next";

import { FieldTypes, isStringField, isTextField } from "./FieldTypes";

import type { EditDataFormSetting } from "./FieldTypes";
import type { TextFieldProps } from "@mui/material";
import type {
	Control,
	FieldValues,
	Path,
	RegisterOptions,
} from "react-hook-form";

type FormElementProps<T extends FieldValues> = Readonly<{
	data: T | undefined;
	settings: EditDataFormSetting<T>;
	control: Control<T>;
	isProcessing: boolean;
}>;
export const FormElement = <T extends FieldValues>({
	data,
	settings,
	control,
	isProcessing,
}: FormElementProps<T>) => {
	const { t } = useTranslation();

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
			control={control}
			defaultValue={data?.[settings.name]}
			rules={{
				required: { value: settings.isRequired, message: t("required") },
				...propsMinMaxLength,
			}}
			render={({ field, formState: { errors } }) =>
				settings.type === FieldTypes.DATE ? (
					<DatePicker
						{...field}
						disabled={isProcessing}
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
				) : settings.type === FieldTypes.SELECT ? (
					<Select
						{...field}
						disabled={isProcessing}
						label={settings.label}
						variant="outlined"
						fullWidth>
						{Object.entries(settings.items).map(([key, { label }]) => (
							<MenuItem
								key={key}
								value={key}>
								{label}
							</MenuItem>
						))}
					</Select>
				) : settings.type === FieldTypes.SWITCH ? (
					<FormControlLabel
						label={settings.label}
						disabled={isProcessing}
						control={<Switch {...field} />}
					/>
				) : (
					<TextField
						{...field}
						disabled={isProcessing}
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
