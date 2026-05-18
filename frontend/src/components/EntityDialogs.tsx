// EntityDialogs — reusable create/edit dialogs for Project / WorkGroup / Work,
// plus a generic ConfirmDialog and a tiny ContextMenu. Ported from EntityDialogs.jsx.
import { useEffect, useState } from "react";

import type { Strings } from "../i18n/strings";
import type { Project, Work, WorkGroup } from "../types/model";

type ProjectDraft = Partial<Pick<Project, "id">> &
	Pick<Project, "name" | "description">;
type WorkGroupDraft = Partial<Pick<WorkGroup, "id">> &
	Pick<WorkGroup, "name" | "description">;
type WorkDraft = Partial<Pick<Work, "id">> &
	Pick<Work, "name" | "affectDate" | "remarks">;

/* ─── Generic confirmation ──────────────────────────────────────────────── */
interface ConfirmDialogProps {
	title: string;
	message: string;
	confirmLabel?: string;
	danger?: boolean;
	onConfirm: () => void;
	onClose: () => void;
}

export function ConfirmDialog({
	title,
	message,
	confirmLabel = "削除",
	danger = true,
	onConfirm,
	onClose,
}: ConfirmDialogProps) {
	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div className="modal" style={{ maxWidth: 420 }}>
				<div className="modal-header">
					<span className="modal-title">{title}</span>
					<button className="btn btn-ghost btn-sm" onClick={onClose}>
						✕
					</button>
				</div>
				<div
					className="modal-body"
					style={{ fontSize: 13, lineHeight: 1.6, color: "var(--color-text)" }}>
					{message}
				</div>
				<div className="modal-footer">
					<button className="btn btn-secondary" onClick={onClose}>
						キャンセル
					</button>
					<button
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
}

/* ─── Project create/edit ───────────────────────────────────────────────── */
interface ProjectDialogProps {
	project?: Project | null;
	onSave: (d: ProjectDraft) => void;
	onClose: () => void;
	t: Strings;
}

export function ProjectDialog({
	project,
	onSave,
	onClose,
	t,
}: ProjectDialogProps) {
	const isNew = !project;
	const [d, setD] = useState<ProjectDraft>(
		project || { name: "", description: "" }
	);
	const set = <K extends keyof ProjectDraft>(k: K, v: ProjectDraft[K]) =>
		setD((p) => ({ ...p, [k]: v }));
	const valid = !!d.name?.trim();

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div className="modal" style={{ maxWidth: 520 }}>
				<div className="modal-header">
					<span className="modal-title">
						📁 {isNew ? t.newProject : "プロジェクトを編集"}
					</span>
					<button className="btn btn-ghost btn-sm" onClick={onClose}>
						✕
					</button>
				</div>
				<div className="modal-body">
					<div className="field" style={{ marginBottom: 14 }}>
						<label>プロジェクト名 *</label>
						<input
							value={d.name || ""}
							onChange={(e) => set("name", e.target.value)}
							placeholder="例: 東海道本線 ダイヤ2024"
							autoFocus
						/>
					</div>
					<div className="field">
						<label>{t.description}</label>
						<textarea
							value={d.description || ""}
							onChange={(e) => set("description", e.target.value)}
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
					<button className="btn btn-secondary" onClick={onClose}>
						{t.cancel}
					</button>
					<button
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
}

/* ─── WorkGroup create/edit ─────────────────────────────────────────────── */
interface WorkGroupDialogProps {
	workGroup?: WorkGroup | null;
	onSave: (d: WorkGroupDraft) => void;
	onClose: () => void;
	t: Strings;
}

export function WorkGroupDialog({
	workGroup,
	onSave,
	onClose,
	t,
}: WorkGroupDialogProps) {
	const isNew = !workGroup;
	const [d, setD] = useState<WorkGroupDraft>(
		workGroup || { name: "", description: "" }
	);
	const set = <K extends keyof WorkGroupDraft>(k: K, v: WorkGroupDraft[K]) =>
		setD((p) => ({ ...p, [k]: v }));
	const valid = !!d.name?.trim();

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div className="modal" style={{ maxWidth: 520 }}>
				<div className="modal-header">
					<span className="modal-title">
						📂 {isNew ? "ワークグループを新規作成" : "ワークグループを編集"}
					</span>
					<button className="btn btn-ghost btn-sm" onClick={onClose}>
						✕
					</button>
				</div>
				<div className="modal-body">
					<div className="field" style={{ marginBottom: 14 }}>
						<label>WG名 *</label>
						<input
							value={d.name || ""}
							onChange={(e) => set("name", e.target.value)}
							placeholder="例: 平日ダイヤ"
							autoFocus
						/>
					</div>
					<div className="field">
						<label>{t.description}</label>
						<textarea
							value={d.description || ""}
							onChange={(e) => set("description", e.target.value)}
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
					<button className="btn btn-secondary" onClick={onClose}>
						{t.cancel}
					</button>
					<button
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
}

