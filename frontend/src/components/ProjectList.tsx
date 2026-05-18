// ProjectList — project grid with create/edit/delete + JSON import/export.
// Ported from ProjectList.jsx.
import { useRef, useState } from "react";

import { ContextMenu } from "./EntityDialogs";

import type { Strings } from "../i18n/strings";
import type { Project } from "../types/model";

interface MenuState {
	x: number;
	y: number;
	project: Project;
}

interface ProjectListScreenProps {
	projects: Project[];
	onOpen: (id: string) => void;
	onNew: () => void;
	onEdit: (p: Project) => void;
	onDelete: (p: Project) => void;
	onImport: (json: unknown) => void;
	onExport: (id?: string) => void;
	t: Strings;
}

export function ProjectListScreen({
	projects,
	onOpen,
	onNew,
	onEdit,
	onDelete,
	onImport,
	onExport,
	t,
}: ProjectListScreenProps) {
	const [menu, setMenu] = useState<MenuState | null>(null);
	const fileInputRef = useRef<HTMLInputElement>(null);

	const handleImport = (e: React.ChangeEvent<HTMLInputElement>) => {
		const file = e.target.files?.[0];
		if (!file) return;
		const reader = new FileReader();
		reader.onload = (ev) => {
			try {
				const json: unknown = JSON.parse(String(ev.target?.result ?? ""));
				onImport(json);
			} catch (err) {
				alert(
					"JSONの読み込みに失敗しました: " +
						(err instanceof Error ? err.message : String(err))
				);
			}
		};
		reader.readAsText(file);
		e.target.value = ""; // allow same file twice
	};

	return (
		<div style={{ padding: "24px 28px" }}>
			<div
				style={{
					display: "flex",
					alignItems: "center",
					marginBottom: 20,
					gap: 12,
				}}>
				<h1 style={{ fontSize: 22, fontWeight: 700, letterSpacing: "-0.3px" }}>
					{t.projects}
				</h1>
				<span style={{ fontSize: 13, color: "var(--color-text-muted)" }}>
					{projects.length} 件
				</span>
				<div style={{ flex: 1 }} />
				<input
					ref={fileInputRef}
					type="file"
					accept="application/json,.json"
					onChange={handleImport}
					style={{ display: "none" }}
				/>
				<button
					className="btn btn-secondary btn-sm"
					onClick={() => fileInputRef.current?.click()}>
					📥 {t.import}
				</button>
				<button className="btn btn-secondary btn-sm" onClick={() => onExport()}>
					📤 {t.export}
				</button>
				<button className="btn btn-primary btn-sm" onClick={onNew}>
					＋ {t.newProject}
				</button>
			</div>
			<div className="card-grid" style={{ padding: 0 }}>
				{projects.map((p) => {
					const wgCount = p.workGroups?.length || 0;
					const trainCount = (p.workGroups || []).reduce(
						(acc, wg) =>
							acc +
							(wg.works || []).reduce(
								(a, w) => a + (w.trains?.length || 0),
								0
							),
						0
					);
					return (
						<div
							key={p.id}
							className="card project-card"
							onClick={() => onOpen(p.id)}
							onContextMenu={(e) => {
								e.preventDefault();
								setMenu({ x: e.clientX, y: e.clientY, project: p });
							}}
							style={{ position: "relative" }}>
							<div
								style={{
									display: "flex",
									alignItems: "center",
									gap: 8,
									marginBottom: 6,
								}}>
								<div
									style={{
										width: 32,
										height: 32,
										borderRadius: 6,
										background: "var(--color-accent-bg)",
										display: "flex",
										alignItems: "center",
										justifyContent: "center",
										color: "var(--color-accent)",
									}}>
									<svg
										width="18"
										height="18"
										viewBox="0 0 24 24"
										fill="none"
										stroke="currentColor"
										strokeWidth="2">
										<rect x="3" y="6" width="18" height="12" rx="2" />
										<path d="M3 10h18" />
									</svg>
								</div>
								<h3 style={{ flex: 1 }}>{p.name}</h3>
								<button
									className="btn btn-ghost btn-xs"
									onClick={(e) => {
										e.stopPropagation();
										const r = e.currentTarget.getBoundingClientRect();
										setMenu({ x: r.right - 4, y: r.bottom + 4, project: p });
									}}
									title="メニュー"
									style={{
										padding: "2px 6px",
										fontSize: 14,
										lineHeight: 1,
										color: "var(--color-text-muted)",
									}}>
									⋯
								</button>
							</div>
							<p style={{ marginBottom: 12, minHeight: 32 }}>
								{p.description || "—"}
							</p>
							<div
								style={{
									display: "flex",
									gap: 8,
									fontSize: 11,
									color: "var(--color-text-muted)",
								}}>
								<span className="chip gray">WG {wgCount}</span>
								<span className="chip gray">列車 {trainCount}</span>
							</div>
						</div>
					);
				})}
				<div className="card-add" onClick={onNew}>
					<div className="card-add-icon">＋</div>
					<div>{t.newProject}</div>
				</div>
			</div>

			{menu && (
				<ContextMenu
					x={menu.x}
					y={menu.y}
					onClose={() => setMenu(null)}
					items={[
						{
							icon: "📂",
							label: "開く",
							onClick: () => onOpen(menu.project.id),
						},
						{ icon: "✏️", label: t.edit, onClick: () => onEdit(menu.project) },
						{
							icon: "📤",
							label: "JSONとしてエクスポート",
							onClick: () => onExport(menu.project.id),
						},
						{
							icon: "🗑",
							label: t.delete,
							danger: true,
							onClick: () => onDelete(menu.project),
						},
					]}
				/>
			)}
		</div>
	);
}
