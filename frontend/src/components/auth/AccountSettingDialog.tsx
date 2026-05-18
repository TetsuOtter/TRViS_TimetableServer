// AccountSettingDialog — new-design modal showing account info & sign-out.
// Mirrors old slice logic (email, userId, verified status, reload, sign out)
// without Redux. Clipboard state simplified to idle/copied/error.
import { useCallback, useRef, useState } from "react";

import { useAuth } from "../../app/AuthContext";
import { useT } from "../../app/SettingsContext";

type CopyState = "idle" | "copied" | "error";

const AccountSettingDialog = () => {
	const {
		isAccountOpen,
		closeAccount,
		userId,
		email,
		isEmailVerified,
		isProcessing,
		signOutUser,
		reloadUser,
	} = useAuth();
	const t = useT();

	const [copyState, setCopyState] = useState<CopyState>("idle");
	const copyTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

	const handleCopyUserId = useCallback(async () => {
		if (copyTimer.current != null) clearTimeout(copyTimer.current);
		try {
			await navigator.clipboard.writeText(userId);
			setCopyState("copied");
		} catch {
			setCopyState("error");
		}
		copyTimer.current = setTimeout(() => setCopyState("idle"), 2000);
	}, [userId]);

	const handleSignOut = useCallback(() => {
		void signOutUser();
	}, [signOutUser]);

	const handleReloadVerified = useCallback(() => {
		void reloadUser();
	}, [reloadUser]);

	if (!isAccountOpen) return null;

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && closeAccount()}>
			<div className="modal" style={{ maxWidth: 480 }}>
				<div className="modal-header">
					<span className="modal-title">👤 {t.account}</span>
					<button
						className="btn btn-ghost btn-sm"
						onClick={closeAccount}>
						✕
					</button>
				</div>
				<div className="modal-body">
					<div className="field" style={{ marginBottom: 16 }}>
						<label>ユーザーID</label>
						<div style={{ display: "flex", gap: 6 }}>
							<input
								readOnly
								value={userId}
								style={{
									flex: 1,
									fontFamily: "var(--font-mono)",
									fontSize: 12,
								}}
							/>
							<button
								className="btn btn-secondary btn-sm"
								onClick={handleCopyUserId}
								title="ユーザーIDをコピー">
								{copyState === "copied"
									? "✓"
									: copyState === "error"
										? "✕"
										: "📋"}
							</button>
						</div>
					</div>

					<div className="field" style={{ marginBottom: 16 }}>
						<label>{t.email}</label>
						<div
							style={{
								display: "flex",
								alignItems: "center",
								gap: 8,
							}}>
							<span style={{ flex: 1, fontSize: 13 }}>
								{email}
							</span>
							{isEmailVerified ? (
								<span className="chip green">確認済み</span>
							) : (
								<>
									<span className="chip amber">未確認</span>
									<button
										className="btn btn-ghost btn-sm"
										disabled={isProcessing}
										onClick={handleReloadVerified}
										title="状態を再読み込み">
										🔄
									</button>
								</>
							)}
						</div>
					</div>
				</div>
				<div className="modal-footer">
					<button
						className="btn btn-danger"
						disabled={isProcessing}
						onClick={handleSignOut}>
						{t.signOut}
					</button>
					<button
						className="btn btn-secondary"
						onClick={closeAccount}>
						閉じる
					</button>
				</div>
			</div>
		</div>
	);
};

export default AccountSettingDialog;
