import type { FieldValues, Path } from "react-hook-form";

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
	SELECT: "select",
	SWITCH: "switch",
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

export type EditDataFormSelectFieldSettings<
	T extends string | number | symbol,
> = {
	type: (typeof FieldTypes)["SELECT"];
	items: Record<T, { value: T; label: string }>;
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
	| {
			type: (typeof FieldTypes)["SWITCH"];
	  }
	| EditDataFormSelectFieldSettings<string | number>
);

export const isStringField = <T extends FieldValues>(
	settings: EditDataFormSetting<T>
): settings is EditDataFormSetting<T> & StringFieldSettings =>
	settings.type === FieldTypes.TEXT ||
	settings.type === FieldTypes.PASSWORD ||
	settings.type === FieldTypes.EMAIL ||
	settings.type === FieldTypes.URL ||
	settings.type === FieldTypes.TEL;

export const isTextField = <T extends FieldValues>(
	settings: EditDataFormSetting<T>
): settings is EditDataFormSetting<T> & TextFieldSettings =>
	settings.type === FieldTypes.TEXT;
