import type { ColorDraft } from "./ColorManager";
import type { Strings } from "../i18n/strings";

// Duplicated from ColorManager to avoid a circular import
const clamp255 = (n: number): number =>
	Math.max(0, Math.min(255, Math.round(n)));

const toHex = (r: number, g: number, b: number): string =>
	"#" +
	[r, g, b].map((n) => clamp255(n).toString(16).padStart(2, "0")).join("");

const fromHex = (hex: string): { red: number; green: number; blue: number } => {
	const m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex.trim());
	if (m === null) return { red: 0, green: 0, blue: 0 };
	return {
		red: parseInt(m[1] ?? "0", 16),
		green: parseInt(m[2] ?? "0", 16),
		blue: parseInt(m[3] ?? "0", 16),
	};
};

const inputStyle: React.CSSProperties = {
	width: "100%",
	padding: "7px 9px",
	border: "1px solid var(--color-border)",
	borderRadius: 4,
	background: "var(--color-content)",
	color: "var(--color-text)",
	fontSize: 14,
};

export type ColorFormProps = {
	readonly draft: ColorDraft;
	readonly set: <K extends keyof ColorDraft>(k: K, v: ColorDraft[K]) => void;
	readonly onSave: () => void;
	readonly onCancel: () => void;
	readonly t: Strings;
};

export const ColorForm = ({
	draft,
	set,
	onSave,
	onCancel,
	t,
}: ColorFormProps) => {
	const hex = toHex(draft.red, draft.green, draft.blue);
	return (
		<div
			style={{
				display: "flex",
				flexDirection: "column",
				gap: 10,
				padding: 14,
				marginBottom: 16,
				border: "1px solid var(--color-accent)",
				borderRadius: 8,
				background: "var(--color-bg)",
			}}>
			<div style={{ display: "flex", gap: 12, alignItems: "flex-end" }}>
				<div style={{ flex: 1 }}>
					<label style={{ fontSize: 12, color: "var(--color-text-muted)" }}>
						{t.color}
					</label>
					<input
						type="text"
						value={draft.name}
						placeholder="赤 / Red ..."
						onChange={(e) => {
							set("name", e.target.value);
						}}
						style={inputStyle}
					/>
				</div>
				<div>
					<label style={{ fontSize: 12, color: "var(--color-text-muted)" }}>{`
						RGB
					`}</label>
					<div style={{ display: "flex", gap: 6, alignItems: "center" }}>
						<input
							type="color"
							value={hex}
							onChange={(e) => {
								const { red, green, blue } = fromHex(e.target.value);
								set("red", red);
								set("green", green);
								set("blue", blue);
							}}
							style={{
								width: 40,
								height: 34,
								padding: 0,
								border: "1px solid var(--color-border)",
								borderRadius: 4,
								background: "transparent",
								cursor: "pointer",
							}}
						/>
						<span
							style={{
								fontFamily: "var(--font-mono)",
								fontSize: 12,
								color: "var(--color-text-muted)",
							}}>
							{hex}
						</span>
					</div>
				</div>
			</div>
			<div>
				<label style={{ fontSize: 12, color: "var(--color-text-muted)" }}>
					{t.description}
				</label>
				<input
					type="text"
					value={draft.description}
					onChange={(e) => {
						set("description", e.target.value);
					}}
					style={inputStyle}
				/>
			</div>
			<div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
				<button
					type="button"
					className="btn btn-ghost btn-sm"
					onClick={onCancel}>
					{t.cancel}
				</button>
				<button
					type="button"
					className="btn btn-primary btn-sm"
					onClick={onSave}
					disabled={draft.name.trim() === ""}>
					{t.save}
				</button>
			</div>
		</div>
	);
};
