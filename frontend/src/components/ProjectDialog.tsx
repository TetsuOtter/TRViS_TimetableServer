import { useState } from "react";

import type { Strings } from "../i18n/strings";
import type { Project } from "../types/model";

type ProjectDraft = Partial<Pick<Project, "id">> &
	Pick<Project, "name" | "description">;

type ProjectDialogProps = {
	readonly project?: Project | null;
	readonly onSave: (d: ProjectDraft) => void;
	readonly onClose: () => void;
	readonly t: Strings;
};

export const ProjectDialog = ({
	project,
	onSave,
	onClose,
	t,
}: ProjectDialogProps) => {
	const isNew = project == null;
	const [d, setD] = useState<ProjectDraft>(
		project ?? { name: "", description: "" }
	);
	const set = <K extends keyof ProjectDraft>(k: K, v: ProjectDraft[K]) => {
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
						📁 `}
						{isNew ? t.newProject : "プロジェクトを編集"}
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
						<label>{`プロジェクト名 *`}</label>
						<input
							value={d.name}
							onChange={(e) => {
								set("name", e.target.value);
							}}
							placeholder="例: 東海道本線 ダイヤ2024"
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
							placeholder="例: 2024年3月改正ダイヤ"
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
