// BBCodeEditor.tsx — Rich BBCode editor dialog + inline preview utilities
// Supports: [b], [i], [u], [s], [color=], [size=], [font=]  (with =false cancel)
import {
	useState,
	useRef,
	useEffect,
	useCallback,
	Fragment,
} from "react";
import type { CSSProperties, ReactNode, RefObject } from "react";

/* ─────────────────────────────────────────────────────────
   1. BBCode → React elements (preview renderer)
──────────────────────────────────────────────────────────── */
type TextToken = { type: "text"; value: string };
type TagToken = {
	type: "tag";
	close: boolean;
	name: string;
	attr: string | null;
};
type Token = TextToken | TagToken;

function parseBBCode(text: string): Token[] {
	if (!text) return [];
	// Tokenise into text segments and tags
	const tokens: Token[] = [];
	const re = /\[(\/?)(b|i|u|s|color|size|font)(?:=([^\]]*))?\]/gi;
	let last = 0;
	let m: RegExpExecArray | null;
	while ((m = re.exec(text)) !== null) {
		if (m.index > last)
			tokens.push({ type: "text", value: text.slice(last, m.index) });
		tokens.push({
			type: "tag",
			close: m[1] === "/",
			name: m[2]!.toLowerCase(),
			attr: m[3] || null,
		});
		last = m.index + m[0].length;
	}
	if (last < text.length) tokens.push({ type: "text", value: text.slice(last) });
	return tokens;
}

interface BBStyleState {
	bold?: boolean;
	italic?: boolean;
	underline?: boolean;
	strike?: boolean;
	color?: string;
	size?: number;
	font?: string;
}

export interface BBCodePreviewProps {
	value?: string;
	style?: CSSProperties;
}

export function BBCodePreview({ value, style }: BBCodePreviewProps) {
	const tokens = parseBBCode(value || "");
	// Walk tokens building a style stack
	const stack: BBStyleState[] = [{}]; // each entry: {bold, italic, underline, strike, color, size, font}
	const top = (): BBStyleState => stack[stack.length - 1]!;
	const push = (patch: BBStyleState) => stack.push({ ...top(), ...patch });
	const pop = () => {
		// Remove the topmost entry that introduced this property
		if (stack.length > 1) stack.pop();
	};

	const parts: ReactNode[] = [];
	let ki = 0;
	for (const tok of tokens) {
		if (tok.type === "text") {
			const s = top();
			const css: CSSProperties = {
				fontWeight: s.bold ? "bold" : undefined,
				fontStyle: s.italic ? "italic" : undefined,
				textDecoration:
					[s.underline && "underline", s.strike && "line-through"]
						.filter(Boolean)
						.join(" ") || undefined,
				color: s.color || undefined,
				fontSize: s.size ? `${s.size}px` : undefined,
				fontFamily: s.font || undefined,
			};
			// Remove undefined keys
			(Object.keys(css) as (keyof CSSProperties)[]).forEach(
				(k) => css[k] === undefined && delete css[k],
			);
			parts.push(
				<span key={ki++} style={css}>
					{tok.value}
				</span>,
			);
		} else {
			const { close, name, attr } = tok;
			if (close || attr === "false") {
				pop();
			} else {
				if (name === "b") push({ bold: true });
				else if (name === "i") push({ italic: true });
				else if (name === "u") push({ underline: true });
				else if (name === "s") push({ strike: true });
				else if (name === "color" && attr) {
					// attr may be "#RRGGBB" or "#RRGGBB dark=#RRGGBB"
					const light = attr.split(/\s+dark=/i)[0]!.trim();
					push({ color: light });
				} else if (name === "size" && attr) push({ size: parseFloat(attr) });
				else if (name === "font" && attr) push({ font: attr.trim() });
			}
		}
	}
	return (
		<span style={{ whiteSpace: "pre-wrap", ...style }}>
			{parts.length ? parts : value || ""}
		</span>
	);
}

/* ─────────────────────────────────────────────────────────
   2. Inline label — shows plain text with tags greyed out
──────────────────────────────────────────────────────────── */
export interface BBCodeInlineLabelProps {
	value?: string;
	style?: CSSProperties;
	emptyPlaceholder?: string;
}

