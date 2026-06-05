import { useEffect, useState } from "react";

import {
	useCreateInviteKey,
	useDeleteInviteKey,
	useInviteKeys,
} from "../api/hooks/useInviteKeys";

import type { Strings } from "../i18n/strings";
import type { InviteKey, WorkGroup } from "../types/entities";

type PrivilegeLevel = "read" | "write" | "admin";

type CreateDraft = {
	description: string;
	privilegeType: PrivilegeLevel;
	expiresAt: string;
	useLimit: string;
};

const EMPTY_DRAFT: CreateDraft = {
	description: "",
	privilegeType: "read",
	expiresAt: "",
	useLimit: "",
};

const privilegeLabel = (p: PrivilegeLevel | undefined, t: Strings): string => {
	if (p === "admin") return t.privilegeAdmin;
	if (p === "write") return t.privilegeWrite;
	return t.privilegeRead;
};

const privilegeColor = (p: PrivilegeLevel | undefined): string => {
	if (p === "admin") return "var(--color-danger, #ef4444)";
	if (p === "write") return "var(--color-accent, #3b82f6)";
	return "var(--color-text-muted)";
};

const keyStatus = (key: InviteKey, t: Strings): string | null => {
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

type WGInviteKeysProps = {
	readonly workGroupId: string;
	readonly t: Strings;
	readonly onRevoke: (keyId: string, keyDesc: string) => void;
};

const WGInviteKeys = ({ workGroupId, t, onRevoke }: WGInviteKeysProps) => {
	const { data: keys, isLoading } = useInviteKeys(workGroupId);
	const [showCreate, setShowCreate] = useState(false);
	const [draft, setDraft] = useState<CreateDraft>(EMPTY_DRAFT);
	const [copied, setCopied] = useState<string | null>(null);

	const createMutation = useCreateInviteKey(workGroupId);

	const set = <K extends keyof CreateDraft>(k: K, v: CreateDraft[K]) => {
		setDraft((d) => ({ ...d, [k]: v }));
	};

	const handleCreate = () => {
		if (draft.description.trim() === "") return;
		const useLimitNum =
			draft.useLimit.trim() !== "" ? parseInt(draft.useLimit, 10) : undefined;
		const expiresAtDate =
			draft.expiresAt.trim() !== "" ? new Date(draft.expiresAt) : undefined;

		createMutation.mutate(
			{
				description: draft.description.trim(),
				privilegeType: draft.privilegeType,
				expiresAt: expiresAtDate,
				useLimit:
					useLimitNum !== undefined && !isNaN(useLimitNum)
						? useLimitNum
						: undefined,
				validFrom: undefined,
			},
			{
				onSuccess: () => {
					setDraft(EMPTY_DRAFT);
					setShowCreate(false);
				},
				onError: (e) => {
					alert((e as Error).message);
				},
			}
		);
	};

	const copyToClipboard = (id: string) => {
		void navigator.clipboard.writeText(id).then(() => {
			setCopied(id);
			setTimeout(() => {
				setCopied(null);
			}, 2000);
		});
	};

	if (isLoading) {
		return (
			<div style={{ color: "var(--color-text-muted)", fontSize: 13 }}>
				読み込み中…
			</div>
		);
	}

	const activeKeys = (keys ?? []).filter(
		(k) => keyStatus(k, t) === null
	);
	const inactiveKeys = (keys ?? []).filter(
		(k) => keyStatus(k, t) !== null
	);

	return (
		<div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
			{activeKeys.length === 0 && inactiveKeys.length === 0 ? (
				<div style={{ color: "var(--color-text-muted)", fontSize: 13 }}>
					{t.noInviteKeys}
				</div>
			) : null}

			{activeKeys.map((key) => (
				<InviteKeyRow
					key={key.id}
					inviteKey={key}
					copied={copied}
					t={t}
					onCopy={copyToClipboard}
					onRevoke={onRevoke}
				/>
			))}

			{inactiveKeys.length > 0 && (
				<details style={{ marginTop: 4 }}>
					<summary
						style={{
							fontSize: 12,
							color: "var(--color-text-muted)",
							cursor: "pointer",
							userSelect: "none",
						}}>
						無効・期限切れ ({inactiveKeys.length})
					</summary>
					<div style={{ display: "flex", flexDirection: "column", gap: 8, marginTop: 8 }}>
						{inactiveKeys.map((key) => (
							<InviteKeyRow
								key={key.id}
								inviteKey={key}
								copied={copied}
								t={t}
								onCopy={copyToClipboard}
								onRevoke={onRevoke}
							/>
						))}
					</div>
				</details>
			)}

			{showCreate ? (
				<div
					style={{
						border: "1px solid var(--color-border)",
						borderRadius: 8,
						padding: 16,
						display: "flex",
						flexDirection: "column",
						gap: 10,
					}}>
					<div style={{ fontWeight: 600, fontSize: 13 }}>{t.newInviteKey}</div>

					<div>
						<label style={labelStyle}>説明 *</label>
						<input
							className="form-input"
							value={draft.description}
							onChange={(e) => {
								set("description", e.target.value);
							}}
							placeholder="例: Aチーム用招待キー"
							autoFocus
						/>
					</div>

					<div>
						<label style={labelStyle}>{t.privilegeType}</label>
						<select
							className="form-input"
							value={draft.privilegeType}
							onChange={(e) => {
								set("privilegeType", e.target.value as PrivilegeLevel);
							}}>
							<option value="read">{t.privilegeRead}</option>
							<option value="write">{t.privilegeWrite}</option>
							<option value="admin">{t.privilegeAdmin}</option>
						</select>
					</div>

					<div>
						<label style={labelStyle}>{t.expiresAt}（任意）</label>
						<input
							className="form-input"
							type="datetime-local"
							value={draft.expiresAt}
							onChange={(e) => {
								set("expiresAt", e.target.value);
							}}
						/>
					</div>

					<div>
						<label style={labelStyle}>{t.useLimit}（任意）</label>
						<input
							className="form-input"
							type="number"
							min={1}
							value={draft.useLimit}
							onChange={(e) => {
								set("useLimit", e.target.value);
							}}
							placeholder="例: 10"
						/>
					</div>

					{createMutation.error !== null && (
						<div
							style={{ color: "var(--color-danger, #ef4444)", fontSize: 12 }}>
							{(createMutation.error as Error).message}
						</div>
					)}

					<div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
						<button
							className="btn btn-secondary btn-sm"
							onClick={() => {
								setShowCreate(false);
								setDraft(EMPTY_DRAFT);
							}}>
							{t.cancel}
						</button>
						<button
							className="btn btn-primary btn-sm"
							disabled={
								createMutation.isPending || draft.description.trim() === ""
							}
							onClick={handleCreate}>
							{createMutation.isPending ? "…" : t.save}
						</button>
					</div>
				</div>
			) : (
				<button
					className="btn btn-secondary btn-sm"
					style={{ alignSelf: "flex-start" }}
					onClick={() => {
						setShowCreate(true);
					}}>
					＋ {t.newInviteKey}
				</button>
			)}
		</div>
	);
};

type InviteKeyRowProps = {
	readonly inviteKey: InviteKey;
	readonly copied: string | null;
	readonly t: Strings;
	readonly onCopy: (id: string) => void;
	readonly onRevoke: (id: string, desc: string) => void;
};

const InviteKeyRow = ({
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
						className="btn btn-ghost btn-xs"
						style={{ color: "var(--color-danger, #ef4444)", flexShrink: 0 }}
						title={t.revoke}
						onClick={() => {
							onRevoke(inviteKey.id, inviteKey.description);
						}}>
						✕
					</button>
				)}
			</div>

			<div style={{ display: "flex", alignItems: "center", gap: 8, flexWrap: "wrap" }}>
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
						{t.expiresAt}: {inviteKey.expiresAt.toLocaleString()}
					</span>
				)}
				{inviteKey.useLimit !== undefined && (
					<span>
						{t.useLimit}: {inviteKey.useLimit}
					</span>
				)}
				{inviteKey.createdAt !== undefined && (
					<span>作成: {inviteKey.createdAt.toLocaleDateString()}</span>
				)}
			</div>
		</div>
	);
};

