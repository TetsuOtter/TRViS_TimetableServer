export const WORK_GROUPS_ID_PLACEHOLDER_KEY = "workGroupsId";

export const getPathToWorkGroupList = () => "/work_groups";
export const getPathToWorkList = (workGroupsId: string) =>
	`${getPathToWorkGroupList()}/${workGroupsId}/works`;
