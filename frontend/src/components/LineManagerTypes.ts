// LineManagerTypes.ts — Shared types, drafts, and model converters for LineManager
import type {
	Line as EntityLine,
	ProjectStation as EntityProjectStation,
	StationOnLine as EntityStationOnLine,
} from "../types/entities";
import type { Line, Station, StationOnLine } from "../types/model";

/* ─── Entity draft / update shapes ─── */
export type EntityLineDraft = Omit<
	EntityLine,
	"id" | "projectId" | "createdAt"
>;
export type EntityLineUpdate = Pick<EntityLine, "id"> &
	Omit<EntityLine, "id" | "projectId" | "createdAt">;

export type EntityStationDraft = Omit<
	EntityProjectStation,
	"id" | "projectId" | "createdAt"
>;
export type EntityStationUpdate = Pick<EntityProjectStation, "id"> &
	Omit<EntityProjectStation, "id" | "projectId" | "createdAt">;

export type EntitySolDraft = Omit<
	EntityStationOnLine,
	"id" | "projectId" | "createdAt"
>;
export type EntitySolUpdate = Pick<EntityStationOnLine, "id"> &
	Omit<EntityStationOnLine, "id" | "projectId" | "createdAt">;

/* ─── Local draft shapes ─── */

/* Local draft shape for the global Stations tab (geo fields may be ''). */
export type StationDraft = {
	stationName: string;
	fullName: string;
	longitude_deg: number | "";
	latitude_deg: number | "";
	onStationDetectRadius_m: number | "";
	alwaysShowHH: boolean;
};

/* Local draft shape for the line-stations tab. */
export type SolDraft = {
	stationId?: string;
	location_m: number;
	longitude_deg: number | "";
	latitude_deg: number | "";
	trackHiddenByDefault: boolean;
};

/* A station-on-line joined with its station for display in LineStationsTab. */
export type LineStationEntry = {
	station: Station;
} & StationOnLine;

/* Line dialog draft */
export type LineDraft = {
	id?: string;
	name: string;
	description: string;
};

/* ─── Model → entity-draft converters ─── */
export function modelLineToDraft(l: Line): EntityLineDraft {
	return {
		name: l.name,
		description: l.description,
	};
}

export function modelStationToDraft(s: Station): EntityStationDraft {
	return {
		name: s.stationName,
		fullName: s.fullName !== "" ? s.fullName : undefined,
		longitude: s.longitude_deg,
		latitude: s.latitude_deg,
		onStationDetectRadiusM: s.onStationDetectRadius_m,
		alwaysShowHh: s.alwaysShowHH,
	};
}

export function modelSolToDraft(sol: StationOnLine): EntitySolDraft {
	return {
		lineId: sol.lineId,
		projectStationId: sol.stationId,
		locationM: sol.location_m,
		longitude: sol.longitude_deg,
		latitude: sol.latitude_deg,
		trackHiddenByDefault: sol.trackHiddenByDefault,
	};
}