const labelStyle: React.CSSProperties = {
	display: "block",
	fontSize: 12,
	fontWeight: 500,
	marginBottom: 4,
	color: "var(--color-text-muted)",
};

type InviteManagerProps = {
	readonly workGroups: WorkGroup[];
	readonly projectPrivilegeType?: "read" | "write" | "admin";
	readonly t: Strings;
};

export const InviteManager = ({
	workGroups,
	projectPrivilegeType,
	t,
}: InviteManagerProps) => {
	const isAdmin = projectPrivilegeType === "admin";
	const [selectedWGId, setSelectedWGId] = useState<string>(
		workGroups[0]?.id ?? ""
	);

	useEffect(() => {
		if (selectedWGId === "" && workGroups.length > 0) {
			setSelectedWGId(workGroups[0]?.id ?? "");
		}
	}, [workGroups, selectedWGId]);
	const [confirmRevoke, setConfirmRevoke] = useState<{
		id: string;
		desc: string;
	} | null>(null);

	const deleteMutation = useDeleteInviteKey(selectedWGId);

	const handleRevoke = (id: string, desc: string) => {
		setConfirmRevoke({ id, desc });
	};

	const confirmRevokeAction = () => {
		if (confirmRevoke === null) return;
		deleteMutation.mutate(confirmRevoke.id, {
			onError: (e) => {
				alert((e as Error).message);
			},
		});
		setConfirmRevoke(null);
	};

	return (
		<div style={{ padding: "24px 28px", maxWidth: 720 }}>
			<div
				style={{
					display: "flex",
					alignItems: "center",
					marginBottom: 24,
					gap: 12,
				}}>
				<h1 style={{ fontSize: 22, fontWeight: 700, letterSpacing: "-0.3px" }}>
					{t.inviteManager}
				</h1>
			</div>

			{!isAdmin && (
				<div
					style={{
						padding: "12px 16px",
						marginBottom: 24,
						background: "var(--color-warning-bg, #fefce8)",
						border: "1px solid var(--color-warning, #eab308)",
						borderRadius: 6,
						fontSize: 13,
						color: "var(--color-text)",
					}}>
					招待キーの管理には管理者権限が必要です。
				</div>
			)}

			{isAdmin && (
				<>
					{workGroups.length === 0 ? (
						<div
							style={{
								color: "var(--color-text-muted)",
								fontSize: 13,
								marginBottom: 24,
							}}>
							ワークグループがありません。先にワークグループを作成してください。
						</div>
					) : (
						<div style={{ display: "flex", flexDirection: "column", gap: 20 }}>
							{workGroups.length > 1 && (
								<div>
									<label style={labelStyle}>ワークグループ</label>
									<select
										className="form-input"
										style={{ maxWidth: 300 }}
										value={selectedWGId}
										onChange={(e) => {
											setSelectedWGId(e.target.value);
										}}>
										{workGroups.map((wg) => (
											<option
												key={wg.id}
												value={wg.id}>
												{wg.name}
											</option>
										))}
									</select>
								</div>
							)}

							{workGroups.length === 1 && (
								<div
									style={{
										fontSize: 13,
										color: "var(--color-text-muted)",
										marginBottom: 4,
									}}>
									ワークグループ: <strong>{workGroups[0]?.name}</strong>
								</div>
							)}

							{selectedWGId !== "" && (
								<WGInviteKeys
									key={selectedWGId}
									workGroupId={selectedWGId}
									t={t}
									onRevoke={handleRevoke}
								/>
							)}
						</div>
					)}
				</>
			)}

			{confirmRevoke !== null && (
				<div
					className="modal-backdrop"
					onClick={(e) =>
						e.target === e.currentTarget && setConfirmRevoke(null)
					}>
					<div
						className="modal"
						style={{ maxWidth: 420 }}>
						<div className="modal-header">
							<span className="modal-title">{t.revoke}</span>
							<button
								className="btn btn-ghost btn-sm"
								onClick={() => {
									setConfirmRevoke(null);
								}}>
								✕
							</button>
						</div>
						<div
							className="modal-body"
							style={{ fontSize: 13 }}>
							「{confirmRevoke.desc}」を無効化します。この操作は取り消せません。
						</div>
						<div className="modal-footer">
							<button
								className="btn btn-secondary"
								onClick={() => {
									setConfirmRevoke(null);
								}}>
								{t.cancel}
							</button>
							<button
								className="btn btn-danger"
								disabled={deleteMutation.isPending}
								onClick={confirmRevokeAction}>
								{deleteMutation.isPending ? "…" : t.revoke}
							</button>
						</div>
					</div>
				</div>
			)}
		</div>
	);
};
