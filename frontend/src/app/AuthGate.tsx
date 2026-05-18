// AuthGate — wraps the entire app behind Firebase auth.
// Shows a splash while the auth state resolves, then either a
// sign-in card (unauthenticated) or the children (authenticated).
import type { ReactNode } from "react";

import SignInUpForm from "../components/auth/SignInUpForm";

import { useAuth } from "./AuthContext";
import { useSettings } from "./SettingsContext";

type Props = { readonly children: ReactNode };

const LOADING_TEXT = "読み込み中…";

const AuthGate = ({ children }: Props) => {
	const { user, isAuthReady } = useAuth();
	const { theme, density, t } = useSettings();

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

	if (user === null) {
		return (
			<div
				data-theme={theme}
				data-density={density}
				style={{
					height: "100%",
					display: "flex",
					alignItems: "center",
					justifyContent: "center",
				}}>
				<div
					className="modal"
					style={{ maxWidth: 420 }}>
					<div className="modal-header">
						<span className="modal-title">{t.signInUp}</span>
					</div>
					<div className="modal-body">
						<SignInUpForm />
					</div>
				</div>
			</div>
		);
	}

	return <>{children}</>;
};

export default AuthGate;
