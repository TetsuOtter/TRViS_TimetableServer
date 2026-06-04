import { useState } from "react";

import { useUseInviteKey } from "../api/hooks/useInviteKeys";

import type { Strings } from "../i18n/strings";

type Props = {
	readonly onClose: () => void;
	readonly t: Strings;
};

export const UseInviteKeyDialog = ({ onClose, t }: Props) => {
	const [keyId, setKeyId] = useState("");
	const [joined, setJoined] = useState(false);
	const { mutate, isPending, error } = useUseInviteKey();

	const handleSubmit = () => {
		const id = keyId.trim();
		if (id === "") return;
		mutate(id, {
			onSuccess: () => {
				setJoined(true);
			},
		});
	};

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div
				className="modal"
				style={{ maxWidth: 480 }}>
				<div className="modal-header">
					<span className="modal-title">{t.useInviteKey}</span>
					<button
						className="btn btn-ghost btn-sm"
						onClick={onClose}>
						✕
					</button>
				</div>
				<div className="modal-body">
					{joined ? (
						<div style={{ color: "var(--color-success, #22c55e)", fontSize: 14 }}>
							プロジェクトに参加しました。プロジェクト一覧を更新してご確認ください。
						</div>
					) : (
						<div style={{ display: "flex", flexDirection: "column", gap: 12 }}>
							<div style={{ fontSize: 13, color: "var(--color-text-muted)" }}>
								招待キーのID (UUID) を入力してください。
							</div>
							<input
								className="form-input"
								value={keyId}
								onChange={(e) => {
									setKeyId(e.target.value);
								}}
								placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
								autoFocus
								onKeyDown={(e) => {
									if (e.key === "Enter") handleSubmit();
								}}
							/>
							{error !== null && (
								<div style={{ color: "var(--color-danger, #ef4444)", fontSize: 13 }}>
									{(error as Error).message}
								</div>
							)}
						</div>
					)}
				</div>
				<div className="modal-footer">
					{joined ? (
						<button
							className="btn btn-primary"
							onClick={onClose}>
							閉じる
						</button>
					) : (
						<>
							<button
								className="btn btn-secondary"
								onClick={onClose}>
								{t.cancel}
							</button>
							<button
								className="btn btn-primary"
								disabled={isPending || keyId.trim() === ""}
								onClick={handleSubmit}>
								{isPending ? "…" : "参加する"}
							</button>
						</>
					)}
				</div>
			</div>
		</div>
	);
};
