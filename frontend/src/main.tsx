import React, { useMemo } from "react";
import ReactDOM from "react-dom/client";
import { createBrowserRouter, RouterProvider } from "react-router-dom";

import { ThemeProvider } from "@emotion/react";
import { createTheme, CssBaseline } from "@mui/material";
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
