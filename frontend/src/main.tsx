import React, { useMemo } from "react";
import ReactDOM from "react-dom/client";
import { createBrowserRouter, RouterProvider } from "react-router-dom";

import { ThemeProvider } from "@emotion/react";
import { createTheme, CssBaseline } from "@mui/material";
import { enUS as muiCoreEnUs, jaJP as muiCoreJaJp } from "@mui/material/locale";
import {
	enUS as muiDataGridEnUs,
	jaJP as muiDataGridJaJp,
} from "@mui/x-data-grid";
import i18n, { changeLanguage } from "i18next";
import I18nextBrowserLanguageDetector from "i18next-browser-languagedetector";
import I18NextHttpBackend from "i18next-http-backend";
import { initReactI18next, useTranslation } from "react-i18next";
import { Provider } from "react-redux";

import App from "./App.tsx";
import MessageDialog from "./components/MessageDialog.tsx";
import MyAppBar from "./components/MyAppBar.tsx";
import { auth } from "./firebase/configure.ts";
import { useAppThemeMode } from "./hooks/appThemeModeHook.ts";
import { I18N_LANGUAGES, I18N_LANGUAGES_ARRAY } from "./i18n.ts";
import ErrorPage from "./pages/ErrorPage.tsx";
import WorkGroupsPage from "./pages/WorkGroupsPage.tsx";
import { store } from "./redux/store.ts";

import type { I18N_LANGUAGE_TYPE } from "./i18n.ts";

import "@fontsource/roboto/300.css";
import "@fontsource/roboto/400.css";
import "@fontsource/roboto/500.css";
import "@fontsource/roboto/700.css";

const router = createBrowserRouter([
	{
		path: "/",
		element: <App />,
		errorElement: <ErrorPage />,
	},
	{
		path: "/work_groups",
		element: <WorkGroupsPage />,
		errorElement: <ErrorPage />,
	},
]);

// eslint-disable-next-line import/no-named-as-default-member
i18n
	.use(I18nextBrowserLanguageDetector)
	.use(I18NextHttpBackend)
	.use(initReactI18next)
	.init({
		fallbackLng: I18N_LANGUAGES.Japanese,
		debug: true,
		interpolation: {
			escapeValue: false,
		},
		backend: {
			loadPath: `${window.location.origin}/i18n/{{lng}}.json`,
		},
	})
	.then(() => {
		console.log("i18n initialized");

		if (
			I18N_LANGUAGES_ARRAY.indexOf(i18n.language as I18N_LANGUAGE_TYPE) === -1
		) {
			console.log(
				"i18n.language was not in I18N_LANGUAGE_TYPE",
				i18n.language,
				I18N_LANGUAGES_ARRAY
			);
			const language = i18n.language.split("-")[0];
			if (I18N_LANGUAGES_ARRAY.indexOf(language as I18N_LANGUAGE_TYPE) !== -1) {
				console.log("changeLanguage (language)", language);
				changeLanguage(language as I18N_LANGUAGE_TYPE);
			} else {
				console.log("changeLanguage (default)", I18N_LANGUAGES.English);
				changeLanguage(I18N_LANGUAGES.English);
			}
		}

		if (i18n.language !== auth.languageCode) {
			auth.languageCode = i18n.language;
		}
	});

const RootComponentWithRedux = () => {
	const appThemeMode = useAppThemeMode();
	const {
		i18n: { language },
	} = useTranslation();

	const muiTranslations = useMemo(() => {
		switch (language) {
			case I18N_LANGUAGES.Japanese:
				return [muiCoreJaJp, muiDataGridJaJp];
			case I18N_LANGUAGES.English:
			default:
				return [muiCoreEnUs, muiDataGridEnUs];
		}
	}, [language]);

	const theme = useMemo(
		() =>
			createTheme(
				{
					palette: {
						mode: appThemeMode,
					},
				},
				...muiTranslations
			),
		[appThemeMode, muiTranslations]
	);

	return (
		<ThemeProvider theme={theme}>
			<CssBaseline />
			<MyAppBar />
			<RouterProvider router={router} />
			<MessageDialog />
		</ThemeProvider>
	);
};

ReactDOM.createRoot(document.getElementById("root")!).render(
	<React.StrictMode>
		<Provider store={store}>
			<RootComponentWithRedux />
		</Provider>
	</React.StrictMode>
);
