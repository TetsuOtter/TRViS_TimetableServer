import { useState } from "react";

import { useCreateInviteKey, useInviteKeys } from "../api/hooks/useInviteKeys";

import { InviteKeyRow } from "./InviteKeyRow";
import { EMPTY_DRAFT, keyStatus, labelStyle } from "./inviteShared";

import type { CreateDraft, PrivilegeLevel } from "./inviteShared";
import type { Strings } from "../i18n/strings";

type WGInviteKeysProps = {
	readonly workGroupId: string;
	readonly t: Strings;
	readonly onRevoke: (keyId: string, keyDesc: string) => void;
};

export const WGInviteKeys = ({
	workGroupId,
	t,
	onRevoke,
}: WGInviteKeysProps) => {
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
			<div style={{ color: "var(--color-text-muted)", fontSize: 13 }}>{`
				読み込み中…
			`}</div>
		);
	}

	const activeKeys = (keys ?? []).filter((k) => keyStatus(k, t) === null);
	const inactiveKeys = (keys ?? []).filter((k) => keyStatus(k, t) !== null);

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
						{`
						無効・期限切れ (`}
						{inactiveKeys.length}
						{`)
					`}
					</summary>
					<div
						style={{
							display: "flex",
							flexDirection: "column",
							gap: 8,
							marginTop: 8,
						}}>
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
						<label style={labelStyle}>{`説明 *`}</label>
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
						<label style={labelStyle}>
							{t.expiresAt}
							{`
							（任意）
						`}
						</label>
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
						<label style={labelStyle}>
							{t.useLimit}
							{`
							（任意）
						`}
						</label>
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
							type="button"
							className="btn btn-secondary btn-sm"
							onClick={() => {
								setShowCreate(false);
								setDraft(EMPTY_DRAFT);
							}}>
							{t.cancel}
						</button>
						<button
							type="button"
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
					type="button"
					className="btn btn-secondary btn-sm"
					style={{ alignSelf: "flex-start" }}
					onClick={() => {
						setShowCreate(true);
					}}>
					{`
					＋ `}
					{t.newInviteKey}
				</button>
			)}
		</div>
	);
};
