import { keyStatus, privilegeColor, privilegeLabel } from "./inviteShared";

import type { Strings } from "../i18n/strings";
import type { InviteKey } from "../types/entities";

export type InviteKeyRowProps = {
	readonly inviteKey: InviteKey;
	readonly copied: string | null;
	readonly t: Strings;
	readonly onCopy: (id: string) => void;
	readonly onRevoke: (id: string, desc: string) => void;
};

export const InviteKeyRow = ({
	inviteKey,
	copied,
	t,
	onCopy,
	onRevoke,
}: InviteKeyRowProps) => {
	const status = keyStatus(inviteKey, t);
	const isInactive = status !== null;

	return (
		<div
			style={{
				border: "1px solid var(--color-border)",
				borderRadius: 6,
				padding: "10px 14px",
				opacity: isInactive ? 0.6 : 1,
				display: "flex",
				flexDirection: "column",
				gap: 4,
			}}>
			<div style={{ display: "flex", alignItems: "center", gap: 8 }}>
				<code
					style={{
						fontSize: 11,
						fontFamily: "monospace",
						color: "var(--color-text-muted)",
						flex: 1,
						overflow: "hidden",
						textOverflow: "ellipsis",
						whiteSpace: "nowrap",
					}}>
					{inviteKey.id}
				</code>
				<button
					type="button"
					className="btn btn-ghost btn-xs"
					title="UUIDをコピー"
					onClick={() => {
						onCopy(inviteKey.id);
					}}
					style={{ fontSize: 12, flexShrink: 0 }}>
					{copied === inviteKey.id ? "✓" : "📋"}
				</button>
				{!isInactive && (
					<button
						type="button"
						className="btn btn-ghost btn-xs"
						style={{ color: "var(--color-danger, #ef4444)", flexShrink: 0 }}
						title={t.revoke}
						onClick={() => {
							onRevoke(inviteKey.id, inviteKey.description);
						}}>{`
						✕
					`}</button>
				)}
			</div>

			<div
				style={{
					display: "flex",
					alignItems: "center",
					gap: 8,
					flexWrap: "wrap",
				}}>
				<span style={{ fontSize: 12, fontWeight: 500 }}>
					{inviteKey.description}
				</span>
				<span
					style={{
						fontSize: 11,
						color: isInactive
							? "var(--color-text-muted)"
							: privilegeColor(inviteKey.privilegeType),
						border: "1px solid currentColor",
						borderRadius: 4,
						padding: "1px 6px",
					}}>
					{privilegeLabel(inviteKey.privilegeType, t)}
				</span>
				{status !== null && (
					<span
						style={{
							fontSize: 11,
							color: "var(--color-danger, #ef4444)",
							fontWeight: 500,
						}}>
						{status}
					</span>
				)}
			</div>

			<div
				style={{
					fontSize: 11,
					color: "var(--color-text-muted)",
					display: "flex",
					gap: 12,
					flexWrap: "wrap",
				}}>
				{inviteKey.expiresAt !== undefined && (
					<span>
						{t.expiresAt}
						{`: `}
						{inviteKey.expiresAt.toLocaleString()}
					</span>
				)}
				{inviteKey.useLimit !== undefined && (
					<span>
						{t.useLimit}
						{`: `}
						{inviteKey.useLimit}
					</span>
				)}
				{inviteKey.createdAt !== undefined && (
					<span>
						{`作成: `}
						{inviteKey.createdAt.toLocaleDateString()}
					</span>
				)}
			</div>
		</div>
	);
};
