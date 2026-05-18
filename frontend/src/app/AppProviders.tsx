// AppProviders — single composition point for every app-wide provider.
// Keeping the stack here (instead of inline in main.tsx) keeps JSX nesting
// under the project's react/jsx-max-depth limit as more providers are added.
import type { ReactNode } from "react";

import { QueryClientProvider } from "@tanstack/react-query";

import { queryClient } from "../api/queryClient";

import { AuthProvider } from "./AuthContext";
import AuthGate from "./AuthGate";
import { SettingsProvider } from "./SettingsContext";

type Props = { readonly children: ReactNode };

const AppProviders = ({ children }: Props) => (
	<QueryClientProvider client={queryClient}>
		<SettingsProvider>
			<AuthProvider>
				<AuthGate>{children}</AuthGate>
			</AuthProvider>
		</SettingsProvider>
	</QueryClientProvider>
);

export default AppProviders;
