import { createSelector } from "@reduxjs/toolkit";

import { currentShowingWorkGroupIdSelector } from "./workGroupsSelector";

import type { Work } from "../../oas";
import type { DateToNumberObjectType } from "../../utils/DateToNumberType";
import type { AppSelector } from "../store";

export const workListSelector: AppSelector<DateToNumberObjectType<Work>[]> = (
	state
) => state.works.workList;

export const isLoadingSelector: AppSelector<boolean> = (state) =>
	state.works.isLoading;

export const currentPageFrom1Selector: AppSelector<number> = (state) =>
	state.works.currentPageFrom1;

export const perPageSelector: AppSelector<number> = (state) =>
	state.works.perPage;

export const totalItemsCountSelector: AppSelector<number> = (state) =>
	state.works.totalItemsCount;

export const isEditingSelector: AppSelector<boolean> = (state) =>
	state.works.isEditing;
export const editErrorMessageSelector: AppSelector<string | undefined> = (
	state
) => state.works.editErrorMessage;
export const editTargetWorkIdSelector: AppSelector<string | undefined> = (
	state
) => state.works.editTargetWorkId;
export const editTargetWorkSelector = createSelector(
	[
		workListSelector,
		editTargetWorkIdSelector,
		currentShowingWorkGroupIdSelector,
	],
	(
		workList,
		editTargetWorkId,
		currentShowingWorkGroupId
	): DateToNumberObjectType<Work> =>
		(editTargetWorkId === undefined
			? undefined
			: workList.find((work) => work.worksId === editTargetWorkId)) ?? {
			worksId: undefined,
			workGroupsId: currentShowingWorkGroupId,
			name: "",
			description: "",
			affectDate: undefined,
			remarks: "",

			createdAt: undefined,

			affixContent: undefined,
			affixContentType: undefined,

			hasETrainTimetable: false,
			eTrainTimetableContentType: undefined,
			eTrainTimetableContent: undefined,
		}
);
export const isEditExistingWorkSelector: AppSelector<boolean> = (state) =>
	state.works.editTargetWorkId !== undefined;

export const currentShowingWorkSelector: AppSelector<
	DateToNumberObjectType<Work> | undefined
> = (state) => state.works.currentShowingWork;
export const currentShowingWorkIdSelector: AppSelector<string | undefined> = (
	state
) => currentShowingWorkSelector(state)?.worksId;