export function BBCodeInlineLabel({
	value,
	style,
	emptyPlaceholder = "—",
}: BBCodeInlineLabelProps) {
	if (!value)
		return (
			<span style={{ opacity: 0.35, fontStyle: "italic", ...style }}>
				{emptyPlaceholder}
			</span>
		);
	// Highlight tags in muted colour, rest in normal
	const parts: ReactNode[] = [];
	const re = /(\[[^\]]*\])/g;
	let last = 0;
	let i = 0;
	let m: RegExpExecArray | null;
	while ((m = re.exec(value)) !== null) {
		if (m.index > last)
			parts.push(<span key={i++}>{value.slice(last, m.index)}</span>);
		parts.push(
			<span
				key={i++}
				style={{
					color: "var(--color-accent)",
					opacity: 0.55,
					fontSize: "0.85em",
				}}
			>
				{m[1]}
			</span>,
		);
		last = m.index + m[0].length;
	}
	if (last < value.length)
		parts.push(<span key={i++}>{value.slice(last)}</span>);
	return <span style={{ whiteSpace: "pre-wrap", ...style }}>{parts}</span>;
}

/* ─────────────────────────────────────────────────────────
   3. Tag insertion toolbar
──────────────────────────────────────────────────────────── */
interface ToolbarButton {
	label: string;
	style: CSSProperties;
	tag: string;
	title: string;
	noAttr: boolean;
}

const TOOLBAR_BUTTONS: ToolbarButton[] = [
	{ label: "B", style: { fontWeight: "bold" }, tag: "b", title: "太字", noAttr: true },
	{ label: "I", style: { fontStyle: "italic" }, tag: "i", title: "斜体", noAttr: true },
	{ label: "U", style: { textDecoration: "underline" }, tag: "u", title: "下線", noAttr: true },
	{ label: "S", style: { textDecoration: "line-through" }, tag: "s", title: "取消線", noAttr: true },
];

interface BBCodeToolbarProps {
	textareaRef: RefObject<HTMLTextAreaElement>;
	value: string;
	onChange: (v: string) => void;
}

