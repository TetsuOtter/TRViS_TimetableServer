import { useState } from "react";

import type { Strings } from "../i18n/strings";
import type { Work } from "../types/model";

type WorkDraft = Partial<Pick<Work, "id">> &
	Pick<Work, "name" | "affectDate" | "remarks">;

type WorkDialogProps = {
	readonly work?: Work | null;
	readonly onSave: (d: WorkDraft) => void;
	readonly onClose: () => void;
	readonly t: Strings;
};

export const WorkDialog = ({ work, onSave, onClose, t }: WorkDialogProps) => {
	const isNew = work == null;
	const [d, setD] = useState<WorkDraft>(
		work ?? {
			name: "",
			affectDate: new Date().toISOString().slice(0, 10),
			remarks: "",
		}
	);
	const set = <K extends keyof WorkDraft>(k: K, v: WorkDraft[K]) => {
		setD((p) => ({ ...p, [k]: v }));
	};
	const valid = !!(d.name?.trim() !== "");

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div
				className="modal"
				style={{ maxWidth: 560 }}>
				<div className="modal-header">
					<span className="modal-title">
						{`
						📋 `}
						{isNew ? `${t.newWork}を作成` : "ワークを編集"}
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
						style={{
							display: "grid",
							gridTemplateColumns: "2fr 1fr",
							gap: 12,
							marginBottom: 14,
						}}>
						<div className="field">
							<label>{`ワーク名 *`}</label>
							<input
								value={d.name}
								onChange={(e) => {
									set("name", e.target.value);
								}}
								placeholder="例: 2024年3月改正"
								autoFocus
							/>
						</div>
						<div className="field">
							<label>{t.affectDate}</label>
							<input
								type="date"
								value={d.affectDate}
								onChange={(e) => {
									set("affectDate", e.target.value);
								}}
							/>
						</div>
					</div>
					<div className="field">
						<label>{t.remarks}</label>
						<textarea
							value={d.remarks}
							onChange={(e) => {
								set("remarks", e.target.value);
							}}
							rows={3}
							placeholder="例: 春のダイヤ改正"
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
					<button
						type="button"
						className="btn btn-secondary"
						onClick={onClose}>
						{t.cancel}
					</button>
					<button
						type="button"
						className="btn btn-primary"
						disabled={!valid}
						style={{
							opacity: valid ? 1 : 0.5,
							pointerEvents: valid ? "auto" : "none",
						}}
						onClick={() => {
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
