import { t } from "i18next";

import type { SerializedError } from "@reduxjs/toolkit";

export const getAuthErrorMessage = ({
	name,
	code,
	message,
}: SerializedError): string => {
	console.log("getAuthErrorMessage", name, code, message);
	if (code == null || name == null) {
		return message ?? name ?? "Unknown error";
	}

	switch (code) {
		case "auth/email-already-in-use":
			return t("auth.email-already-in-use");
		case "auth/internal-error":
			return t("auth.internal-error");
		case "auth/invalid-verification-code":
			return t("auth.invalid-verification-code");
		case "auth/invalid-email":
			return t("auth.invalid-email");
		case "auth/invalid-credential":
			return t("auth.invalid-credential");
		case "auth/unauthorized-domain":
			return t("auth.unauthorized-domain");
		case "auth/network-request-failed":
			return t("auth.network-request-failed");
		case "auth/null-user":
			return t("auth.null-user");
		case "auth/operation-not-allowed":
			return t("auth.operation-not-allowed");
		case "auth/timeout":
			return t("auth.timeout");
		case "auth/user-token-expired":
			return t("auth.user-token-expired");
		case "auth/too-many-requests":
			return t("auth.too-many-requests");
		case "auth/unauthorized-continue-uri":
			return t("auth.unauthorized-continue-uri");
		case "auth/unverified-email":
			return t("auth.unverified-email");
		case "auth/user-disabled":
			return t("auth.user-disabled");
		case "auth/user-signed-out":
			return t("auth.user-signed-out");
		case "auth/wrong-password":
		case "auth/user-not-found":
			return t("auth.user-not-found");
		default:
			return message ?? name;
	}
};
