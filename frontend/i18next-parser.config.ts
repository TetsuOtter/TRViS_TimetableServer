import { I18N_LANGUAGES } from "./src/i18n";

export default {
	createOldCatalogs: false,
	defaultValue: "NO_TRANSLATION",
	lineEnding: "lf",
	locales: Object.values(I18N_LANGUAGES),
	output: "public/i18n/$LOCALE.json",
	input: ["src/**/*.{ts,tsx}"],
	sort: true,
};
