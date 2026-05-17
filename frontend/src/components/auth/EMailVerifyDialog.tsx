// EMailVerifyDialog — new-design modal asking the user to verify their email.
// Mirrors the old slice logic: opens for brand-new unverified sign-ups
// (isVerifyForNewUser). Adds "resend verification" and "reload" actions.
import { useCallback, useState } from "react";

import { sendEmailVerification } from "firebase/auth";

import { useAuth } from "../../app/AuthContext";
import { auth } from "../../firebase/configure";

const EMailVerifyDialog = () => {
	const { isVerifyOpen, isVerifyForNewUser, closeVerify, reloadUser } =
		useAuth();
	const [isResending, setIsResending] = useState(false);

	const handleResend = useCallback(async () => {
		const currentUser = auth.currentUser;
		if (currentUser == null) return;
		setIsResending(true);
		try {
			await sendEmailVerification(currentUser, {
				url: window.location.href,
			});
			alert("確認メールを再送しました。受信箱をご確認ください。");
		} catch {
			alert("確認メールの再送に失敗しました。");
		} finally {
			setIsResending(false);
		}
	}, []);

	const handleReload = useCallback(() => {
		void reloadUser();
	}, [reloadUser]);

	if (!isVerifyOpen) return null;

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && closeVerify()}>
			<div className="modal" style={{ maxWidth: 460 }}>
				<div className="modal-header">
					<span className="modal-title">
						{isVerifyForNewUser
							? "TRViS Data Editor へようこそ 🎉"
							: "メールアドレスの確認"}
					</span>
					<button
						className="btn btn-ghost btn-sm"
						onClick={closeVerify}>
						✕
					</button>
				</div>
				<div
					className="modal-body"
					style={{ fontSize: 13, lineHeight: 1.7 }}>
					<p style={{ marginBottom: 8 }}>
						{isVerifyForNewUser
							? "TRViS Data Editor を利用するには、メールアドレスの確認が必要です。"
							: "確認用リンクをメールアドレス宛に送信しました。"}
					</p>
					<p>
						受信箱を確認し、案内に従ってメールアドレスを確認してください。
					</p>
				</div>
				<div className="modal-footer">
					<button
						className="btn btn-secondary"
						disabled={isResending}
						onClick={handleResend}>
						確認メールを再送
					</button>
					<button
						className="btn btn-primary"
						onClick={handleReload}>
						確認しました（再読み込み）
					</button>
					<button className="btn btn-ghost" onClick={closeVerify}>
						閉じる
					</button>
				</div>
			</div>
		</div>
	);
};

export default EMailVerifyDialog;
