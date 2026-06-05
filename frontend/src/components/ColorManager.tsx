// ColorManager — project-level management of marker Colors (the colors entity
// is project-rooted; a timetable row's colors_id_marker picks one of these).
// Mirrors the lightweight CRUD shape of the Lines/Stations management UI.

import { useState } from "react";

import { ColorForm } from "./ColorForm";

import type { Strings } from "../i18n/strings";
import type { Color } from "../types/entities";

const clamp255 = (n: number): number =>
	Math.max(0, Math.min(255, Math.round(n)));

const toHex = (r: number, g: number, b: number): string =>
	"#" +
	[r, g, b].map((n) => clamp255(n).toString(16).padStart(2, "0")).join("");

export type ColorDraft = Omit<Color, "id" | "projectId" | "createdAt">;

type ColorManagerProps = {
	readonly colors: Color[];
	readonly isLoading: boolean;
	readonly onCreate: (draft: ColorDraft) => void;
	readonly onUpdate: (vars: { id: string } & ColorDraft) => void;
	readonly onDelete: (id: string) => void;
	readonly canWrite?: boolean;
	readonly t: Strings;
};

const EMPTY: ColorDraft = {
	name: "",
	description: "",
	red: 0,
	green: 0,
	blue: 0,
};

export const ColorManager = ({
	colors,
	isLoading,
	onCreate,
	onUpdate,
	onDelete,
	canWrite = true,
	t,
}: ColorManagerProps) => {
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

	const set = <K extends keyof ColorDraft>(k: K, v: ColorDraft[K]) => {
		setDraft((p) => ({ ...p, [k]: v }));
	};

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
					{`🎨 `}
					{t.colorManager}
				</h2>
				{editingId === null && canWrite ? (
					<button
						type="button"
						className="btn btn-primary btn-sm"
						onClick={startNew}>
						{`
						＋ `}
						{t.newColor}
					</button>
				) : null}
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

			{isLoading && colors.length === 0 && editingId === null ? (
				<div className="loading-center">
					<span className="spinner" />
					{`
					読み込み中...
				`}
				</div>
			) : colors.length === 0 && editingId === null ? (
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
								{c.description !== "" ? (
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
								) : null}
							</div>
							<span
								style={{
									fontFamily: "var(--font-mono)",
									fontSize: 11,
									color: "var(--color-text-muted)",
								}}>
								{toHex(c.red, c.green, c.blue)}
							</span>
							{canWrite ? (
								<>
									<button
										type="button"
										className="btn btn-ghost btn-sm"
										onClick={() => {
											startEdit(c);
										}}>
										{t.edit}
									</button>
									<button
										type="button"
										className="btn btn-ghost btn-sm"
										style={{ color: "var(--color-danger)" }}
										onClick={() => {
											onDelete(c.id);
										}}>{`
										🗑
									`}</button>
								</>
							) : null}
						</div>
					))}
				</div>
			)}
		</div>
	);
};
