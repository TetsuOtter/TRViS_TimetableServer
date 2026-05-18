// App — top-level app, routing & sidebar tree. Ported from App.jsx.
// The prototype's tweaks-panel is dropped; theme/lang live in the top bar
// via SettingsContext. Layout is fixed to the design's default ("sidebar").
import { useEffect, useMemo, useState } from "react";

import { useQueryClient } from "@tanstack/react-query";

import {
	fromApiStopPattern,
	fromApiStopPatternRow,
	fromApiTrain,
} from "../api/adapters";
import {
	useCreateLine,
	useDeleteLine,
	useLines,
	useUpdateLine,
} from "../api/hooks/useLines";
import {
	useCreateProject,
	useDeleteProject,
	useProjects,
	useUpdateProject,
} from "../api/hooks/useProjects";
import {
	useCreateProjectStation,
	useDeleteProjectStation,
	useProjectStations,
	useUpdateProjectStation,
} from "../api/hooks/useProjectStations";
import {
	useCreateStationOnLine,
	useDeleteStationOnLine,
	useStationsOnLine,
	useUpdateStationOnLine,
} from "../api/hooks/useStationsOnLine";
import {
	useCreateStopPatternRows,
	useDeleteStopPatternRow,
	useStopPatternRows,
} from "../api/hooks/useStopPatternRows";
import {
	useCreateStopPattern,
	useDeleteStopPattern,
	useStopPatterns,
	useUpdateStopPattern,
} from "../api/hooks/useStopPatterns";
import {
	fetchTimetableRows,
	useCreateTimetableRow,
	useDeleteTimetableRow,
	useTimetableRows,
} from "../api/hooks/useTimetableRows";
import {
	useCreateTrain,
	useDeleteTrain,
	useTrains,
	useUpdateTrain,
} from "../api/hooks/useTrains";
import {
	useCreateWorkGroup,
	useDeleteWorkGroup,
	useUpdateWorkGroup,
	useWorkGroups,
} from "../api/hooks/useWorkGroups";
import {
	useCreateWork,
	useDeleteWork,
	useUpdateWork,
	useWorks,
} from "../api/hooks/useWorks";
import { stopPatternRowApi } from "../api/instances";
import { queryKeys } from "../api/queryKeys";
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

import type { AppliedRow } from "../components/ApplyPatternDialog";
import type { ContextMenuItem } from "../components/EntityDialogs";
import type {
	Line as EntityLine,
	Project as EntityProject,
	ProjectStation as EntityProjectStation,
	StationOnLine as EntityStationOnLine,
	StopPattern as EntityStopPattern,
	StopPatternRow as EntityStopPatternRow,
	TimetableRow as EntityTimetableRow,
	Train as EntityTrain,
	Work as EntityWork,
} from "../types/entities";
import type {
	AppData,
	Line,
	Project,
	Station,
	StationOnLine,
	StopPattern,
	StopPatternRow as ModelStopPatternRow,
	TimetableRow as ModelTimetableRow,
	Train as ModelTrain,
	Work,
	WorkGroup,
} from "../types/model";

function entityTimetableRowToModel(
	row: EntityTimetableRow,
	stationsById: Map<string, Station>
): ModelTimetableRow {
	const pad2 = (n: number) => String(n).padStart(2, "0");
	const toTimeStr = (
		hh: number | undefined,
		mm: number | undefined,
		ss: number | undefined
	): string => {
		if (hh === undefined || mm === undefined || ss === undefined) return "";
		return `${pad2(hh)}:${pad2(mm)}:${pad2(ss)}`;
	};
	const station =
		row.stationId !== undefined ? stationsById.get(row.stationId) : undefined;
	return {
		id: row.id,
		stationName: station?.stationName ?? "",
		fullName: station?.fullName ?? "",
		arrive: toTimeStr(row.arriveTimeHh, row.arriveTimeMm, row.arriveTimeSs),
		departure: toTimeStr(
			row.departureTimeHh,
			row.departureTimeMm,
			row.departureTimeSs
		),
		arriveDisplayText: row.arriveStr,
		departureDisplayText: row.departureStr,
		trackName: "",
		isPass: row.isPass ?? false,
		isOperationOnlyStop: row.isOperationOnlyStop ?? false,
		isLastStop: row.isLastStop,
		hasBracket: row.hasBracket,
		recordType: "station",
		driveTime_MM: row.driveTimeMm ?? 0,
		driveTime_SS: row.driveTimeSs ?? 0,
		runInLimit: row.runInLimit ?? "",
		runOutLimit: row.runOutLimit ?? "",
		remarks: row.remarks ?? "",
		workType: row.workType ?? "",
	};
}

