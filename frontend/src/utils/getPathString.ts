export const WORK_GROUPS_ID_PLACEHOLDER_KEY = "workGroupsId";

export const getPathToWorkGroupList = () => "/work_groups";
export const getPathToWorkGroupOne = (workGroupsId: string) =>
	`/work_groups/${workGroupsId}`;
export const getPathToWorkList = (workGroupsId: string) =>
	`${getPathToWorkGroupOne(workGroupsId)}/works`;