function BBCodeToolbar({ textareaRef, value, onChange }: BBCodeToolbarProps) {
	const [colorPicker, setColorPicker] = useState(false);
	const [colorVal, setColorVal] = useState("#e74c3c");
	const [sizeVal, setSizeVal] = useState("14");
	const [fontVal, setFontVal] = useState("");
	const [showSize, setShowSize] = useState(false);
	const [showFont, setShowFont] = useState(false);

	const insertTag = useCallback(
		(open: string, close: string) => {
			const el = textareaRef.current;
			if (!el) return;
			const start = el.selectionStart;
			const end = el.selectionEnd;
			const sel = value.slice(start, end);
			const before = value.slice(0, start);
			const after = value.slice(end);
			const newVal = sel
				? `${before}${open}${sel}${close}${after}`
				: `${before}${open}${close}${after}`;
			onChange(newVal);
			// Restore cursor
			setTimeout(() => {
				el.focus();
				const cur = sel
					? start + open.length + sel.length + close.length
					: start + open.length;
				el.setSelectionRange(cur, cur);
			}, 0);
		},
		[textareaRef, value, onChange],
	);

	const insertSimple = (tag: string) => insertTag(`[${tag}]`, `[/${tag}]`);
	const insertColor = () => {
		insertTag(`[color=${colorVal}]`, `[/color]`);
		setColorPicker(false);
	};
	const insertSize = () => {
		const n = parseInt(sizeVal, 10);
		if (!n) return;
		insertTag(`[size=${n}]`, `[/size]`);
		setShowSize(false);
	};
	const insertFont = () => {
		const f = fontVal.trim();
		if (!f) return;
		insertTag(`[font=${f}]`, `[/font]`);
		setShowFont(false);
		setFontVal("");
	};

	const btnBase: CSSProperties = {
		padding: "3px 9px",
		borderRadius: 4,
		border: "1px solid var(--color-border)",
		background: "var(--color-bg)",
		color: "var(--color-text)",
		cursor: "pointer",
		fontSize: 13,
		lineHeight: 1.4,
		display: "inline-flex",
		alignItems: "center",
		gap: 3,
	};
	const popBase: CSSProperties = {
		position: "absolute",
		top: "100%",
		left: 0,
		zIndex: 200,
		marginTop: 4,
		background: "var(--color-content)",
		border: "1px solid var(--color-border)",
		borderRadius: "var(--radius)",
		boxShadow: "var(--shadow-lg)",
		padding: 10,
		display: "flex",
		flexDirection: "column",
		gap: 6,
		minWidth: 180,
	};

	return (
		<div
			style={{
				display: "flex",
				gap: 4,
				flexWrap: "wrap",
				padding: "6px 8px",
				borderBottom: "1px solid var(--color-border)",
				background: "var(--color-bg)",
				borderRadius: "var(--radius) var(--radius) 0 0",
			}}
		>
			{TOOLBAR_BUTTONS.map((btn) => (
				<button
					key={btn.tag}
					title={btn.title}
					style={{ ...btnBase, ...btn.style }}
					onMouseDown={(e) => {
						e.preventDefault();
						insertSimple(btn.tag);
					}}
				>
					{btn.label}
				</button>
			))}

			<div
				style={{
					width: 1,
					background: "var(--color-border)",
					margin: "0 2px",
					alignSelf: "stretch",
				}}
			/>

			{/* Color */}
			<div style={{ position: "relative" }}>
				<button
					title="フォント色"
					style={{ ...btnBase }}
					onMouseDown={(e) => {
						e.preventDefault();
						setColorPicker((v) => !v);
						setShowSize(false);
						setShowFont(false);
					}}
				>
					<span
						style={{
							display: "inline-block",
							width: 12,
							height: 12,
							borderRadius: 2,
							background: colorVal,
							border: "1px solid #0002",
							marginRight: 2,
						}}
					/>
					色
				</button>
				{colorPicker && (
					<div style={popBase}>
						<label style={{ fontSize: 12, color: "var(--color-text-muted)" }}>
							カラー (light)
						</label>
						<div style={{ display: "flex", gap: 6, alignItems: "center" }}>
							<input
								type="color"
								value={colorVal}
								onChange={(e) => setColorVal(e.target.value)}
								style={{
									width: 36,
									height: 28,
									border: "none",
									padding: 0,
									cursor: "pointer",
									background: "none",
								}}
							/>
							<input
								value={colorVal}
								onChange={(e) => setColorVal(e.target.value)}
								style={{
									flex: 1,
									padding: "4px 6px",
									border: "1px solid var(--color-border)",
									borderRadius: 4,
									fontFamily: "var(--font-mono)",
									fontSize: 12,
									background: "var(--color-bg)",
									color: "var(--color-text)",
								}}
							/>
						</div>
						{/* Preset swatches */}
						<div style={{ display: "flex", flexWrap: "wrap", gap: 4 }}>
							{[
								"#e74c3c",
								"#e67e22",
								"#f1c40f",
								"#2ecc71",
								"#3498db",
								"#9b59b6",
								"#1abc9c",
								"#000000",
								"#ffffff",
							].map((c) => (
								<button
									key={c}
									onMouseDown={(e) => {
										e.preventDefault();
										setColorVal(c);
									}}
									style={{
										width: 20,
										height: 20,
										borderRadius: 3,
										background: c,
										border:
											colorVal === c
												? "2px solid var(--color-accent)"
												: "1px solid #0003",
										cursor: "pointer",
										padding: 0,
									}}
								/>
							))}
						</div>
						<button
							style={{
								...btnBase,
								background: "var(--color-accent)",
								color: "#fff",
								border: "none",
								justifyContent: "center",
							}}
							onMouseDown={(e) => {
								e.preventDefault();
								insertColor();
							}}
						>
							挿入
						</button>
					</div>
				)}
			</div>

			{/* Size */}
			<div style={{ position: "relative" }}>
				<button
					title="フォントサイズ"
					style={btnBase}
					onMouseDown={(e) => {
						e.preventDefault();
						setShowSize((v) => !v);
						setColorPicker(false);
						setShowFont(false);
					}}
				>
					サイズ
				</button>
				{showSize && (
					<div style={popBase}>
						<label style={{ fontSize: 12, color: "var(--color-text-muted)" }}>
							フォントサイズ (px)
						</label>
						<div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
							{[10, 12, 14, 16, 18, 20, 24].map((s) => (
								<button
									key={s}
									onMouseDown={(e) => {
										e.preventDefault();
										setSizeVal(String(s));
									}}
									style={{
										...btnBase,
										background:
											sizeVal === String(s)
												? "var(--color-accent-bg)"
												: "var(--color-bg)",
										color:
											sizeVal === String(s)
												? "var(--color-accent)"
												: "var(--color-text)",
									}}
								>
									{s}
								</button>
							))}
						</div>
						<input
							type="number"
							value={sizeVal}
							onChange={(e) => setSizeVal(e.target.value)}
							min={6}
							max={72}
							style={{
								padding: "4px 6px",
								border: "1px solid var(--color-border)",
								borderRadius: 4,
								fontFamily: "var(--font-mono)",
								fontSize: 12,
								background: "var(--color-bg)",
								color: "var(--color-text)",
								width: "100%",
							}}
						/>
						<button
							style={{
								...btnBase,
								background: "var(--color-accent)",
								color: "#fff",
								border: "none",
								justifyContent: "center",
							}}
							onMouseDown={(e) => {
								e.preventDefault();
								insertSize();
							}}
						>
							挿入
						</button>
					</div>
				)}
			</div>

			{/* Font */}
			<div style={{ position: "relative" }}>
				<button
					title="フォントファミリー"
					style={btnBase}
					onMouseDown={(e) => {
						e.preventDefault();
						setShowFont((v) => !v);
						setColorPicker(false);
						setShowSize(false);
					}}
				>
					フォント
				</button>
				{showFont && (
					<div style={popBase}>
						<label style={{ fontSize: 12, color: "var(--color-text-muted)" }}>
							フォントファミリー
						</label>
						<div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
							{["Meiryo", "Yu Gothic", "MS Gothic", "Arial", "Times New Roman"].map(
								(f) => (
									<button
										key={f}
										onMouseDown={(e) => {
											e.preventDefault();
											setFontVal(f);
										}}
										style={{
											...btnBase,
											fontFamily: f,
											background:
												fontVal === f
													? "var(--color-accent-bg)"
													: "var(--color-bg)",
											color:
												fontVal === f
													? "var(--color-accent)"
													: "var(--color-text)",
											fontSize: 12,
										}}
									>
										{f}
									</button>
								),
							)}
						</div>
						<input
							value={fontVal}
							onChange={(e) => setFontVal(e.target.value)}
							placeholder="フォント名を入力"
							style={{
								padding: "4px 6px",
								border: "1px solid var(--color-border)",
								borderRadius: 4,
								fontSize: 12,
								background: "var(--color-bg)",
								color: "var(--color-text)",
								width: "100%",
								fontFamily: fontVal || "inherit",
							}}
						/>
						<button
							style={{
								...btnBase,
								background: "var(--color-accent)",
								color: "#fff",
								border: "none",
								justifyContent: "center",
							}}
							onMouseDown={(e) => {
								e.preventDefault();
								insertFont();
							}}
						>
							挿入
						</button>
					</div>
				)}
			</div>

			<div style={{ flex: 1 }} />

			{/* Remove all tags */}
			<button
				title="全タグを削除"
				style={{ ...btnBase, opacity: 0.6, fontSize: 11 }}
				onMouseDown={(e) => {
					e.preventDefault();
					onChange(value.replace(/\[[^\]]*\]/g, ""));
				}}
			>
				タグ削除
			</button>
		</div>
	);
}

