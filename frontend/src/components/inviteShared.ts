import type { Strings } from "../i18n/strings";
import type { InviteKey } from "../types/entities";

export type PrivilegeLevel = "read" | "write" | "admin";

export type CreateDraft = {
	description: string;
	privilegeType: PrivilegeLevel;
	expiresAt: string;
	useLimit: string;
};

export const EMPTY_DRAFT: CreateDraft = {
	description: "",
	privilegeType: "read",
	expiresAt: "",
	useLimit: "",
};

export const privilegeLabel = (
	p: PrivilegeLevel | undefined,
	t: Strings
): string => {
	if (p === "admin") return t.privilegeAdmin;
	if (p === "write") return t.privilegeWrite;
	return t.privilegeRead;
};

export const privilegeColor = (p: PrivilegeLevel | undefined): string => {
	if (p === "admin") return "var(--color-danger, #ef4444)";
	if (p === "write") return "var(--color-accent, #3b82f6)";
	return "var(--color-text-muted)";
};

export const keyStatus = (key: InviteKey, t: Strings): string | null => {
	if (key.disabledAt !== undefined) {
		if (
			key.expiresAt !== undefined &&
			key.disabledAt.getTime() >= key.expiresAt.getTime() - 1000
		) {
			return t.inviteKeyExpired;
		}
		return t.inviteKeyRevoked;
	}
	if (key.expiresAt !== undefined && key.expiresAt < new Date()) {
		return t.inviteKeyExpired;
	}
	return null;
};

export const labelStyle: React.CSSProperties = {
	display: "block",
	fontSize: 12,
	fontWeight: 500,
	marginBottom: 4,
	color: "var(--color-text-muted)",
};
