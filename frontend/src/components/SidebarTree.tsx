// SidebarTree — sidebar navigation component, extracted from App.tsx.
import { useState } from "react";

import type { useSettings } from "../app/SettingsContext";
import type { Project, Work, WorkGroup } from "../types/model";

type Screen = "projects" | "work" | "lines" | "colors" | "invite";

export type SidebarTreeProps = {
	readonly project?: Project;
	readonly isLoadingWorkGroups: boolean;
	readonly currentScreen: Screen;
	readonly currentWG: string | null;
	readonly currentWork: string | null;
	readonly projectPrivilegeType?: "read" | "write" | "admin";
	readonly onSelect: (wgId: string, wId: string) => void;
	readonly onSelectLines: () => void;
	readonly onSelectColors: () => void;
	readonly onSelectInvite: () => void;
	readonly onAddWG: () => void;
	readonly onWGContext: (x: number, y: number, wg: WorkGroup) => void;
	readonly onWorkContext: (
		x: number,
		y: number,
		wg: WorkGroup,
		w: Work
	) => void;
	readonly onAddWork: (wg: WorkGroup) => void;
	readonly onExport: () => void;
	readonly canWrite: boolean;
	readonly t: ReturnType<typeof useSettings>["t"];
};

export const SidebarTree = ({
	project,
	isLoadingWorkGroups,
	currentScreen,
	currentWG,
	currentWork,
	projectPrivilegeType,
	onSelect,
	onSelectLines,
	onSelectColors,
	onSelectInvite,
	onAddWG,
	onWGContext,
	onWorkContext,
	onAddWork,
	onExport,
	canWrite,
	t,
}: SidebarTreeProps) => {
	const [openWG, setOpenWG] = useState<Set<string>>(
		() => new Set((project?.workGroups ?? []).map((wg) => wg.id))
	);
	const toggle = (id: string) => {
		setOpenWG((s) => {
			const n = new Set(s);
			if (n.has(id)) {
				n.delete(id);
			} else {
				n.add(id);
			}
			return n;
		});
	};
	if (project === undefined) return null;
	return (
		<>
			<div
				className="sidebar-section"
				style={{ flex: 1, overflow: "auto" }}>
				<div
					className="sidebar-label"
					style={{ paddingBottom: 6 }}>
					{project.name}
				</div>
				{isLoadingWorkGroups && project.workGroups.length === 0 ? (
					<div
						style={{
							display: "flex",
							alignItems: "center",
							justifyContent: "center",
							padding: "16px 0",
						}}>
						<span className="spinner" />
					</div>
				) : null}
				{project.workGroups.map((wg) => (
					<div key={wg.id}>
						<div style={{ display: "flex", alignItems: "center" }}>
							<button
								type="button"
								className={`sidebar-item ${currentScreen === "work" && currentWG === wg.id && currentWork === null ? "active" : ""}`}
								onClick={() => {
									toggle(wg.id);
								}}
								onContextMenu={(e) => {
									if (!canWrite) return;
									e.preventDefault();
									onWGContext(e.clientX, e.clientY, wg);
								}}
								style={{ flex: 1 }}>
								<span
									style={{
										fontSize: 9,
										opacity: 0.6,
										width: 8,
									}}>
									{openWG.has(wg.id) ? "▾" : "▸"}
								</span>
								<span
									style={{
										flex: 1,
										whiteSpace: "nowrap",
										overflow: "hidden",
										textOverflow: "ellipsis",
									}}>
									{wg.name}
								</span>
								<span style={{ fontSize: 10, opacity: 0.5 }}>
									{wg.works.length}
								</span>
							</button>
						</div>
						{openWG.has(wg.id) && (
							<>
								{wg.works.map((w) => (
									<button
										type="button"
										key={w.id}
										className={`sidebar-item indent ${currentScreen === "work" && currentWork === w.id ? "active" : ""}`}
										onClick={() => {
											onSelect(wg.id, w.id);
										}}
										onContextMenu={(e) => {
											if (!canWrite) return;
											e.preventDefault();
											onWorkContext(e.clientX, e.clientY, wg, w);
										}}>
										<span
											style={{
												opacity: 0.5,
												fontSize: 11,
											}}>{`
											📋
										`}</span>
										<span
											style={{
												flex: 1,
												whiteSpace: "nowrap",
												overflow: "hidden",
												textOverflow: "ellipsis",
											}}>
											{w.name}
										</span>
										<span
											style={{
												fontSize: 10,
												opacity: 0.5,
											}}>
											{w.trains !== undefined ? w.trains.length : 0}
										</span>
									</button>
								))}
								{canWrite ? (
									<button
										type="button"
										className="sidebar-item indent"
										style={{ opacity: 0.7, fontSize: 11 }}
										onClick={() => {
											onAddWork(wg);
										}}>
										<span style={{ fontSize: 11 }}>{`＋`}</span> {t.newWork}
									</button>
								) : null}
							</>
						)}
					</div>
				))}
				<div style={{ height: 8 }} />
				{canWrite ? (
					<button
						type="button"
						className="sidebar-item"
						style={{
							color: "var(--color-sidebar-text)",
							fontSize: 12,
						}}
						onClick={onAddWG}>
						<span style={{ fontSize: 11 }}>{`＋`}</span> {t.newWorkGroup}
					</button>
				) : null}
			</div>
			<div className="sidebar-footer">
				<button
					type="button"
					className={`sidebar-item ${currentScreen === "lines" ? "active" : ""}`}
					onClick={onSelectLines}>
					<span style={{ fontSize: 11 }}>{`🛤`}</span> {t.lineManager}
				</button>
				<button
					type="button"
					className={`sidebar-item ${currentScreen === "colors" ? "active" : ""}`}
					onClick={onSelectColors}>
					<span style={{ fontSize: 11 }}>{`🎨`}</span> {t.colorManager}
				</button>
				{projectPrivilegeType === "admin" && (
					<button
						type="button"
						className={`sidebar-item ${currentScreen === "invite" ? "active" : ""}`}
						onClick={onSelectInvite}>
						<span style={{ fontSize: 11 }}>{`🔑`}</span> {t.inviteManager}
					</button>
				)}
				<button
					type="button"
					className="sidebar-item"
					style={{ fontSize: 12 }}
					onClick={onExport}>
					<span style={{ fontSize: 11 }}>{`📤`}</span> {t.export}
					{` (JSON)
				`}
				</button>
			</div>
		</>
	);
};
