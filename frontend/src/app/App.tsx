// App — top-level app, routing & sidebar tree. Ported from App.jsx.
// The prototype's tweaks-panel is dropped; theme/lang live in the top bar
// via SettingsContext. Layout is fixed to the design's default ("sidebar").
import { useEffect, useMemo, useState } from "react";

import {
	useCreateProject,
	useDeleteProject,
	useProjects,
	useUpdateProject,
} from "../api/hooks/useProjects";
import {
	useCreateWorkGroup,
	useDeleteWorkGroup,
	useUpdateWorkGroup,
	useWorkGroups,
} from "../api/hooks/useWorkGroups";
import { AppShell } from "../components/AppShell";
import AuthControls from "../components/auth/AuthControls";
import {
	ConfirmDialog,
	ContextMenu,
	ProjectDialog,
	WorkDialog,
	WorkGroupDialog,
} from "../components/EntityDialogs";
import { LineManager } from "../components/LineManager";
import { ProjectListScreen } from "../components/ProjectList";
import { StopPatternWizard } from "../components/StopPatternWizard";
import { WorkBrowser } from "../components/WorkBrowser";
import { createInitialData } from "../data/sampleData";

import { useSettings } from "./SettingsContext";

import type { ContextMenuItem } from "../components/EntityDialogs";
import type { Project as EntityProject } from "../types/entities";
import type {
	AppData,
	Line,
	Project,
	StationOnLine,
	StopPattern,
	Work,
	WorkGroup,
} from "../types/model";

function uid(prefix: string): string {
	return (
		prefix +
		Date.now().toString(36) +
		Math.random().toString(36).slice(2, 6)
	);
}

function downloadJson(filename: string, obj: unknown) {
	const blob = new Blob([JSON.stringify(obj, null, 2)], {
		type: "application/json",
	});
	const url = URL.createObjectURL(blob);
	const a = document.createElement("a");
	a.href = url;
	a.download = filename;
	document.body.appendChild(a);
	a.click();
	setTimeout(() => {
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	}, 0);
}

type Screen = "projects" | "work" | "lines";

interface ContextMenuState {
	x: number;
	y: number;
	items: ContextMenuItem[];
}
interface ConfirmState {
	title: string;
	message: string;
	onConfirm: () => void;
}

interface SidebarTreeProps {
	project?: Project;
	currentScreen: Screen;
	currentWG: string | null;
	currentWork: string | null;
	onSelect: (wgId: string, wId: string) => void;
	onSelectLines: () => void;
	onAddWG: () => void;
	onWGContext: (x: number, y: number, wg: WorkGroup) => void;
	onWorkContext: (
		x: number,
		y: number,
		wg: WorkGroup,
		w: Work
	) => void;
	onAddWork: (wg: WorkGroup) => void;
	onExport: () => void;
	t: ReturnType<typeof useSettings>["t"];
}

function SidebarTree({
	project,
	currentScreen,
	currentWG,
	currentWork,
	onSelect,
	onSelectLines,
	onAddWG,
	onWGContext,
	onWorkContext,
	onAddWork,
	onExport,
	t,
}: SidebarTreeProps) {
	const [openWG, setOpenWG] = useState<Set<string>>(
		() => new Set((project?.workGroups || []).map((wg) => wg.id))
	);
	const toggle = (id: string) => {
		setOpenWG((s) => {
			const n = new Set(s);
			n.has(id) ? n.delete(id) : n.add(id);
			return n;
		});
	};
	if (!project) return null;
	return (
		<>
			<div
				className="sidebar-section"
				style={{ flex: 1, overflow: "auto" }}>
				<div className="sidebar-label" style={{ paddingBottom: 6 }}>
					{project.name}
				</div>
				{project.workGroups.map((wg) => (
					<div key={wg.id}>
						<div style={{ display: "flex", alignItems: "center" }}>
							<button
								className={`sidebar-item ${currentScreen === "work" && currentWG === wg.id && !currentWork ? "active" : ""}`}
								onClick={() => toggle(wg.id)}
								onContextMenu={(e) => {
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
										key={w.id}
										className={`sidebar-item indent ${currentScreen === "work" && currentWork === w.id ? "active" : ""}`}
										onClick={() => onSelect(wg.id, w.id)}
										onContextMenu={(e) => {
											e.preventDefault();
											onWorkContext(
												e.clientX,
												e.clientY,
												wg,
												w
											);
										}}>
										<span
											style={{
												opacity: 0.5,
												fontSize: 11,
											}}>
											📋
										</span>
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
											{w.trains?.length || 0}
										</span>
									</button>
								))}
								<button
									className="sidebar-item indent"
									style={{ opacity: 0.7, fontSize: 11 }}
									onClick={() => onAddWork(wg)}>
									<span style={{ fontSize: 11 }}>＋</span>{" "}
									{t.newWork}
								</button>
							</>
						)}
					</div>
				))}
				<div style={{ height: 8 }} />
				<button
					className="sidebar-item"
					style={{
						color: "var(--color-sidebar-text)",
						fontSize: 12,
					}}
					onClick={onAddWG}>
					<span style={{ fontSize: 11 }}>＋</span> {t.newWorkGroup}
				</button>
			</div>
			<div className="sidebar-footer">
				<button
					className={`sidebar-item ${currentScreen === "lines" ? "active" : ""}`}
					onClick={onSelectLines}>
					<span style={{ fontSize: 11 }}>🛤</span> {t.lineManager}
				</button>
				<button
					className="sidebar-item"
					style={{ fontSize: 12 }}
					onClick={onExport}>
					<span style={{ fontSize: 11 }}>📤</span> {t.export} (JSON)
				</button>
			</div>
		</>
	);
}

