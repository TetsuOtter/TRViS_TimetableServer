export const WORK_GROUPS_ID_PLACEHOLDER_KEY = "workGroupsId";
export const WORKS_ID_PLACEHOLDER_KEY = "worksId";
export const TRAINS_ID_PLACEHOLDER_KEY = "trainsId";
export const STATIONS_ID_PLACEHOLDER_KEY = "stationsId";
export const STATION_TRACKS_ID_PLACEHOLDER_KEY = "stationTracksId";

const PATH_SEG_WORK_GROUPS = "work_groups";
const PATH_SEG_WORKS = "works";
const PATH_SEG_TRAINS = "trains";
const PATH_SEG_TIMETABLE_ROWS = "timetable_rows";
const PATH_SEG_STATIONS = "stations";
const PATH_SEG_STATION_TRACKS = "station_tracks";

const genPathToListGetter =
	<T extends string>(pathSegment: T) =>
	(): `/${T}` =>
		`/${pathSegment}`;

const genPathToOneGetter =
	<T extends string, TId extends string>(pathSegment: T) =>
	(
		id: TId
	): `${ReturnType<ReturnType<typeof genPathToListGetter<T>>>}/${TId}` =>
		`${genPathToListGetter(pathSegment)()}/${id}`;

const genPathToChildListGetter =
	<
		TParentPathSegment extends string,
		TParentId extends string,
		TChildPathSegment extends string,
	>(
		parentPathSegment: TParentPathSegment,
		childPathSegment: TChildPathSegment
	) =>
	(id: TParentId) =>
		`${genPathToOneGetter<TParentPathSegment, TParentId>(parentPathSegment)(id)}/${childPathSegment}`;

export const getPathToWorkGroupList = genPathToListGetter(PATH_SEG_WORK_GROUPS);
export const getPathToWorkGroupOne = genPathToOneGetter(PATH_SEG_WORK_GROUPS);

export const getPathToWorkList = genPathToChildListGetter(
	PATH_SEG_WORK_GROUPS,
	PATH_SEG_WORKS
);
export const getPathToWorkOne = genPathToOneGetter(PATH_SEG_WORKS);

export const getPathToTrainList = genPathToChildListGetter(
	PATH_SEG_WORKS,
	PATH_SEG_TRAINS
);
export const getPathToTrainOne = genPathToOneGetter(PATH_SEG_TRAINS);

export const getPathToTimetableRowList = genPathToChildListGetter(
	PATH_SEG_TRAINS,
	PATH_SEG_TIMETABLE_ROWS
);

export const getPathToStationList = genPathToChildListGetter(
	PATH_SEG_WORK_GROUPS,
	PATH_SEG_STATIONS
);
export const getPathToStationOne = genPathToOneGetter(PATH_SEG_STATIONS);

export const getPathToStationTrackList = genPathToChildListGetter(
	PATH_SEG_STATIONS,
	PATH_SEG_STATION_TRACKS
);
export const getPathToStationTrackOne = genPathToOneGetter(
	PATH_SEG_STATION_TRACKS
);
