// BBCodeEditorDialog — Full BBCode editor dialog
import { useState, useRef, useEffect } from "react";
import type { CSSProperties } from "react";

import { BBCodePreview } from "./BBCodePreview";
import { BBCodeToolbar } from "./BBCodeToolbar";

type EditorTab = "edit" | "preview" | "split";

export type BBCodeEditorDialogProps = {
	readonly title: string;
	readonly value: string;
	readonly onSave: (v: string) => void;
	readonly onClose: () => void;
	readonly multiline?: boolean;
};

export const BBCodeEditorDialog = ({
	title,
	value,
	onSave,
	onClose,
	multiline = true,
}: BBCodeEditorDialogProps) => {
	const [draft, setDraft] = useState(value !== "" ? value : "");
	const [tab, setTab] = useState<EditorTab>("edit");
	const textareaRef = useRef<HTMLTextAreaElement>(null);

	// Close on Escape
	useEffect(() => {
		const handler = (e: KeyboardEvent) => {
			if (e.key === "Escape") onClose();
		};
		window.addEventListener("keydown", handler);
		return () => {
			window.removeEventListener("keydown", handler);
		};
	}, [onClose]);

	const tabBtn = (id: EditorTab, label: string) => (
		<button
			type="button"
			onClick={() => {
				setTab(id);
			}}
			style={{
				padding: "4px 12px",
				fontSize: 12,
				border: "none",
				cursor: "pointer",
				borderRadius: 4,
				background: tab === id ? "var(--color-accent)" : "transparent",
				color: tab === id ? "#fff" : "var(--color-text-muted)",
				fontWeight: tab === id ? 600 : 400,
			}}>
			{label}
		</button>
	);

	const sharedTextareaStyle: CSSProperties = {
		width: "100%",
		flex: 1,
		resize: "none",
		border: "none",
		outline: "none",
		fontFamily: "var(--font-mono)",
		fontSize: 13,
		lineHeight: 1.7,
		background: "var(--color-content)",
		color: "var(--color-text)",
		padding: "10px 12px",
		borderRadius: "0 0 var(--radius) var(--radius)",
	};

	const previewStyle: CSSProperties = {
		flex: 1,
		overflowY: "auto",
		padding: "12px 14px",
		background: "var(--color-bg)",
		fontSize: 14,
		lineHeight: 1.8,
		borderRadius: "0 0 var(--radius) var(--radius)",
		minHeight: 80,
	};

	const handleSave = onSave;

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div
				className="modal"
				style={{
					maxWidth: 640,
					width: "95vw",
					display: "flex",
					flexDirection: "column",
					maxHeight: "85vh",
				}}>
				{/* Header */}
				<div className="modal-header">
					<span className="modal-title">
						{`✏️ `}
						{title}
					</span>
					<button
						type="button"
						className="btn btn-ghost btn-sm"
						onClick={onClose}>{`
						✕
					`}</button>
				</div>

				{/* Tab bar */}
				<div
					style={{
						display: "flex",
						gap: 2,
						alignItems: "center",
						padding: "6px 12px",
						borderBottom: "1px solid var(--color-border)",
						background: "var(--color-bg)",
					}}>
					{tabBtn("edit", "編集")}
					{tabBtn("preview", "プレビュー")}
					{tabBtn("split", "分割")}
					<span style={{ flex: 1 }} />
					<span style={{ fontSize: 11, color: "var(--color-text-muted)" }}>{`
						BBコード対応
					`}</span>
				</div>

				{/* Body */}
				<div
					className="modal-body"
					style={{
						padding: 0,
						flex: 1,
						overflow: "hidden",
						display: "flex",
						flexDirection: "column",
					}}>
					{/* Edit only */}
					{tab === "edit" && (
						<div
							style={{
								display: "flex",
								flexDirection: "column",
								flex: 1,
								overflow: "hidden",
							}}>
							<BBCodeToolbar
								textareaRef={textareaRef}
								value={draft}
								onChange={setDraft}
							/>
							<textarea
								ref={textareaRef}
								value={draft}
								onChange={(e) => {
									setDraft(e.target.value);
								}}
								rows={multiline ? 8 : 2}
								style={{
									...sharedTextareaStyle,
									minHeight: multiline ? 180 : 60,
								}}
								autoFocus
							/>
						</div>
					)}

					{/* Preview only */}
					{tab === "preview" && (
						<div style={previewStyle}>
							{draft !== "" ? (
								<BBCodePreview value={draft} />
							) : (
								<span style={{ opacity: 0.35, fontStyle: "italic" }}>{`
									（内容なし）
								`}</span>
							)}
						</div>
					)}

					{/* Split */}
					{tab === "split" && (
						<div
							style={{ display: "flex", flex: 1, overflow: "hidden", gap: 0 }}>
							<div
								style={{
									flex: 1,
									display: "flex",
									flexDirection: "column",
									overflow: "hidden",
									borderRight: "1px solid var(--color-border)",
								}}>
								<BBCodeToolbar
									textareaRef={textareaRef}
									value={draft}
									onChange={setDraft}
								/>
								<textarea
									ref={textareaRef}
									value={draft}
									onChange={(e) => {
										setDraft(e.target.value);
									}}
									style={{
										...sharedTextareaStyle,
										minHeight: 160,
										flex: 1,
									}}
									autoFocus
								/>
							</div>
							<div
								style={{
									flex: 1,
									overflow: "auto",
									padding: "12px 14px",
									background: "var(--color-bg)",
									fontSize: 14,
									lineHeight: 1.8,
								}}>
								<div
									style={{
										fontSize: 10,
										color: "var(--color-text-muted)",
										marginBottom: 6,
										fontWeight: 600,
										letterSpacing: "0.05em",
									}}>{`
									プレビュー
								`}</div>
								{draft !== "" ? (
									<BBCodePreview value={draft} />
								) : (
									<span
										style={{
											opacity: 0.35,
											fontStyle: "italic",
											fontSize: 13,
										}}>{`
										（内容なし）
									`}</span>
								)}
							</div>
						</div>
					)}
				</div>

				{/* Footer */}
				<div className="modal-footer">
					<span
						style={{
							fontSize: 11,
							color: "var(--color-text-muted)",
							flex: 1,
						}}>
						{draft.length}
						{` 文字
						`}
						{/\[[^\]]*\]/.test(draft) && (
							<span
								style={{
									marginLeft: 8,
									color: "var(--color-accent)",
									fontWeight: 500,
								}}>{`
								• BBコード使用中
							`}</span>
						)}
					</span>
					<button
						type="button"
						className="btn btn-secondary"
						onClick={onClose}>{`
						キャンセル
					`}</button>
					<button
						type="button"
						className="btn btn-primary"
						onClick={() => {
							handleSave(draft);
							onClose();
						}}>{`
						保存
					`}</button>
				</div>
			</div>
		</div>
	);
};
