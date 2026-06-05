import { useState } from "react";

import { useDeleteInviteKey } from "../api/hooks/useInviteKeys";

import { WGInviteKeys } from "./WGInviteKeys";
import { labelStyle } from "./inviteShared";

import type { Strings } from "../i18n/strings";
import type { WorkGroup } from "../types/entities";

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
	// Track the user's explicit selection; fall back to the first WG when empty.
	const [preferredWGId, setPreferredWGId] = useState<string>("");
	const selectedWGId =
		preferredWGId !== "" ? preferredWGId : (workGroups[0]?.id ?? "");
	const setSelectedWGId = setPreferredWGId;
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
					}}>{`
					招待キーの管理には管理者権限が必要です。
				`}</div>
			)}

			{isAdmin ? (
				<>
					{workGroups.length === 0 ? (
						<div
							style={{
								color: "var(--color-text-muted)",
								fontSize: 13,
								marginBottom: 24,
							}}>{`
							ワークグループがありません。先にワークグループを作成してください。
						`}</div>
					) : (
						<div style={{ display: "flex", flexDirection: "column", gap: 20 }}>
							{workGroups.length > 1 && (
								<div>
									<label style={labelStyle}>{`ワークグループ`}</label>
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
									{`
									ワークグループ: `}
									<strong>{workGroups[0]?.name}</strong>
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
			) : null}

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
								type="button"
								className="btn btn-ghost btn-sm"
								onClick={() => {
									setConfirmRevoke(null);
								}}>{`
								✕
							`}</button>
						</div>
						<div
							className="modal-body"
							style={{ fontSize: 13 }}>
							{`
							「`}
							{confirmRevoke.desc}
							{`」を無効化します。この操作は取り消せません。
						`}
						</div>
						<div className="modal-footer">
							<button
								type="button"
								className="btn btn-secondary"
								onClick={() => {
									setConfirmRevoke(null);
								}}>
								{t.cancel}
							</button>
							<button
								type="button"
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
