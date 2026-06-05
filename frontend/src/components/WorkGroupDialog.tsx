import { useState } from "react";

import type { Strings } from "../i18n/strings";
import type { WorkGroup } from "../types/model";

type WorkGroupDraft = Partial<Pick<WorkGroup, "id">> &
	Pick<WorkGroup, "name" | "description">;

type WorkGroupDialogProps = {
	readonly workGroup?: WorkGroup | null;
	readonly onSave: (d: WorkGroupDraft) => void;
	readonly onClose: () => void;
	readonly t: Strings;
};

export const WorkGroupDialog = ({
	workGroup,
	onSave,
	onClose,
	t,
}: WorkGroupDialogProps) => {
	const isNew = workGroup == null;
	const [d, setD] = useState<WorkGroupDraft>(
		workGroup ?? { name: "", description: "" }
	);
	const set = <K extends keyof WorkGroupDraft>(k: K, v: WorkGroupDraft[K]) => {
		setD((p) => ({ ...p, [k]: v }));
	};
	const valid = !!(d.name?.trim() !== "");

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div
				className="modal"
				style={{ maxWidth: 520 }}>
				<div className="modal-header">
					<span className="modal-title">
						{`
						📂 `}
						{isNew ? "ワークグループを新規作成" : "ワークグループを編集"}
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
						style={{ marginBottom: 14 }}>
						<label>{`WG名 *`}</label>
						<input
							value={d.name}
							onChange={(e) => {
								set("name", e.target.value);
							}}
							placeholder="例: 平日ダイヤ"
							autoFocus
						/>
					</div>
					<div className="field">
						<label>{t.description}</label>
						<textarea
							value={d.description}
							onChange={(e) => {
								set("description", e.target.value);
							}}
							rows={3}
							placeholder="例: 月〜金 運転"
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
