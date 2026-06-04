import type { SerializedError } from "@reduxjs/toolkit";

export const getAuthErrorMessage = ({
	name,
	code,
	message,
}: SerializedError): string => {
	if (code == null || name == null) {
		return message ?? name ?? "不明なエラーが発生しました";
	}

	switch (code) {
		case "auth/email-already-in-use":
			return "このメールアドレスはすでに使用されています";
		case "auth/internal-error":
			return "内部エラーが発生しました";
		case "auth/invalid-verification-code":
			return "確認コードが正しくありません";
		case "auth/invalid-email":
			return "メールアドレスの形式が正しくありません";
		case "auth/invalid-credential":
			return "メールアドレスまたはパスワードが正しくありません";
		case "auth/unauthorized-domain":
			return "このドメインからはサインインできません";
		case "auth/network-request-failed":
			return "ネットワークエラーが発生しました。インターネット接続を確認してください";
		case "auth/null-user":
			return "ユーザー情報が見つかりません";
		case "auth/operation-not-allowed":
			return "この操作は許可されていません";
		case "auth/timeout":
			return "タイムアウトしました。再度お試しください";
		case "auth/user-token-expired":
			return "セッションが期限切れです。再度サインインしてください";
		case "auth/too-many-requests":
			return "操作が多すぎます。しばらくしてから再度お試しください";
		case "auth/unauthorized-continue-uri":
			return "リダイレクト先が無効です";
		case "auth/unverified-email":
			return "メールアドレスの確認が完了していません";
		case "auth/user-disabled":
			return "このアカウントは無効化されています";
		case "auth/user-signed-out":
			return "サインアウトしました";
		case "auth/wrong-password":
		case "auth/user-not-found":
			return "メールアドレスまたはパスワードが正しくありません";
		default:
			return message ?? name ?? "不明なエラーが発生しました";
	}
};
