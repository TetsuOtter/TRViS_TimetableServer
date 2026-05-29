// ColorManager — project-level management of marker Colors (the colors entity
// is project-rooted; a timetable row's colors_id_marker picks one of these).
// Mirrors the lightweight CRUD shape of the Lines/Stations management UI.

import { useState } from "react";

import type { Color } from "../types/entities";
import type { Strings } from "../i18n/strings";

const clamp255 = (n: number): number => Math.max(0, Math.min(255, Math.round(n)));

const toHex = (r: number, g: number, b: number): string =>
	"#" +
	[r, g, b]
		.map((n) => clamp255(n).toString(16).padStart(2, "0"))
		.join("");

const fromHex = (hex: string): { red: number; green: number; blue: number } => {
	const m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex.trim());
	if (m === null) return { red: 0, green: 0, blue: 0 };
	return {
		red: parseInt(m[1] ?? "0", 16),
		green: parseInt(m[2] ?? "0", 16),
		blue: parseInt(m[3] ?? "0", 16),
	};
};

export type ColorDraft = Omit<Color, "id" | "projectId" | "createdAt">;

interface ColorManagerProps {
	colors: Color[];
	onCreate: (draft: ColorDraft) => void;
	onUpdate: (vars: { id: string } & ColorDraft) => void;
	onDelete: (id: string) => void;
	t: Strings;
}

const EMPTY: ColorDraft = {
	name: "",
	description: "",
	red: 0,
	green: 0,
	blue: 0,
};

export function ColorManager({
	colors,
	onCreate,
	onUpdate,
	onDelete,
	t,
}: ColorManagerProps) {
	// editingId === "new" → the add form; a real id → editing that color.
	const [editingId, setEditingId] = useState<string | null>(null);
	const [draft, setDraft] = useState<ColorDraft>(EMPTY);

	const startNew = () => {
		setDraft(EMPTY);
		setEditingId("new");
	};
	const startEdit = (c: Color) => {
		setDraft({
			name: c.name,
			description: c.description,
			red: c.red,
			green: c.green,
			blue: c.blue,
		});
		setEditingId(c.id);
	};
	const cancel = () => {
		setEditingId(null);
		setDraft(EMPTY);
	};
	const save = () => {
		if (draft.name.trim() === "") {
			return;
		}
		if (editingId === "new") {
			onCreate(draft);
		} else if (editingId !== null) {
			onUpdate({ id: editingId, ...draft });
		}
		cancel();
	};

	const set = <K extends keyof ColorDraft>(k: K, v: ColorDraft[K]) =>
		setDraft((p) => ({ ...p, [k]: v }));

	return (
		<div style={{ padding: 20, maxWidth: 720 }}>
			<div
				style={{
					display: "flex",
					alignItems: "center",
					justifyContent: "space-between",
					marginBottom: 16,
				}}>
				<h2 style={{ fontSize: 18, fontWeight: 600 }}>
					🎨 {t.colorManager}
				</h2>
				{editingId === null && (
					<button className="btn btn-primary btn-sm" onClick={startNew}>
						＋ {t.newColor}
					</button>
				)}
			</div>

			{editingId !== null && (
				<ColorForm
					draft={draft}
					set={set}
					onSave={save}
					onCancel={cancel}
					t={t}
				/>
			)}

			{colors.length === 0 && editingId === null ? (
				<p style={{ color: "var(--color-text-muted)", padding: "24px 0" }}>
					{t.noColors}
				</p>
			) : (
				<div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
					{colors.map((c) => (
						<div
							key={c.id}
							style={{
								display: "flex",
								alignItems: "center",
								gap: 12,
								padding: "8px 12px",
								border: "1px solid var(--color-border)",
								borderRadius: 6,
								background: "var(--color-content)",
							}}>
							<span
								title={toHex(c.red, c.green, c.blue)}
								style={{
									width: 22,
									height: 22,
									borderRadius: 4,
									flexShrink: 0,
									background: toHex(c.red, c.green, c.blue),
									border: "1px solid var(--color-border)",
								}}
							/>
							<div style={{ flex: 1, minWidth: 0 }}>
								<div style={{ fontWeight: 500 }}>{c.name}</div>
								{c.description && (
									<div
										style={{
											fontSize: 12,
											color: "var(--color-text-muted)",
											overflow: "hidden",
											textOverflow: "ellipsis",
											whiteSpace: "nowrap",
										}}>
										{c.description}
									</div>
								)}
							</div>
							<span
								style={{
									fontFamily: "var(--font-mono)",
									fontSize: 11,
									color: "var(--color-text-muted)",
								}}>
								{toHex(c.red, c.green, c.blue)}
							</span>
							<button
								className="btn btn-ghost btn-sm"
								onClick={() => startEdit(c)}>
								{t.edit}
							</button>
							<button
								className="btn btn-ghost btn-sm"
								style={{ color: "var(--color-danger)" }}
								onClick={() => onDelete(c.id)}>
								🗑
							</button>
						</div>
					))}
				</div>
			)}
		</div>
	);
}

interface ColorFormProps {
	draft: ColorDraft;
	set: <K extends keyof ColorDraft>(k: K, v: ColorDraft[K]) => void;
	onSave: () => void;
	onCancel: () => void;
	t: Strings;
}

function ColorForm({ draft, set, onSave, onCancel, t }: ColorFormProps) {
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
						onChange={(e) => set("name", e.target.value)}
						style={inputStyle}
					/>
				</div>
				<div>
					<label style={{ fontSize: 12, color: "var(--color-text-muted)" }}>
						RGB
					</label>
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
					onChange={(e) => set("description", e.target.value)}
					style={inputStyle}
				/>
			</div>
			<div style={{ display: "flex", gap: 8, justifyContent: "flex-end" }}>
				<button className="btn btn-ghost btn-sm" onClick={onCancel}>
					{t.cancel}
				</button>
				<button
					className="btn btn-primary btn-sm"
					onClick={onSave}
					disabled={draft.name.trim() === ""}>
					{t.save}
				</button>
			</div>
		</div>
	);
}

const inputStyle: React.CSSProperties = {
	width: "100%",
	padding: "7px 9px",
	border: "1px solid var(--color-border)",
	borderRadius: 4,
	background: "var(--color-content)",
	color: "var(--color-text)",
	fontSize: 14,
};