/* ─────────────────────────────────────────────────────────
   4. Full BBCode Editor Dialog
──────────────────────────────────────────────────────────── */
type EditorTab = "edit" | "preview" | "split";

export interface BBCodeEditorDialogProps {
	title: string;
	value: string;
	onSave: (v: string) => void;
	onClose: () => void;
	multiline?: boolean;
}

export function BBCodeEditorDialog({
	title,
	value,
	onSave,
	onClose,
	multiline = true,
}: BBCodeEditorDialogProps) {
	const [draft, setDraft] = useState(value || "");
	const [tab, setTab] = useState<EditorTab>("edit"); // 'edit' | 'preview' | 'split'
	const textareaRef = useRef<HTMLTextAreaElement>(null);

	// Close on Escape
	useEffect(() => {
		const handler = (e: KeyboardEvent) => {
			if (e.key === "Escape") onClose();
		};
		window.addEventListener("keydown", handler);
		return () => window.removeEventListener("keydown", handler);
	}, [onClose]);

	const tabBtn = (id: EditorTab, label: string) => (
		<button
			onClick={() => setTab(id)}
			style={{
				padding: "4px 12px",
				fontSize: 12,
				border: "none",
				cursor: "pointer",
				borderRadius: 4,
				background: tab === id ? "var(--color-accent)" : "transparent",
				color: tab === id ? "#fff" : "var(--color-text-muted)",
				fontWeight: tab === id ? 600 : 400,
			}}
		>
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

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}
		>
			<div
				className="modal"
				style={{
					maxWidth: 640,
					width: "95vw",
					display: "flex",
					flexDirection: "column",
					maxHeight: "85vh",
				}}
			>
				{/* Header */}
				<div className="modal-header">
					<span className="modal-title">✏️ {title}</span>
					<button className="btn btn-ghost btn-sm" onClick={onClose}>
						✕
					</button>
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
					}}
				>
					{tabBtn("edit", "編集")}
					{tabBtn("preview", "プレビュー")}
					{tabBtn("split", "分割")}
					<span style={{ flex: 1 }} />
					<span style={{ fontSize: 11, color: "var(--color-text-muted)" }}>
						BBコード対応
					</span>
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
					}}
				>
					{/* Edit only */}
					{tab === "edit" && (
						<div
							style={{
								display: "flex",
								flexDirection: "column",
								flex: 1,
								overflow: "hidden",
							}}
						>
							<BBCodeToolbar
								textareaRef={textareaRef}
								value={draft}
								onChange={setDraft}
							/>
							<textarea
								ref={textareaRef}
								value={draft}
								onChange={(e) => setDraft(e.target.value)}
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
							{draft ? (
								<BBCodePreview value={draft} />
							) : (
								<span style={{ opacity: 0.35, fontStyle: "italic" }}>
									（内容なし）
								</span>
							)}
						</div>
					)}

					{/* Split */}
					{tab === "split" && (
						<div
							style={{ display: "flex", flex: 1, overflow: "hidden", gap: 0 }}
						>
							<div
								style={{
									flex: 1,
									display: "flex",
									flexDirection: "column",
									overflow: "hidden",
									borderRight: "1px solid var(--color-border)",
								}}
							>
								<BBCodeToolbar
									textareaRef={textareaRef}
									value={draft}
									onChange={setDraft}
								/>
								<textarea
									ref={textareaRef}
									value={draft}
									onChange={(e) => setDraft(e.target.value)}
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
								}}
							>
								<div
									style={{
										fontSize: 10,
										color: "var(--color-text-muted)",
										marginBottom: 6,
										fontWeight: 600,
										letterSpacing: "0.05em",
									}}
								>
									プレビュー
								</div>
								{draft ? (
									<BBCodePreview value={draft} />
								) : (
									<span
										style={{
											opacity: 0.35,
											fontStyle: "italic",
											fontSize: 13,
										}}
									>
										（内容なし）
									</span>
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
						}}
					>
						{draft.length} 文字
						{/\[[^\]]*\]/.test(draft) && (
							<span
								style={{
									marginLeft: 8,
									color: "var(--color-accent)",
									fontWeight: 500,
								}}
							>
								• BBコード使用中
							</span>
						)}
					</span>
					<button className="btn btn-secondary" onClick={onClose}>
						キャンセル
					</button>
					<button
						className="btn btn-primary"
						onClick={() => {
							onSave(draft);
							onClose();
						}}
					>
						保存
					</button>
				</div>
			</div>
		</div>
	);
}

