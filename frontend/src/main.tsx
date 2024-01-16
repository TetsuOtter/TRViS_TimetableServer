import React, { useMemo } from "react";
import ReactDOM from "react-dom/client";
import { createBrowserRouter, RouterProvider } from "react-router-dom";

import { ThemeProvider } from "@emotion/react";
import { createTheme, CssBaseline } from "@mui/material";
import i18n from "i18next";
import I18nextBrowserLanguageDetector from "i18next-browser-languagedetector";
import I18NextHttpBackend from "i18next-http-backend";
import { initReactI18next } from "react-i18next";
import { Provider } from "react-redux";

import App from "./App.tsx";
import MyAppBar from "./components/MyAppBar.tsx";
import { useAppThemeMode } from "./hooks/appThemeModeHook.ts";
import ErrorPage from "./pages/ErrorPage.tsx";
import { store } from "./redux/store.ts";

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
]);

i18n
	.use(I18nextBrowserLanguageDetector)
	.use(I18NextHttpBackend)
	.use(initReactI18next)
	.init({
		fallbackLng: "ja",
		debug: true,
		interpolation: {
			escapeValue: false,
		},
		backend: {
			loadPath: `${window.location.origin}/i18n/{{lng}}.json`,
		},
	});

const RootComponentWithRedux = () => {
	const appThemeMode = useAppThemeMode();

	const theme = useMemo(
		() =>
			createTheme({
				palette: {
					mode: appThemeMode,
				},
			}),
		[appThemeMode]
	);

	return (
		<ThemeProvider theme={theme}>
			<CssBaseline />
			<MyAppBar />
			<RouterProvider router={router} />
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
