import { createSelector } from "@reduxjs/toolkit";

import { currentShowingWorkGroupIdSelector } from "./workGroupsSelector";

import type { DateToNumberObjectType } from "../../utils/DateToNumberType";
import type { AppSelector } from "../store";
import type { Station } from "trvis-api";

export const stationListSelector: AppSelector<
	DateToNumberObjectType<Station>[]
> = (state) => state.stations.stationList;

export const isLoadingSelector: AppSelector<boolean> = (state) =>
	state.stations.isLoading;

export const currentPageFrom1Selector: AppSelector<number> = (state) =>
	state.stations.currentPageFrom1;

export const perPageSelector: AppSelector<number> = (state) =>
	state.stations.perPage;

export const totalItemsCountSelector: AppSelector<number> = (state) =>
	state.stations.totalItemsCount;

export const isEditingSelector: AppSelector<boolean> = (state) =>
	state.stations.isEditing;
export const editErrorMessageSelector: AppSelector<string | undefined> = (
	state
) => state.stations.editErrorMessage;
export const editTargetStationIdSelector: AppSelector<string | undefined> = (
	state
) => state.stations.editTargetStationId;
export const editTargetStationSelector = createSelector(
	[
		stationListSelector,
		editTargetStationIdSelector,
		currentShowingWorkGroupIdSelector,
	],
	(
		stationList,
		editTargetStationId,
		currentShowingWorkGroupId
	): DateToNumberObjectType<Station> =>
		(editTargetStationId === undefined
			? undefined
			: stationList.find(
					(station) => station.stationsId === editTargetStationId
				)) ?? {
			stationsId: undefined,
			workGroupsId: currentShowingWorkGroupId,
			name: "",
			description: "",

			createdAt: undefined,

			locationKm: 0,
			recordType: 0,
			locationLonlat: undefined,
			onStationDetectRadiusM: undefined,
		}
);
export const isEditExistingStationSelector: AppSelector<boolean> = (state) =>
	state.stations.editTargetStationId !== undefined;

export const currentShowingStationSelector: AppSelector<
	DateToNumberObjectType<Station> | undefined
> = (state) => state.stations.currentShowingStation;
export const currentShowingStationIdSelector: AppSelector<
	string | undefined
> = (state) => currentShowingStationSelector(state)?.stationsId;
export const currentShowingStationParentIdSelector: AppSelector<
	string | undefined
> = (state) => currentShowingStationSelector(state)?.workGroupsId;