/* ─────────────────────────────────────────────────────────
   5. Convenience hook: open BBCode editor
──────────────────────────────────────────────────────────── */
interface BBCodeEditorState {
	title: string;
	value: string;
	multiline: boolean;
	onSave: (v: string) => void;
}

export function useBBCodeEditor() {
	const [state, setState] = useState<BBCodeEditorState | null>(null); // {title, value, multiline, onSave}
	const open = useCallback(
		(
			title: string,
			value: string,
			onSave: (v: string) => void,
			multiline = true,
		) => {
			setState({ title, value, onSave, multiline });
		},
		[],
	);
	const close = useCallback(() => setState(null), []);
	const dialog = state ? (
		<BBCodeEditorDialog
			title={state.title}
			value={state.value}
			multiline={state.multiline}
			onSave={state.onSave}
			onClose={close}
		/>
	) : null;
	return { open, dialog };
}

/* ─────────────────────────────────────────────────────────
   6. BBCode-aware input field (inline textarea + edit button)
──────────────────────────────────────────────────────────── */
export interface BBCodeFieldProps {
	label?: string;
	value: string;
	onChange: (v: string) => void;
	placeholder?: string;
	multiline?: boolean;
	rows?: number;
	fieldStyle?: CSSProperties;
}

export function BBCodeField({
	label,
	value,
	onChange,
	placeholder = "",
	multiline = false,
	rows = 2,
	fieldStyle,
}: BBCodeFieldProps) {
	const { open, dialog } = useBBCodeEditor();
	const hasBB = /\[[^\]]*\]/.test(value || "");

	return (
		<div className="field" style={fieldStyle}>
			{label && (
				<label style={{ display: "flex", alignItems: "center", gap: 4 }}>
					{label}
					{hasBB && (
						<span
							style={{
								fontSize: 10,
								padding: "1px 5px",
								borderRadius: 3,
								background: "var(--color-accent-bg)",
								color: "var(--color-accent)",
								fontWeight: 600,
							}}
						>
							BB
						</span>
					)}
					<button
						type="button"
						title="リッチエディタで編集"
						onClick={() => open(label, value, onChange, multiline)}
						style={{
							marginLeft: "auto",
							padding: "1px 7px",
							fontSize: 11,
							borderRadius: 3,
							border: "1px solid var(--color-border)",
							background: "var(--color-bg)",
							color: "var(--color-text-muted)",
							cursor: "pointer",
							display: "flex",
							alignItems: "center",
							gap: 3,
						}}
					>
						✏️ リッチ編集
					</button>
				</label>
			)}
			{multiline ? (
				<textarea
					value={value || ""}
					onChange={(e) => onChange(e.target.value)}
					rows={rows}
					placeholder={placeholder}
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
			) : (
				<input
					value={value || ""}
					onChange={(e) => onChange(e.target.value)}
					placeholder={placeholder}
					style={{ width: "100%" }}
				/>
			)}
			{dialog}
		</div>
	);
}

