import { createSelector } from "@reduxjs/toolkit";

import { currentShowingWorkIdSelector } from "./worksSelector";

import type { Train } from "../../oas";
import type { DateToNumberObjectType } from "../../utils/DateToNumberType";
import type { AppSelector } from "../store";

export const trainListSelector: AppSelector<DateToNumberObjectType<Train>[]> = (
	state
) => state.trains.trainList;

export const isLoadingSelector: AppSelector<boolean> = (state) =>
	state.trains.isLoading;

export const currentPageFrom1Selector: AppSelector<number> = (state) =>
	state.trains.currentPageFrom1;

export const perPageSelector: AppSelector<number> = (state) =>
	state.trains.perPage;

export const totalItemsCountSelector: AppSelector<number> = (state) =>
	state.trains.totalItemsCount;

export const isEditingSelector: AppSelector<boolean> = (state) =>
	state.trains.isEditing;
export const editErrorMessageSelector: AppSelector<string | undefined> = (
	state
) => state.trains.editErrorMessage;
export const editTargetTrainIdSelector: AppSelector<string | undefined> = (
	state
) => state.trains.editTargetTrainId;
export const editTargetTrainSelector = createSelector(
	[trainListSelector, editTargetTrainIdSelector, currentShowingWorkIdSelector],
	(
		trainList,
		editTargetTrainId,
		currentShowingWorkId
	): DateToNumberObjectType<Train> =>
		(editTargetTrainId === undefined
			? undefined
			: trainList.find((train) => train.trainsId === editTargetTrainId)) ?? {
			trainsId: undefined,
			worksId: currentShowingWorkId,

			description: "",
			trainNumber: "",
			direction: 1,
			dayCount: 0,

			createdAt: undefined,

			maxSpeed: undefined,
			speedType: undefined,
			nominalTractiveCapacity: undefined,
			carCount: undefined,
			destination: undefined,
			beginRemarks: undefined,
			afterRemarks: undefined,
			remarks: undefined,
			beforeDeparture: undefined,
			afterArrive: undefined,
			trainInfo: undefined,
			isRideOnMoving: undefined,
		}
);
export const isEditExistingTrainSelector: AppSelector<boolean> = (state) =>
	state.trains.editTargetTrainId !== undefined;

export const currentShowingTrainSelector: AppSelector<
	DateToNumberObjectType<Train> | undefined
> = (state) => state.trains.currentShowingTrain;
export const currentShowingTrainIdSelector: AppSelector<string | undefined> = (
	state
) => currentShowingTrainSelector(state)?.trainsId;
