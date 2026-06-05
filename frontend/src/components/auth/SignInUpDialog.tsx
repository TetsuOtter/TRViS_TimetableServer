// SignInUpDialog — new-design modal wrapper around SignInUpForm.
// Open state controlled by useAuth() (isSignInOpen / closeSignIn).
import { useAuth } from "../../app/AuthContext";
import { useT } from "../../app/SettingsContext";

import SignInUpForm from "./SignInUpForm";

const SignInUpDialog = () => {
	const { isSignInOpen, closeSignIn } = useAuth();
	const t = useT();

	if (!isSignInOpen) return null;

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && closeSignIn()}>
			<div
				className="modal"
				style={{ maxWidth: 420 }}>
				<div className="modal-header">
					<span className="modal-title">{t.signInUp}</span>
					<button
						type="button"
						className="btn btn-ghost btn-sm"
						onClick={closeSignIn}>{`
						✕
					`}</button>
				</div>
				<div className="modal-body">
					<SignInUpForm />
				</div>
			</div>
		</div>
	);
};

export default SignInUpDialog;