/* ─── Work create/edit ──────────────────────────────────────────────────── */
interface WorkDialogProps {
	work?: Work | null;
	onSave: (d: WorkDraft) => void;
	onClose: () => void;
	t: Strings;
}

export function WorkDialog({ work, onSave, onClose, t }: WorkDialogProps) {
	const isNew = !work;
	const [d, setD] = useState<WorkDraft>(
		work || {
			name: "",
			affectDate: new Date().toISOString().slice(0, 10),
			remarks: "",
		}
	);
	const set = <K extends keyof WorkDraft>(k: K, v: WorkDraft[K]) =>
		setD((p) => ({ ...p, [k]: v }));
	const valid = !!d.name?.trim();

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div className="modal" style={{ maxWidth: 560 }}>
				<div className="modal-header">
					<span className="modal-title">
						📋 {isNew ? `${t.newWork}を作成` : "ワークを編集"}
					</span>
					<button className="btn btn-ghost btn-sm" onClick={onClose}>
						✕
					</button>
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
							<label>ワーク名 *</label>
							<input
								value={d.name || ""}
								onChange={(e) => set("name", e.target.value)}
								placeholder="例: 2024年3月改正"
								autoFocus
							/>
						</div>
						<div className="field">
							<label>{t.affectDate}</label>
							<input
								type="date"
								value={d.affectDate || ""}
								onChange={(e) => set("affectDate", e.target.value)}
							/>
						</div>
					</div>
					<div className="field">
						<label>{t.remarks}</label>
						<textarea
							value={d.remarks || ""}
							onChange={(e) => set("remarks", e.target.value)}
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
					<button className="btn btn-secondary" onClick={onClose}>
						{t.cancel}
					</button>
					<button
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
}

/* ─── Tiny context menu (right-click / kebab) ───────────────────────────── */
export interface ContextMenuItem {
	icon: string;
	label: string;
	danger?: boolean;
	onClick: () => void;
}

interface ContextMenuProps {
	x: number;
	y: number;
	items: ContextMenuItem[];
	onClose: () => void;
}

export function ContextMenu({ x, y, items, onClose }: ContextMenuProps) {
	useEffect(() => {
		const close = () => onClose();
		window.addEventListener("click", close);
		window.addEventListener("contextmenu", close);
		window.addEventListener("keydown", close);
		return () => {
			window.removeEventListener("click", close);
			window.removeEventListener("contextmenu", close);
			window.removeEventListener("keydown", close);
		};
	}, [onClose]);

	// Clamp to viewport
	const W = 180,
		H = items.length * 32 + 8;
	const left = Math.min(x, window.innerWidth - W - 8);
	const top = Math.min(y, window.innerHeight - H - 8);

	return (
		<div
			onClick={(e) => e.stopPropagation()}
			onContextMenu={(e) => {
				e.preventDefault();
				e.stopPropagation();
			}}
			style={{
				position: "fixed",
				left,
				top,
				zIndex: 200,
				minWidth: W,
				padding: 4,
				background: "var(--color-content)",
				border: "1px solid var(--color-border)",
				borderRadius: "var(--radius)",
				boxShadow: "var(--shadow-lg)",
			}}>
			{items.map((it, i) => (
				<button
					key={i}
					onClick={() => {
						it.onClick();
						onClose();
					}}
					style={{
						display: "flex",
						alignItems: "center",
						gap: 8,
						width: "100%",
						padding: "7px 10px",
						borderRadius: 4,
						fontSize: 13,
						textAlign: "left",
						color: it.danger ? "var(--color-danger)" : "var(--color-text)",
						background: "transparent",
						border: "none",
						cursor: "pointer",
					}}
					onMouseEnter={(e) =>
						(e.currentTarget.style.background = "var(--color-bg)")
					}
					onMouseLeave={(e) =>
						(e.currentTarget.style.background = "transparent")
					}>
					<span style={{ width: 14, opacity: 0.8 }}>{it.icon}</span>
					<span>{it.label}</span>
				</button>
			))}
		</div>
	);
}