function entityTrainToModel(
	train: EntityTrain,
	timetableRows: ModelTimetableRow[]
): ModelTrain {
	return {
		id: train.id,
		trainNumber: train.trainNumber,
		direction: train.direction === -1 ? -1 : 1,
		destination: train.destination ?? "",
		maxSpeed: train.maxSpeed ?? "",
		speedType: train.speedType ?? "",
		nominalTractiveCapacity: train.nominalTractiveCapacity ?? "",
		carCount: train.carCount ?? 0,
		workType: "",
		dayCount: train.dayCount,
		isRideOnMoving: train.isRideOnMoving ?? false,
		beginRemarks: train.beginRemarks ?? "",
		afterRemarks: train.afterRemarks ?? "",
		remarks: train.remarks ?? "",
		beforeDeparture: train.beforeDeparture ?? "",
		afterArrive: train.afterArrive ?? "",
		trainInfo: train.trainInfo ?? "",
		nextTrainId: "",
		timetableRows,
	};
}

function entityWorkToModel(work: EntityWork, trains: ModelTrain[]): Work {
	return {
		id: work.id,
		name: work.name,
		affectDate:
			work.affectDate !== undefined
				? work.affectDate.toISOString().slice(0, 10)
				: "",
		remarks: work.remarks ?? "",
		trains,
	};
}

function entityLineToModel(line: EntityLine): Line {
	return {
		id: line.id,
		name: line.name,
		description: line.description,
	};
}

function entityProjectStationToModel(ps: EntityProjectStation): Station {
	return {
		id: ps.id,
		stationName: ps.name,
		fullName: ps.fullName ?? "",
		longitude_deg: ps.longitude,
		latitude_deg: ps.latitude,
		onStationDetectRadius_m: ps.onStationDetectRadiusM,
		alwaysShowHH: ps.alwaysShowHh,
	};
}

function entityStationOnLineToModel(sol: EntityStationOnLine): StationOnLine {
	return {
		id: sol.id,
		lineId: sol.lineId,
		stationId: sol.projectStationId,
		location_m: sol.locationM,
		longitude_deg: sol.longitude,
		latitude_deg: sol.latitude,
		trackHiddenByDefault: sol.trackHiddenByDefault,
	};
}

function entityStopRowToModel(r: EntityStopPatternRow): ModelStopPatternRow {
	return {
		stationId: r.projectStationId,
		trackName: r.trackName,
		trackHidden: r.trackHidden,
		driveTime_MM: r.driveTimeMm,
		driveTime_SS: r.driveTimeSs,
		dwellTime_MM: r.dwellTimeMm,
		dwellTime_SS: r.dwellTimeSs,
		isOperationOnlyStop: r.isOperationOnlyStop,
		isPass: r.isPass,
		showArrive: r.showArrive,
		showDeparture: r.showDeparture,
		arrive: r.arriveStr,
		departure: r.departureStr,
		runInLimit: r.runInLimit,
		runOutLimit: r.runOutLimit,
		remarks: r.remarks,
		alwaysShowHH: r.alwaysShowHh,
	};
}

function entityStopPatternToModel(
	sp: EntityStopPattern,
	rows: ModelStopPatternRow[]
): StopPattern {
	return {
		id: sp.id,
		name: sp.name,
		lineId: sp.lineId,
		fromStationId: sp.fromProjectStationId ?? "",
		toStationId: sp.toProjectStationId ?? "",
		direction: sp.direction === -1 ? -1 : 1,
		rows,
		stopRows: rows,
	};
}

function modelStopRowToEntityDraft(
	r: ModelStopPatternRow
): Omit<
	EntityStopPatternRow,
	"id" | "projectId" | "stopPatternId" | "createdAt"
