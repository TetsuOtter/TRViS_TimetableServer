export const I18N_LANGUAGES = {
	English: "en",
	Japanese: "ja",
} as const;
export type I18N_LANGUAGE_TYPE =
	(typeof I18N_LANGUAGES)[keyof typeof I18N_LANGUAGES];

export const LANGUAGE_NAMES = {
	[I18N_LANGUAGES.English]: "English",
	[I18N_LANGUAGES.Japanese]: "日本語",
} as const;
