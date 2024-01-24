import { createSelector } from "@reduxjs/toolkit";

import { WorkGroupPrivilegeTypeEnum } from "../../oas";

import type { WorkGroup } from "../../oas";
import type { DateToNumberObjectType } from "../../utils/DateToNumberType";
import type { AppSelector } from "../store";

export const workGroupListSelector: AppSelector<
	DateToNumberObjectType<WorkGroup>[]
> = (state) => state.workGroups.workGroupList;

export const isLoadingSelector: AppSelector<boolean> = (state) =>
	state.workGroups.isLoading;

export const currentPageFrom1Selector: AppSelector<number> = (state) =>
	state.workGroups.currentPageFrom1;

export const perPageSelector: AppSelector<number> = (state) =>
	state.workGroups.perPage;

export const totalItemsCountSelector: AppSelector<number> = (state) =>
	state.workGroups.totalItemsCount;

export const isEditingSelector: AppSelector<boolean> = (state) =>
	state.workGroups.isEditing;
export const editErrorMessageSelector: AppSelector<string | undefined> = (
	state
) => state.workGroups.editErrorMessage;
export const editTargetWorkGroupIdSelector: AppSelector<string | undefined> = (
	state
) => state.workGroups.editTargetWorkGroupId;
export const editTargetWorkGroupSelector = createSelector(
	[workGroupListSelector, editTargetWorkGroupIdSelector],
	(workGroupList, editTargetWorkGroupId): DateToNumberObjectType<WorkGroup> =>
		(editTargetWorkGroupId === undefined
			? undefined
			: workGroupList.find(
					(workGroup) => workGroup.workGroupsId === editTargetWorkGroupId
				)) ?? {
			workGroupsId: undefined,
			name: "",
			description: "",
			privilegeType: undefined,
			createdAt: undefined,
		}
);
export const isEditExistingWorkGroupSelector: AppSelector<boolean> = (state) =>
	state.workGroups.editTargetWorkGroupId !== undefined;

export const currentShowingWorkGroupSelector: AppSelector<
	DateToNumberObjectType<WorkGroup> | undefined
> = (state) => state.workGroups.currentShowingWorkGroup;
export const currentShowingWorkGroupIdSelector: AppSelector<
	string | undefined
> = (state) => currentShowingWorkGroupSelector(state)?.workGroupsId;
export const currentShowingWorkGroupPrivilegeSelector: AppSelector<
	WorkGroupPrivilegeTypeEnum | undefined
> = (state) => currentShowingWorkGroupSelector(state)?.privilegeType;
export const canWriteToCurrentShowingWorkGroupSelector: AppSelector<boolean> = (
	state
) => {
	const privilegeType = currentShowingWorkGroupPrivilegeSelector(state);
	if (privilegeType == null) return false;
	switch (privilegeType) {
		case WorkGroupPrivilegeTypeEnum.Write:
		case WorkGroupPrivilegeTypeEnum.Admin:
			return true;
		case WorkGroupPrivilegeTypeEnum.Read:
			return false;
	}
};
