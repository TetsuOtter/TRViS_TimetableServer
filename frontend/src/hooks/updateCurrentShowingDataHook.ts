import { useEffect } from "react";
import { useParams } from "react-router-dom";

import { useAppDispatch, useAppSelector } from "../redux/hooks";
import { currentShowingWorkGroupIdSelector } from "../redux/selectors/workGroupsSelector";
import { currentShowingWorkIdSelector } from "../redux/selectors/worksSelector";
import { setCurrentShowingWorkGroup } from "../redux/slices/workGroupsSlice";
import { setCurrentShowingWork } from "../redux/slices/worksSlice";
import {
	WORKS_ID_PLACEHOLDER_KEY,
	WORK_GROUPS_ID_PLACEHOLDER_KEY,
} from "../utils/getPathString";

export const useUpdateCurrentShowingWorkGroups = () => {
	const dispatch = useAppDispatch();
	const urlParams = useParams<{
		[WORK_GROUPS_ID_PLACEHOLDER_KEY]: string;
	}>();
	const workGroupsIdParam = urlParams[WORK_GROUPS_ID_PLACEHOLDER_KEY];
	const currentShowingWorkGroupsId = useAppSelector(
		currentShowingWorkGroupIdSelector
	);

	useEffect(() => {
		if (
			workGroupsIdParam == null ||
			currentShowingWorkGroupsId === workGroupsIdParam
		) {
			return;
		}

		dispatch(setCurrentShowingWorkGroup({ workGroupId: workGroupsIdParam }));
	}, [currentShowingWorkGroupsId, dispatch, workGroupsIdParam]);

	return workGroupsIdParam;
};

export const useUpdateCurrentShowingWorks = () => {
	const dispatch = useAppDispatch();
	const urlParams = useParams<{
		[WORKS_ID_PLACEHOLDER_KEY]: string;
	}>();
	const worksIdParam = urlParams[WORKS_ID_PLACEHOLDER_KEY];
	const currentShowingWorksId = useAppSelector(currentShowingWorkIdSelector);

	useEffect(() => {
		if (worksIdParam == null || currentShowingWorksId === worksIdParam) {
			return;
		}

		dispatch(setCurrentShowingWork({ workId: worksIdParam }));
	}, [currentShowingWorksId, dispatch, worksIdParam]);

	return worksIdParam;
};
