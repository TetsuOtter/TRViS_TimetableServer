// EntityDialogs — reusable create/edit dialogs for Project / WorkGroup / Work,
// plus a generic ConfirmDialog and a tiny ContextMenu. Ported from EntityDialogs.jsx.

/* ─── Generic confirmation ──────────────────────────────────────────────── */
type ConfirmDialogProps = {
	readonly title: string;
	readonly message: string;
	readonly confirmLabel?: string;
	readonly danger?: boolean;
	readonly onConfirm: () => void;
	readonly onClose: () => void;
};

export const ConfirmDialog = ({
	title,
	message,
	confirmLabel = "削除",
	danger = true,
	onConfirm,
	onClose,
}: ConfirmDialogProps) => {
	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div
				className="modal"
				style={{ maxWidth: 420 }}>
				<div className="modal-header">
					<span className="modal-title">{title}</span>
					<button
						type="button"
						className="btn btn-ghost btn-sm"
						onClick={onClose}>{`
						✕
					`}</button>
				</div>
				<div
					className="modal-body"
					style={{ fontSize: 13, lineHeight: 1.6, color: "var(--color-text)" }}>
					{message}
				</div>
				<div className="modal-footer">
					<button
						type="button"
						className="btn btn-secondary"
						onClick={onClose}>{`
						キャンセル
					`}</button>
					<button
						type="button"
						className={danger ? "btn btn-danger" : "btn btn-primary"}
						onClick={() => {
							onConfirm();
							onClose();
						}}>
						{confirmLabel}
					</button>
				</div>
			</div>
		</div>
	);
};

/* ─── Re-exports ────────────────────────────────────────────────────────── */
export { ProjectDialog } from "./ProjectDialog";
export { WorkGroupDialog } from "./WorkGroupDialog";
export { WorkDialog } from "./WorkDialog";
export { ContextMenu } from "./ContextMenu";
export type { ContextMenuItem } from "./ContextMenu";
