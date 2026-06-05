// BBCodeToolbar — tag insertion toolbar for the BBCode editor
import { useState, useCallback } from "react";
import type { CSSProperties, RefObject } from "react";

type ToolbarButton = {
	label: string;
	style: CSSProperties;
	tag: string;
	title: string;
	noAttr: boolean;
};

const TOOLBAR_BUTTONS: ToolbarButton[] = [
	{
		label: "B",
		style: { fontWeight: "bold" },
		tag: "b",
		title: "太字",
		noAttr: true,
	},
	{
		label: "I",
		style: { fontStyle: "italic" },
		tag: "i",
		title: "斜体",
		noAttr: true,
	},
	{
		label: "U",
		style: { textDecoration: "underline" },
		tag: "u",
		title: "下線",
		noAttr: true,
	},
	{
		label: "S",
		style: { textDecoration: "line-through" },
		tag: "s",
		title: "取消線",
		noAttr: true,
	},
];

export type BBCodeToolbarProps = {
	readonly textareaRef: RefObject<HTMLTextAreaElement | null>;
	readonly value: string;
	readonly onChange: (v: string) => void;
};

export const BBCodeToolbar = ({
	textareaRef,
	value,
	onChange,
}: BBCodeToolbarProps) => {
	const [colorPicker, setColorPicker] = useState(false);
	const [colorVal, setColorVal] = useState("#e74c3c");
	const [sizeVal, setSizeVal] = useState("14");
	const [fontVal, setFontVal] = useState("");
	const [showSize, setShowSize] = useState(false);
	const [showFont, setShowFont] = useState(false);

	const insertTag = useCallback(
		(open: string, close: string) => {
			const el = textareaRef.current;
			if (el === null) return;
			const start = el.selectionStart;
			const end = el.selectionEnd;
			const sel = value.slice(start, end);
			const before = value.slice(0, start);
			const after = value.slice(end);
			const newVal =
				sel !== ""
					? `${before}${open}${sel}${close}${after}`
					: `${before}${open}${close}${after}`;
			onChange(newVal);
			// Restore cursor
			setTimeout(() => {
				el.focus();
				const cur =
					sel !== ""
						? start + open.length + sel.length + close.length
						: start + open.length;
				el.setSelectionRange(cur, cur);
			}, 0);
		},
		[textareaRef, value, onChange]
	);

	const insertSimple = (tag: string) => {
		insertTag(`[${tag}]`, `[/${tag}]`);
	};
	const insertColor = () => {
		insertTag(`[color=${colorVal}]`, `[/color]`);
		setColorPicker(false);
	};
	const insertSize = () => {
		const n = parseInt(sizeVal, 10);
		if (n === 0 || isNaN(n)) return;
		insertTag(`[size=${n}]`, `[/size]`);
		setShowSize(false);
	};
	const insertFont = () => {
		const f = fontVal.trim();
		if (f === "") return;
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
			}}>
			{TOOLBAR_BUTTONS.map((btn) => (
				<button
					type="button"
					key={btn.tag}
					title={btn.title}
					style={{ ...btnBase, ...btn.style }}
					onMouseDown={(e) => {
						e.preventDefault();
						insertSimple(btn.tag);
					}}>
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
					type="button"
					title="フォント色"
					style={{ ...btnBase }}
					onMouseDown={(e) => {
						e.preventDefault();
						setColorPicker((v) => !v);
						setShowSize(false);
						setShowFont(false);
					}}>
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
					{`
					色
				`}
				</button>
				{colorPicker ? (
					<div style={popBase}>
						<label style={{ fontSize: 12, color: "var(--color-text-muted)" }}>{`
							カラー (light)
						`}</label>
						<div style={{ display: "flex", gap: 6, alignItems: "center" }}>
							<input
								type="color"
								value={colorVal}
								onChange={(e) => {
									setColorVal(e.target.value);
								}}
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
								onChange={(e) => {
									setColorVal(e.target.value);
								}}
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
									type="button"
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
							type="button"
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
							}}>{`
							挿入
						`}</button>
					</div>
				) : null}
			</div>

			{/* Size */}
			<div style={{ position: "relative" }}>
				<button
					type="button"
					title="フォントサイズ"
					style={btnBase}
					onMouseDown={(e) => {
						e.preventDefault();
						setShowSize((v) => !v);
						setColorPicker(false);
						setShowFont(false);
					}}>{`
					サイズ
				`}</button>
				{showSize ? (
					<div style={popBase}>
						<label style={{ fontSize: 12, color: "var(--color-text-muted)" }}>{`
							フォントサイズ (px)
						`}</label>
						<div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
							{[10, 12, 14, 16, 18, 20, 24].map((s) => (
								<button
									type="button"
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
									}}>
									{s}
								</button>
							))}
						</div>
						<input
							type="number"
							value={sizeVal}
							onChange={(e) => {
								setSizeVal(e.target.value);
							}}
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
							type="button"
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
							}}>{`
							挿入
						`}</button>
					</div>
				) : null}
			</div>

			{/* Font */}
			<div style={{ position: "relative" }}>
				<button
					type="button"
					title="フォントファミリー"
					style={btnBase}
					onMouseDown={(e) => {
						e.preventDefault();
						setShowFont((v) => !v);
						setColorPicker(false);
						setShowSize(false);
					}}>{`
					フォント
				`}</button>
				{showFont ? (
					<div style={popBase}>
						<label style={{ fontSize: 12, color: "var(--color-text-muted)" }}>{`
							フォントファミリー
						`}</label>
						<div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
							{[
								"Meiryo",
								"Yu Gothic",
								"MS Gothic",
								"Arial",
								"Times New Roman",
							].map((f) => (
								<button
									type="button"
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
									}}>
									{f}
								</button>
							))}
						</div>
						<input
							value={fontVal}
							onChange={(e) => {
								setFontVal(e.target.value);
							}}
							placeholder="フォント名を入力"
							style={{
								padding: "4px 6px",
								border: "1px solid var(--color-border)",
								borderRadius: 4,
								fontSize: 12,
								background: "var(--color-bg)",
								color: "var(--color-text)",
								width: "100%",
								fontFamily: fontVal !== "" ? fontVal : "inherit",
							}}
						/>
						<button
							type="button"
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
							}}>{`
							挿入
						`}</button>
					</div>
				) : null}
			</div>

			<div style={{ flex: 1 }} />

			{/* Remove all tags */}
			<button
				type="button"
				title="全タグを削除"
				style={{ ...btnBase, opacity: 0.6, fontSize: 11 }}
				onMouseDown={(e) => {
					e.preventDefault();
					onChange(value.replace(/\[[^\]]*\]/g, ""));
				}}>{`
				タグ削除
			`}</button>
		</div>
	);
};
