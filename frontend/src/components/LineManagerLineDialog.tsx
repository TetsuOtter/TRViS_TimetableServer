// LineManagerLineDialog.tsx — Line edit dialog (create / edit)
import { useState } from "react";

import type { LineDraft } from "./LineManagerTypes";
import type { Strings } from "../i18n/strings";
import type { Line } from "../types/model";

export type LineDialogProps = {
	readonly line: Partial<Line>;
	readonly onSave: (line: LineDraft) => void;
	readonly onDelete: (id: string) => void;
	readonly onClose: () => void;
	readonly t: Strings;
};

export const LineDialog = ({
	line,
	onSave,
	onDelete,
	onClose,
	t,
}: LineDialogProps) => {
	const [d, setD] = useState<LineDraft>({
		name: line?.name ?? "",
		description: line?.description ?? "",
	});
	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div
				className="modal"
				style={{ maxWidth: 460 }}>
				<div className="modal-header">
					<span className="modal-title">
						{`
						🛤 `}
						{line?.id != null ? t.edit : t.addLine}
					</span>
					<button
						type="button"
						className="btn btn-ghost btn-sm"
						onClick={onClose}>{`
						✕
					`}</button>
				</div>
				<div className="modal-body">
					<div
						className="field"
						style={{ marginBottom: 12 }}>
						<label>{`路線名`}</label>
						<input
							value={d.name}
							onChange={(e) => {
								setD((p) => ({ ...p, name: e.target.value }));
							}}
							placeholder="例: 東海道本線"
							autoFocus
						/>
					</div>
					<div className="field">
						<label>{t.description}</label>
						<textarea
							value={d.description}
							onChange={(e) => {
								setD((p) => ({ ...p, description: e.target.value }));
							}}
							rows={3}
							placeholder="例: 東京〜小田原間"
							style={{
								width: "100%",
								padding: 8,
								border: "1px solid var(--color-border)",
								borderRadius: "var(--radius)",
								background: "var(--color-content)",
								color: "var(--color-text)",
								fontFamily: "inherit",
								fontSize: 13,
								resize: "vertical",
								outline: "none",
								lineHeight: 1.5,
							}}
						/>
					</div>
				</div>
				<div className="modal-footer">
					{line?.id != null ? (
						<button
							type="button"
							className="btn btn-danger btn-sm"
							style={{ marginRight: "auto" }}
							onClick={() => {
								if (confirm(`「${line.name}」を削除しますか？`)) {
									onDelete(line.id ?? "");
									onClose();
								}
							}}>
							{`
							🗑 `}
							{t.delete}
						</button>
					) : null}
					<button
						type="button"
						className="btn btn-secondary"
						onClick={onClose}>
						{t.cancel}
					</button>
					<button
						type="button"
						className="btn btn-primary"
						onClick={() => {
							if (!(d.name.trim() !== "")) return;
							onSave(d);
							onClose();
						}}>
						{t.save}
					</button>
				</div>
			</div>
		</div>
	);
};
