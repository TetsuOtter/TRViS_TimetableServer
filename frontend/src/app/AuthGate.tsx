// AuthGate — wraps the entire app behind Firebase auth.
// Shows a splash while the auth state resolves, then either a
// sign-in card (unauthenticated) or the children (authenticated).
import type { ReactNode } from "react";

import { useAuth } from "./AuthContext";
import { useSettings } from "./SettingsContext";

type Props = { readonly children: ReactNode };

const LOADING_TEXT = "読み込み中…";

const AuthGate = ({ children }: Props) => {
	const { isAuthReady } = useAuth();
	const { theme, density } = useSettings();

	if (!isAuthReady) {
		return (
			<div
				data-theme={theme}
				data-density={density}
				className="empty-state"
				style={{ height: "100%" }}>
				<p>{LOADING_TEXT}</p>
			</div>
		);
	}

	return <>{children}</>;
};

export default AuthGate;