> {
	return {
		projectStationId: r.stationId,
		trackName: r.trackName,
		trackHidden: r.trackHidden,
		driveTimeMm: r.driveTime_MM,
		driveTimeSs: r.driveTime_SS,
		dwellTimeMm: r.dwellTime_MM,
		dwellTimeSs: r.dwellTime_SS,
		isOperationOnlyStop: r.isOperationOnlyStop,
		isPass: r.isPass,
		showArrive: r.showArrive,
		showDeparture: r.showDeparture,
		arriveStr: r.arrive,
		departureStr: r.departure,
		runInLimit:
			r.runInLimit === "" || r.runInLimit === undefined
				? undefined
				: r.runInLimit,
		runOutLimit:
			r.runOutLimit === "" || r.runOutLimit === undefined
				? undefined
				: r.runOutLimit,
		remarks: r.remarks,
		alwaysShowHh: r.alwaysShowHH,
	};
}

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
	const [currentTrain, setCurrentTrain] = useState<string | null>(null);
	const [screen, setScreen] = useState<Screen>("projects");

	const { data: apiWorks } = useWorks(currentWG ?? "");
	const createWorkMutation = useCreateWork(currentWG ?? "");
	const updateWorkMutation = useUpdateWork(currentWG ?? "");
	const deleteWorkMutation = useDeleteWork(currentWG ?? "");

	const { data: apiTrains } = useTrains(currentWork ?? "");
	const createTrainMutation = useCreateTrain(currentWork ?? "");
	const updateTrainMutation = useUpdateTrain(currentWork ?? "");
	const deleteTrainMutation = useDeleteTrain(currentWork ?? "");

	const { data: apiTimetableRows } = useTimetableRows(currentTrain ?? "");
	const createTimetableRowMutation = useCreateTimetableRow();
	const deleteTimetableRowMutation = useDeleteTimetableRow();

	const [currentLine, setCurrentLine] = useState<string | null>(null);
	const { data: apiLines } = useLines(projectId ?? "");
	const createLineMutation = useCreateLine(projectId ?? "");
	const updateLineMutation = useUpdateLine(projectId ?? "");
	const deleteLineMutation = useDeleteLine(projectId ?? "");

	const { data: apiProjectStations } = useProjectStations(projectId ?? "");
	const createProjectStationMutation = useCreateProjectStation(projectId ?? "");
	const updateProjectStationMutation = useUpdateProjectStation(projectId ?? "");
	const deleteProjectStationMutation = useDeleteProjectStation(projectId ?? "");

	const { data: apiStationsOnLine } = useStationsOnLine(currentLine ?? "");
	const createStationOnLineMutation = useCreateStationOnLine(currentLine ?? "");
	const updateStationOnLineMutation = useUpdateStationOnLine(currentLine ?? "");
	const deleteStationOnLineMutation = useDeleteStationOnLine(currentLine ?? "");

	const [showStopPattern, setShowStopPattern] = useState(false);
	const [editingPattern, setEditingPattern] = useState<StopPattern | null>(
		null
	);

	const { data: apiStopPatterns } = useStopPatterns(projectId ?? "");
	const createStopPatternMutation = useCreateStopPattern(projectId ?? "");
	const updateStopPatternMutation = useUpdateStopPattern(projectId ?? "");
	const deleteStopPatternMutation = useDeleteStopPattern(projectId ?? "");
	const createStopPatternRowsMutation = useCreateStopPatternRows();
	const deleteStopPatternRowMutation = useDeleteStopPatternRow();

	const { data: apiEditingRows } = useStopPatternRows(editingPattern?.id ?? "");

	const queryClient = useQueryClient();

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

	const modelProjectStations = (apiProjectStations ?? []).map(
		entityProjectStationToModel
	);

	const stationsById = new Map<string, Station>(
		modelProjectStations.map((s) => [s.id, s])
	);

	const modelTimetableRows = (apiTimetableRows ?? []).map((r) =>
		entityTimetableRowToModel(r, stationsById)
	);

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
						works: (apiWorks ?? [])
							.filter((w) => w.workGroupId === wg.id)
							.map((w) => {
								if (w.id !== currentWork) {
									return entityWorkToModel(w, []);
								}
								const trains = (apiTrains ?? []).map((tr) => {
									if (tr.id !== currentTrain) {
										return entityTrainToModel(tr, []);
									}
									return entityTrainToModel(tr, modelTimetableRows);
								});
								return entityWorkToModel(w, trains);
							}),
					})),
				}
			: undefined;
	const wg = project?.workGroups.find((g) => g.id === currentWG);
	const work = wg?.works.find((w) => w.id === currentWork);

	const modelLines = (apiLines ?? []).map(entityLineToModel);
	const modelStationsOnLine = (apiStationsOnLine ?? []).map(
		entityStationOnLineToModel
	);
	const modelStopPatterns = (apiStopPatterns ?? []).map((sp) =>
		entityStopPatternToModel(
			sp,
			sp.id === (editingPattern?.id ?? "")
				? (apiEditingRows ?? []).map(entityStopRowToModel)
				: []
		)
	);

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

	// Auto-select first line when navigating to the lines screen with no line selected.
	useEffect(() => {
		if (
			screen === "lines" &&
			currentLine === null &&
			(apiLines ?? []).length > 0
		) {
			setCurrentLine((apiLines ?? [])[0]?.id ?? null);
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [screen, apiLines]);

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
			setCurrentTrain(null);
		}
	};

	/* ─── Work CRUD ─── */
	const saveWork = (
		wgId: string,
		draft: Partial<Pick<Work, "id">> &
			Pick<Work, "name" | "affectDate" | "remarks">
	) => {
		if (project === undefined) return;
		const onError = (e: Error) => alert(e.message);
		if (draft.id !== undefined && draft.id !== "") {
			const existing = (apiWorks ?? []).find((w) => w.id === draft.id);
			updateWorkMutation.mutate(
				{
					id: draft.id,
					name: draft.name,
					description: existing?.description ?? "",
					affectDate:
						draft.affectDate !== undefined && draft.affectDate !== ""
							? new Date(draft.affectDate)
							: undefined,
					affixContentType: existing?.affixContentType,
					affixContent: existing?.affixContent,
					remarks: draft.remarks,
					hasETrainTimetable: existing?.hasETrainTimetable,
					eTrainTimetableContentType:
						existing?.eTrainTimetableContentType,
					eTrainTimetableContent: existing?.eTrainTimetableContent,
				},
				{ onError }
			);
		} else {
			createWorkMutation.mutate(
				{
					name: draft.name,
					description: "",
					affectDate:
						draft.affectDate !== undefined && draft.affectDate !== ""
							? new Date(draft.affectDate)
							: undefined,
					remarks: draft.remarks,
				},
				{
					onError,
					onSuccess: (created) => {
						const newId: string =
							(created as { worksId?: string }).worksId ?? "";
						setCurrentWG(wgId);
						if (newId !== "") {
							setCurrentWork(newId);
						}
						setScreen("work");
					},
				}
			);
		}
	};
	const deleteWork = (workId: string) => {
		deleteWorkMutation.mutate(workId, {
			onError: (e: Error) => alert(e.message),
		});
		if (currentWork === workId) {
			setCurrentWork(null);
			setCurrentTrain(null);
		}
	};

	/* ─── Train CRUD (wired from WorkBrowser) ─── */
	const handleCreateTrain = (
		draft: Omit<EntityTrain, "id" | "workId" | "createdAt">
	) => {
		createTrainMutation.mutate(draft, {
			onError: (e: Error) => alert(e.message),
		});
	};
	const handleUpdateTrain = (
		vars: Pick<EntityTrain, "id"> &
			Omit<EntityTrain, "id" | "workId" | "createdAt">
	) => {
		const existing = (apiTrains ?? []).find((t) => t.id === vars.id);
		updateTrainMutation.mutate(
			{ ...vars, description: existing?.description ?? "" },
			{ onError: (e: Error) => alert(e.message) }
		);
	};
	const handleDeleteTrain = (trainId: string) => {
		deleteTrainMutation.mutate(trainId, {
			onError: (e: Error) => alert(e.message),
		});
		if (currentTrain === trainId) {
			setCurrentTrain(null);
		}
	};

	/* ─── ApplyPattern handler ─── */

	// Parse "HH:MM:SS" string into a time component at position index (0=HH, 1=MM, 2=SS).
	// Returns undefined when the string is empty (open-end stations have no departure).
	const parseTimePart = (t: string, idx: number): number | undefined =>
		t !== "" ? parseInt(t.split(":")[idx] ?? "0", 10) : undefined;

	const buildRowDraft = (r: AppliedRow) => ({
		stationId: r.stationId,
		driveTimeMm: r.driveTime_MM,
		driveTimeSs: r.driveTime_SS,
		isPass: r.isPass,
		isOperationOnlyStop: r.isOperationOnlyStop,
		hasBracket: r.hasBracket,
		isLastStop: r.isLastStop,
		arriveTimeHh: parseTimePart(r.arrive, 0),
		arriveTimeMm: parseTimePart(r.arrive, 1),
		arriveTimeSs: parseTimePart(r.arrive, 2),
		departureTimeHh: parseTimePart(r.departure, 0),
		departureTimeMm: parseTimePart(r.departure, 1),
		departureTimeSs: parseTimePart(r.departure, 2),
		remarks: r.remarks !== "" ? r.remarks : undefined,
		workType: r.workType !== "" ? r.workType : undefined,
	});

	const handleApplyPattern = async ({
		rows,
		direction,
		destination,
		existingTrain,
	}: {
		rows: AppliedRow[];
		direction: number;
		destination: string;
		existingTrain: { id: string } | null;
	}) => {
		try {
			if (existingTrain !== null) {
				// Existing train: delete all current rows, then create new ones
				const existingRows = await fetchTimetableRows(
					queryClient,
					existingTrain.id
				);
				for (const row of existingRows) {
					await deleteTimetableRowMutation.mutateAsync({
						trainId: existingTrain.id,
						rowId: row.id,
					});
				}
				for (const r of rows) {
					await createTimetableRowMutation.mutateAsync({
						trainId: existingTrain.id,
						draft: buildRowDraft(r),
					});
				}
			} else {
				// No existing train: create a new train, then create rows
				const apiTrain = await createTrainMutation.mutateAsync({
					description: "",
					trainNumber: "0000M",
					direction,
					destination: destination !== "" ? destination : undefined,
					maxSpeed: "100",
					speedType: "近郊型",
					nominalTractiveCapacity: undefined,
					carCount: 10,
					dayCount: 0,
					isRideOnMoving: false,
					beginRemarks: undefined,
					afterRemarks: undefined,
					remarks: undefined,
					beforeDeparture: undefined,
					afterArrive: undefined,
					trainInfo: undefined,
				});
				const newTrainId = fromApiTrain(apiTrain).id;
				for (const r of rows) {
					await createTimetableRowMutation.mutateAsync({
						trainId: newTrainId,
						draft: buildRowDraft(r),
					});
				}
			}
		} catch (e) {
			alert(e instanceof Error ? e.message : String(e));
		}
	};

	/* ─── Line CRUD (wired from LineManager) ─── */
	const handleCreateLine = (
		draft: Omit<EntityLine, "id" | "projectId" | "createdAt">
	) => {
		createLineMutation.mutate(draft, {
			onError: (e: Error) => alert(e.message),
		});
	};
	const handleUpdateLine = (
		vars: Pick<EntityLine, "id"> &
			Omit<EntityLine, "id" | "projectId" | "createdAt">
	) => {
		updateLineMutation.mutate(vars, {
			onError: (e: Error) => alert(e.message),
		});
	};
	const handleDeleteLine = (lineId: string) => {
		deleteLineMutation.mutate(lineId, {
			onError: (e: Error) => alert(e.message),
		});
		if (currentLine === lineId) {
			setCurrentLine(null);
		}
	};

	/* ─── ProjectStation CRUD (wired from LineManager) ─── */
	const handleCreateStation = (
		draft: Omit<EntityProjectStation, "id" | "projectId" | "createdAt">
	) => {
		createProjectStationMutation.mutate(draft, {
			onError: (e: Error) => alert(e.message),
		});
	};
	const handleUpdateStation = (
		vars: Pick<EntityProjectStation, "id"> &
			Omit<EntityProjectStation, "id" | "projectId" | "createdAt">
	) => {
		updateProjectStationMutation.mutate(vars, {
			onError: (e: Error) => alert(e.message),
		});
	};
	const handleDeleteStation = (stationId: string) => {
		deleteProjectStationMutation.mutate(stationId, {
			onError: (e: Error) => alert(e.message),
		});
	};

	/* ─── StationOnLine CRUD (wired from LineManager) ─── */
	const handleCreateStationOnLine = (
		draft: Omit<EntityStationOnLine, "id" | "projectId" | "createdAt">
	) => {
		createStationOnLineMutation.mutate(draft, {
			onError: (e: Error) => alert(e.message),
		});
	};
	const handleUpdateStationOnLine = (
		vars: Pick<EntityStationOnLine, "id"> &
			Omit<EntityStationOnLine, "id" | "projectId" | "createdAt">
	) => {
		updateStationOnLineMutation.mutate(vars, {
			onError: (e: Error) => alert(e.message),
		});
	};
	const handleDeleteStationOnLine = (stationOnLineId: string) => {
		deleteStationOnLineMutation.mutate(stationOnLineId, {
			onError: (e: Error) => alert(e.message),
		});
	};
	const handleReorderStationsOnLine = (
		updates: (Pick<EntityStationOnLine, "id"> &
			Omit<EntityStationOnLine, "id" | "projectId" | "createdAt">)[]
	) => {
		updates.forEach((vars) => {
			updateStationOnLineMutation.mutate(vars, {
				onError: (e: Error) => alert(e.message),
			});
		});
	};

	/* ─── StopPattern CRUD (wired from LineManager / StopPatternWizard) ─── */
	const handleSaveStopPattern = async (sp: StopPattern) => {
		try {
			const rowDrafts = (sp.stopRows ?? sp.rows ?? []).map((r, idx) => ({
				...modelStopRowToEntityDraft(r),
				sortKey: idx,
			}));
			const patternDraft = {
				lineId: sp.lineId,
				name: sp.name,
				fromProjectStationId:
					sp.fromStationId !== "" ? sp.fromStationId : undefined,
				toProjectStationId: sp.toStationId !== "" ? sp.toStationId : undefined,
				direction: sp.direction as number | undefined,
			};
			if (editingPattern !== null) {
				await updateStopPatternMutation.mutateAsync({
					id: sp.id,
					...patternDraft,
				});
				const existing = apiEditingRows ?? [];
				for (const r of existing) {
					await deleteStopPatternRowMutation.mutateAsync({
						stopPatternId: sp.id,
						id: r.id,
					});
				}
				if (rowDrafts.length > 0) {
					await createStopPatternRowsMutation.mutateAsync({
						stopPatternId: sp.id,
						drafts: rowDrafts,
					});
				}
			} else {
				const created =
					await createStopPatternMutation.mutateAsync(patternDraft);
				const newId = fromApiStopPattern(created).id;
				if (rowDrafts.length > 0) {
					await createStopPatternRowsMutation.mutateAsync({
						stopPatternId: newId,
						drafts: rowDrafts,
					});
				}
			}
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stopPatterns(projectId ?? ""),
			});
			void queryClient.invalidateQueries({ queryKey: ["stopPatterns"] });
			setShowStopPattern(false);
			setEditingPattern(null);
		} catch (e) {
			alert(e instanceof Error ? e.message : String(e));
		}
	};

	const handleDeleteStopPattern = (id: string) => {
		deleteStopPatternMutation.mutate(id, {
			onError: (e: Error) => alert(e.message),
		});
	};

	const handleDuplicateStopPattern = async (p: StopPattern) => {
		try {
			const rows = await queryClient.fetchQuery({
				queryKey: queryKeys.stopPatternRows(p.id),
				queryFn: () =>
					stopPatternRowApi
						.getStopPatternRowList({ stopPatternId: p.id })
						.then((l) => l.map(fromApiStopPatternRow)),
			});
			const created = await createStopPatternMutation.mutateAsync({
				lineId: p.lineId,
				name: p.name + " (コピー)",
				fromProjectStationId:
					p.fromStationId !== "" ? p.fromStationId : undefined,
				toProjectStationId: p.toStationId !== "" ? p.toStationId : undefined,
				direction: p.direction as number | undefined,
			});
			const newId = fromApiStopPattern(created).id;
			const drafts = rows.map((r, idx) => ({
				projectStationId: r.projectStationId,
				sortKey: idx,
				trackName: r.trackName,
				trackHidden: r.trackHidden,
				isOperationOnlyStop: r.isOperationOnlyStop,
				isPass: r.isPass,
				driveTimeMm: r.driveTimeMm,
				driveTimeSs: r.driveTimeSs,
				dwellTimeMm: r.dwellTimeMm,
				dwellTimeSs: r.dwellTimeSs,
				showArrive: r.showArrive,
				showDeparture: r.showDeparture,
				arriveStr: r.arriveStr,
				departureStr: r.departureStr,
				runInLimit: r.runInLimit,
				runOutLimit: r.runOutLimit,
				remarks: r.remarks,
				alwaysShowHh: r.alwaysShowHh,
			}));
			if (drafts.length > 0) {
				await createStopPatternRowsMutation.mutateAsync({
					stopPatternId: newId,
					drafts,
				});
			}
		} catch (e) {
			alert(e instanceof Error ? e.message : String(e));
		}
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

	// Provide live rows to the wizard after the rows query settles.
	// editingPattern is a frozen snapshot; liveEditingPattern tracks
	// the live modelStopPatterns entry (including loaded rows).
	const liveEditingPattern =
		editingPattern !== null
			? (modelStopPatterns.find((sp) => sp.id === editingPattern.id) ??
				editingPattern)
			: null;

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
			onAddWork={(w) => {
					setCurrentWG(w.id);
					setEditingWork({ wgId: w.id, new: true });
				}}
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
							onClick: () => {
								setCurrentWG(wgRef.id);
								setEditingWork({
									wgId: wgRef.id,
									new: true,
								});
							},
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
										deleteWork(w.id),
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
						onCreateTrain={handleCreateTrain}
						onUpdateTrain={handleUpdateTrain}
						onDeleteTrain={handleDeleteTrain}
						onSelectTrain={setCurrentTrain}
						onOpenStopPatternWizard={() =>
							setShowStopPattern(true)
						}
						onApplyPattern={(args) => {
							void handleApplyPattern(args);
						}}
						stopPatterns={modelStopPatterns}
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
						lines={modelLines}
						stations={modelProjectStations}
						stationsOnLine={modelStationsOnLine}
						stopPatterns={modelStopPatterns}
						activeLineId={currentLine ?? ""}
						onSelectLine={setCurrentLine}
						onCreateLine={handleCreateLine}
						onUpdateLine={handleUpdateLine}
						onDeleteLine={handleDeleteLine}
						onCreateStation={handleCreateStation}
						onUpdateStation={handleUpdateStation}
						onDeleteStation={handleDeleteStation}
						onCreateStationOnLine={handleCreateStationOnLine}
						onUpdateStationOnLine={handleUpdateStationOnLine}
						onDeleteStationOnLine={handleDeleteStationOnLine}
						onReorderStationsOnLine={handleReorderStationsOnLine}
						onOpenStopPatternWizard={() => {
							setEditingPattern(null);
							setShowStopPattern(true);
						}}
						onEditStopPattern={(p) => {
							setEditingPattern(p);
							setShowStopPattern(true);
						}}
						onDeleteStopPattern={handleDeleteStopPattern}
						onDuplicateStopPattern={(p) => {
							void handleDuplicateStopPattern(p);
						}}
						t={t}
					/>
				)}
			</AppShell>

			{showStopPattern &&
				(editingPattern === null || apiEditingRows !== undefined) && (
					<StopPatternWizard
						key={editingPattern?.id ?? "new"}
						lines={modelLines}
						stations={modelProjectStations}
						stationsOnLine={modelStationsOnLine}
						t={t}
						editPattern={liveEditingPattern}
						onSave={(sp) => {
							void handleSaveStopPattern(sp);
						}}
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