/* ─────────────────────────────────────────────────────────
   7. Compact BBCode trigger button (for table cells, etc.)
──────────────────────────────────────────────────────────── */
export interface BBCodeEditButtonProps {
	title?: string;
	value: string;
	onChange: (v: string) => void;
	multiline?: boolean;
}

export function BBCodeEditButton({
	title,
	value,
	onChange,
	multiline = false,
}: BBCodeEditButtonProps) {
	const { open, dialog } = useBBCodeEditor();
	const hasBB = /\[[^\]]*\]/.test(value || "");
	return (
		<Fragment>
			<button
				type="button"
				title={`BBコードエディタで編集: ${title}`}
				onClick={() => open(title ?? "", value, onChange, multiline)}
				style={{
					padding: "2px 5px",
					fontSize: 10,
					borderRadius: 3,
					border: "1px solid var(--color-border)",
					background: hasBB ? "var(--color-accent-bg)" : "var(--color-bg)",
					color: hasBB ? "var(--color-accent)" : "var(--color-text-muted)",
					cursor: "pointer",
					flexShrink: 0,
					lineHeight: 1.4,
					whiteSpace: "nowrap",
				}}
			>
				{hasBB ? "BB✓" : "BB"}
			</button>
			{dialog}
		</Fragment>
	);
}