interface ImportedJson {
	kind?: string;
	projects?: Project[];
	lines?: Line[];
	stations?: AppData["stations"];
	stationsOnLine?: StationOnLine[];
	stopPatterns?: StopPattern[];
	project?: Project;
}

export function App() {
	const { theme, toggleTheme, lang, toggleLang, density, t } =
		useSettings();

	const {
		data: apiProjects,
		isLoading: projectsLoading,
		error: projectsError,
		refetch: refetchProjects,
	} = useProjects();
	const createProjectMutation = useCreateProject();
	const updateProjectMutation = useUpdateProject();
	const deleteProjectMutation = useDeleteProject();

	const [data, setData] = useState<AppData>(() => createInitialData());
	const [projectId, setProjectId] = useState<string | null>(null);

	const { data: apiWorkGroups } = useWorkGroups(projectId ?? "");
	const createWGMutation = useCreateWorkGroup(projectId ?? "");
	const updateWGMutation = useUpdateWorkGroup(projectId ?? "");
	const deleteWGMutation = useDeleteWorkGroup(projectId ?? "");
	const [currentWG, setCurrentWG] = useState<string | null>(null);
	const [currentWork, setCurrentWork] = useState<string | null>(null);
	const [screen, setScreen] = useState<Screen>("projects");
	const [showStopPattern, setShowStopPattern] = useState(false);
	const [editingPattern, setEditingPattern] = useState<StopPattern | null>(
		null
	);

	const [editingProject, setEditingProject] = useState<{
		project?: Project;
		new?: boolean;
	} | null>(null);
	const [editingWG, setEditingWG] = useState<{
		wg?: WorkGroup;
		new?: boolean;
	} | null>(null);
	const [editingWork, setEditingWork] = useState<{
		wgId: string;
		work?: Work;
		new?: boolean;
	} | null>(null);
	const [confirmDialog, setConfirmDialog] = useState<ConfirmState | null>(
		null
	);
	const [contextMenu, setContextMenu] = useState<ContextMenuState | null>(
		null
	);

	const baseProject = apiProjects?.find((p) => p.id === projectId);
	const project: Project | undefined =
		baseProject !== undefined
			? {
					id: baseProject.id,
					name: baseProject.name,
					description: baseProject.description,
					workGroups: (apiWorkGroups ?? []).map((wg) => ({
						id: wg.id,
						name: wg.name,
						description: wg.description,
						works: [],
					})),
				}
			: undefined;
	const wg = project?.workGroups.find((g) => g.id === currentWG);
	const work = wg?.works.find((w) => w.id === currentWork);

	useEffect(() => {
		if (
			screen === "work" &&
			project &&
			(!currentWork || !wg?.works.find((w) => w.id === currentWork))
		) {
			const firstWG = project.workGroups[0];
			const firstWork = firstWG?.works[0];
			if (firstWG && firstWork) {
				setCurrentWG(firstWG.id);
				setCurrentWork(firstWork.id);
			} else if (firstWG) {
				setCurrentWG(firstWG.id);
				setCurrentWork(null);
			}
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [screen, projectId]);

	const updateProject = (updated: Project) => {
		setData((d) => ({
			...d,
			projects: d.projects.map((p) =>
				p.id === updated.id ? updated : p
			),
		}));
	};
	const updateWork = (updatedWork: Work) => {
		if (!project || !wg) return;
		const newWG: WorkGroup = {
			...wg,
			works: wg.works.map((w) =>
				w.id === updatedWork.id ? updatedWork : w
			),
		};
		const newProject: Project = {
			...project,
			workGroups: project.workGroups.map((g) =>
				g.id === wg.id ? newWG : g
			),
		};
		updateProject(newProject);
	};

	const breadcrumbs = useMemo(() => {
		const bc = [
			{
				label: t.projects,
				onClick: () => {
					setScreen("projects");
					setProjectId(null);
				},
			},
		];
		if (project)
			bc.push({
				label: project.name,
				onClick: () => {
					setScreen("work");
				},
			});
		if (screen === "work" && wg)
			bc.push({ label: wg.name, onClick: () => {} });
		if (screen === "work" && work)
			bc.push({ label: work.name, onClick: () => {} });
		if (screen === "lines")
			bc.push({ label: t.lineManager, onClick: () => {} });
		return bc;
	}, [project, wg, work, screen, t]);

	const handleOpenProject = (pid: string) => {
		setProjectId(pid);
		setScreen("work");
	};

	/* ─── Project CRUD ─── */
	const saveProject = (
		draft: Partial<Pick<Project, "id">> &
			Pick<Project, "name" | "description">
	) => {
		const onError = (e: Error) => alert(e.message);
		if (draft.id !== undefined && draft.id !== "") {
			updateProjectMutation.mutate(
				{ id: draft.id, name: draft.name, description: draft.description },
				{ onError }
			);
		} else {
			createProjectMutation.mutate(
				{ name: draft.name, description: draft.description },
				{ onError }
			);
		}
	};
	const deleteProject = (p: EntityProject) => {
		deleteProjectMutation.mutate(p.id, {
			onError: (e) => alert(e.message),
		});
		if (projectId === p.id) {
			setProjectId(null);
			setScreen("projects");
		}
	};

	/* ─── WorkGroup CRUD ─── */
	const saveWG = (
		draft: Partial<Pick<WorkGroup, "id">> &
			Pick<WorkGroup, "name" | "description">
	) => {
		if (project === undefined) return;
		if (draft.id !== undefined && draft.id !== "") {
			updateWGMutation.mutate(
				{ id: draft.id, name: draft.name, description: draft.description },
				{ onError: (e: Error) => alert(e.message) }
			);
		} else {
			createWGMutation.mutate(
				{ name: draft.name, description: draft.description },
				{ onError: (e: Error) => alert(e.message) }
			);
		}
	};
	const deleteWG = (wgId: string) => {
		deleteWGMutation.mutate(wgId, {
			onError: (e: Error) => alert(e.message),
		});
		if (currentWG === wgId) {
			setCurrentWG(null);
			setCurrentWork(null);
		}
	};

	/* ─── Work CRUD ─── */
	const saveWork = (
		wgId: string,
		draft: Partial<Pick<Work, "id">> &
			Pick<Work, "name" | "affectDate" | "remarks">
	) => {
		if (!project) return;
		const targetWG = project.workGroups.find((g) => g.id === wgId);
		if (!targetWG) return;
		let newWG: WorkGroup;
		if (draft.id) {
			newWG = {
				...targetWG,
				works: targetWG.works.map((w) =>
					w.id === draft.id ? { ...w, ...draft } : w
				),
			};
		} else {
			const newW: Work = { ...draft, id: uid("w"), trains: [] };
			newWG = { ...targetWG, works: [...targetWG.works, newW] };
			setCurrentWG(wgId);
			setCurrentWork(newW.id);
			setScreen("work");
		}
		const newProj: Project = {
			...project,
			workGroups: project.workGroups.map((g) =>
				g.id === wgId ? newWG : g
			),
		};
		updateProject(newProj);
	};
	const deleteWork = (wgId: string, workId: string) => {
		if (!project) return;
		const targetWG = project.workGroups.find((g) => g.id === wgId);
		if (!targetWG) return;
		const newWG: WorkGroup = {
			...targetWG,
			works: targetWG.works.filter((w) => w.id !== workId),
		};
		const newProj: Project = {
			...project,
			workGroups: project.workGroups.map((g) =>
				g.id === wgId ? newWG : g
			),
		};
		updateProject(newProj);
		if (currentWork === workId) setCurrentWork(null);
	};

	/* ─── Train delete (called from WorkBrowser dialog) ─── */
	const deleteTrain = (trainId: string) => {
		if (!work) return;
		const newWork: Work = {
			...work,
			trains: work.trains.filter((tr) => tr.id !== trainId),
		};
		updateWork(newWork);
	};

	/* ─── JSON Import / Export ─── */
	const exportProject = (pid: string) => {
		const p = data.projects.find((x) => x.id === pid);
		if (!p) return;
		const safeName = (p.name || "project").replace(/[^\w.-]+/g, "_");
		downloadJson(`${safeName}.json`, {
			version: 1,
			kind: "trvis-project",
			exportedAt: new Date().toISOString(),
			project: p,
			lines: data.lines,
			stations: data.stations,
			stationsOnLine: data.stationsOnLine,
			stopPatterns: data.stopPatterns,
		});
	};
	const exportAll = () => {
		downloadJson("trvis-all.json", {
			version: 1,
			kind: "trvis-all",
			exportedAt: new Date().toISOString(),
			...data,
		});
	};
	const mergeById = <T extends { id: string }>(
		existing: T[],
		incoming: T[]
	): T[] => {
		const map = new Map(existing.map((x) => [x.id, x]));
		incoming.forEach((x) => map.set(x.id, x));
		return [...map.values()];
	};
	const importJson = (raw: unknown) => {
		try {
			if (typeof raw !== "object" || raw === null) {
				alert("未対応のJSON形式です。");
				return;
			}
			const json = raw as ImportedJson;
			if (json.kind === "trvis-all" && Array.isArray(json.projects)) {
				if (
					!confirm(
						`全データ (${json.projects.length} プロジェクト) を読み込みます。現在のデータは置き換えられます。よろしいですか？`
					)
				)
					return;
				setData({
					projects: json.projects,
					lines: json.lines || [],
					stations: json.stations || [],
					stationsOnLine: json.stationsOnLine || [],
					stopPatterns: json.stopPatterns || [],
				});
			} else if (json.kind === "trvis-project" && json.project) {
				setData((d) => {
					const incoming = json.project as Project;
					const exists = d.projects.some(
						(p) => p.id === incoming.id
					);
					const newProj: Project = exists
						? {
								...incoming,
								id: uid("p"),
								name: incoming.name + " (取込)",
							}
						: incoming;
					return {
						...d,
						projects: [...d.projects, newProj],
						lines: mergeById(d.lines, json.lines || []),
						stations: mergeById(d.stations, json.stations || []),
						stationsOnLine: mergeById(
							d.stationsOnLine,
							json.stationsOnLine || []
						),
						stopPatterns: mergeById(
							d.stopPatterns,
							json.stopPatterns || []
						),
					};
				});
			} else {
				alert("未対応のJSON形式です。");
			}
		} catch (e) {
			alert(
				"インポートに失敗しました: " +
					(e instanceof Error ? e.message : String(e))
			);
		}
	};

	const sidebarContent = projectId ? (
		<SidebarTree
			project={project}
			currentScreen={screen}
			currentWG={currentWG}
			currentWork={currentWork}
			onSelect={(wgId, wId) => {
				setScreen("work");
				setCurrentWG(wgId);
				setCurrentWork(wId);
			}}
			onSelectLines={() => setScreen("lines")}
			onAddWG={() => setEditingWG({ new: true })}
			onAddWork={(w) => setEditingWork({ wgId: w.id, new: true })}
			onWGContext={(x, y, wgRef) =>
				setContextMenu({
					x,
					y,
					items: [
						{
							icon: "✏️",
							label: t.edit,
							onClick: () => setEditingWG({ wg: wgRef }),
						},
						{
							icon: "＋",
							label: t.newWork,
							onClick: () =>
								setEditingWork({ wgId: wgRef.id, new: true }),
						},
						{
							icon: "🗑",
							label: t.delete,
							danger: true,
							onClick: () =>
								setConfirmDialog({
									title: "ワークグループを削除",
									message: `「${wgRef.name}」を削除します。配下の ${wgRef.works.length} 件のワークも一緒に削除されます。`,
									onConfirm: () => deleteWG(wgRef.id),
								}),
						},
					],
				})
			}
			onWorkContext={(x, y, wgRef, w) =>
				setContextMenu({
					x,
					y,
					items: [
						{
							icon: "✏️",
							label: t.edit,
							onClick: () =>
								setEditingWork({ wgId: wgRef.id, work: w }),
						},
						{
							icon: "🗑",
							label: t.delete,
							danger: true,
							onClick: () =>
								setConfirmDialog({
									title: "ワークを削除",
									message: `「${w.name}」を削除します。配下の ${w.trains?.length || 0} 列車も削除されます。`,
									onConfirm: () =>
										deleteWork(wgRef.id, w.id),
								}),
						},
					],
				})
			}
			onExport={() => exportProject(projectId)}
			t={t}
		/>
	) : null;

	return (
		<div
			data-theme={theme}
			data-density={density}
			style={{ height: "100%" }}>
			<AppShell
				theme={theme}
				toggleTheme={toggleTheme}
				lang={lang}
				toggleLang={toggleLang}
				breadcrumbs={projectId ? breadcrumbs : null}
				sidebarContent={sidebarContent}
				topRight={<AuthControls />}
				t={t}>
				{!projectId && (
					<ProjectListScreen
						projects={apiProjects ?? []}
						isLoading={projectsLoading}
						error={projectsError}
						onRetry={() => {
							void refetchProjects();
						}}
						onOpen={handleOpenProject}
						onNew={() => setEditingProject({ new: true })}
						onEdit={(p) =>
							setEditingProject({
								project: {
									id: p.id,
									name: p.name,
									description: p.description,
									workGroups: [],
								},
							})
						}
						onDelete={(p) =>
							setConfirmDialog({
								title: "プロジェクトを削除",
								message: `「${p.name}」を削除します。配下の全ワークグループ・ワーク・列車も削除されます。`,
								onConfirm: () => deleteProject(p),
							})
						}
						onImport={importJson}
						onExport={(pid) =>
							pid !== undefined ? exportProject(pid) : exportAll()
						}
						t={t}
					/>
				)}
				{projectId && screen === "work" && work && (
					<WorkBrowser
						work={work}
						onUpdateWork={updateWork}
						onDeleteTrain={deleteTrain}
						onOpenStopPatternWizard={() =>
							setShowStopPattern(true)
						}
						stopPatterns={data.stopPatterns}
						stations={data.stations}
						stationsOnLine={data.stationsOnLine}
						lines={data.lines}
						t={t}
					/>
				)}
				{projectId && screen === "work" && !work && (
					<div className="empty-state" style={{ padding: 60 }}>
						<p>このワークグループにはワークがありません。</p>
						{wg && (
							<button
								className="btn btn-primary btn-sm"
								onClick={() =>
									setEditingWork({
										wgId: wg.id,
										new: true,
									})
								}>
								＋ {t.newWork}
							</button>
						)}
						{!wg && project && (
							<button
								className="btn btn-primary btn-sm"
								onClick={() => setEditingWG({ new: true })}>
								＋ {t.newWorkGroup}
							</button>
						)}
					</div>
				)}
				{projectId && screen === "lines" && (
					<LineManager
						lines={data.lines}
						stations={data.stations}
						stationsOnLine={data.stationsOnLine}
						stopPatterns={data.stopPatterns}
						onUpdate={(patch) =>
							setData((d) => ({ ...d, ...patch }))
						}
						onOpenStopPatternWizard={() => {
							setEditingPattern(null);
							setShowStopPattern(true);
						}}
						onEditStopPattern={(p) => {
							setEditingPattern(p);
							setShowStopPattern(true);
						}}
						t={t}
					/>
				)}
			</AppShell>

			{showStopPattern && (
				<StopPatternWizard
					lines={data.lines}
					stations={data.stations}
					stationsOnLine={data.stationsOnLine}
					t={t}
					editPattern={editingPattern}
					onSave={(sp) =>
						setData((d) => ({
							...d,
							stopPatterns: editingPattern
								? d.stopPatterns.map((p) =>
										p.id === sp.id ? sp : p
									)
								: [...d.stopPatterns, sp],
						}))
					}
					onClose={() => {
						setShowStopPattern(false);
						setEditingPattern(null);
					}}
				/>
			)}

			{/* Entity dialogs */}
			{editingProject && (
				<ProjectDialog
					project={editingProject.project}
					onSave={saveProject}
					onClose={() => setEditingProject(null)}
					t={t}
				/>
			)}
			{editingWG && (
				<WorkGroupDialog
					workGroup={editingWG.wg}
					onSave={saveWG}
					onClose={() => setEditingWG(null)}
					t={t}
				/>
			)}
			{editingWork && (
				<WorkDialog
					work={editingWork.work}
					onSave={(draft) => saveWork(editingWork.wgId, draft)}
					onClose={() => setEditingWork(null)}
					t={t}
				/>
			)}
			{confirmDialog && (
				<ConfirmDialog
					title={confirmDialog.title}
					message={confirmDialog.message}
					onConfirm={confirmDialog.onConfirm}
					onClose={() => setConfirmDialog(null)}
				/>
			)}
			{contextMenu && (
				<ContextMenu
					x={contextMenu.x}
					y={contextMenu.y}
					items={contextMenu.items}
					onClose={() => setContextMenu(null)}
				/>
			)}
		</div>
	);
}
