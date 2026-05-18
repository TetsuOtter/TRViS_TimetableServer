// AuthControls — self-contained top-bar auth widget.
// Renders the trigger button (Sign In/Up when logged out; account icon
// with an unverified-email warning dot when logged in) plus all three
// auth dialogs, so a single <AuthControls/> in the top bar is complete.
import { useAuth } from "../../app/AuthContext";
import { useT } from "../../app/SettingsContext";

import AccountSettingDialog from "./AccountSettingDialog";
import EMailVerifyDialog from "./EMailVerifyDialog";
import SignInUpDialog from "./SignInUpDialog";

const AuthControls = () => {
	const { user, isEmailVerified, openSignIn, openAccount } = useAuth();
	const t = useT();

	return (
		<>
			{user == null ? (
				<button
					className="topbar-btn"
					onClick={openSignIn}
					title={t.signInUp}>
					<span>👤</span>
					<span>{t.signInUp}</span>
				</button>
			) : (
				<button
					className="topbar-btn"
					onClick={openAccount}
					title={t.account}
					style={{ position: "relative" }}>
					<span>👤</span>
					{!isEmailVerified && (
						<span
							aria-label="メールアドレス未確認"
							style={{
								position: "absolute",
								top: 2,
								right: 2,
								width: 7,
								height: 7,
								borderRadius: "50%",
								background: "var(--color-danger)",
								border: "1px solid var(--color-topbar)",
							}}
						/>
					)}
				</button>
			)}

			<SignInUpDialog />
			<EMailVerifyDialog />
			<AccountSettingDialog />
		</>
	);
};

export default AuthControls;
