import { memo, useCallback, useEffect, useMemo } from "react";
import { useParams } from "react-router-dom";

import { Add } from "@mui/icons-material";
import { Box, Button, Stack, Typography } from "@mui/material";
import { DataGrid } from "@mui/x-data-grid";
import { useTranslation } from "react-i18next";

import { useAppDispatch, useAppSelector } from "../redux/hooks";
import { isLoggedInSelector } from "../redux/selectors/authInfoSelector";
import {
	canWriteToCurrentShowingWorkGroupSelector,
	currentShowingWorkGroupIdSelector,
} from "../redux/selectors/workGroupsSelector";
import {
	currentPageFrom1Selector,
	isLoadingSelector,
	perPageSelector,
	totalItemsCountSelector,
	workListSelector,
} from "../redux/selectors/worksSelector";
import { setCurrentShowingWorkGroup } from "../redux/slices/workGroupsSlice";
import { reloadWorks, setIsEditing } from "../redux/slices/worksSlice";
import { PAGE_SIZE_OPTIONS, UUID_NULL } from "../utils/Constants";
import { WORK_GROUPS_ID_PLACEHOLDER_KEY } from "../utils/getPathString";

import type { Work } from "../oas";
import type { DateToNumberObjectType } from "../utils/DateToNumberType";
import type {
	GridColDef,
	GridPaginationModel,
	GridValueFormatterParams,
} from "@mui/x-data-grid";

const getRowId = (row: DateToNumberObjectType<Work>) =>
	row.worksId ?? UUID_NULL;

const useGridColDefList = (): GridColDef[] => {
	const {
		t,
		i18n: { language },
	} = useTranslation();

	return useMemo(
		() => [
			{
				field: "name",
				headerName: t("Name"),
				width: 200,
				sortable: false,
			},
			{
				field: "description",
				headerName: t("Description"),
				width: 280,
				sortable: false,
			},
			{
				field: "createdAt",
				headerName: t("Created At"),
				valueFormatter: (params: GridValueFormatterParams<number>) => {
					const date = new Date(params.value);
					return date.toLocaleString(language);
				},
				width: 200,
				sortable: false,
			},
			{
				field: "worksId",
				headerName: t("ID"),
				renderCell: (params) => (
					<Typography
						variant="body2"
						sx={{ fontFamily: "monospace" }}
						component="span">
						{params.value}
					</Typography>
				),
				width: 280,
				sortable: false,
			},
		],
		[language, t]
	);
};

const WorksPage = () => {
	const urlParams = useParams<{ [WORK_GROUPS_ID_PLACEHOLDER_KEY]: string }>();
	const urlParamsWorkGroupsId = urlParams[WORK_GROUPS_ID_PLACEHOLDER_KEY];
	const { t } = useTranslation();

	const dispatch = useAppDispatch();
	const currentShowingWorkGroupsId = useAppSelector(
		currentShowingWorkGroupIdSelector
	);
	const workList = useAppSelector(workListSelector);

	const isSignedIn = useAppSelector(isLoggedInSelector);
	const canWrite = useAppSelector(canWriteToCurrentShowingWorkGroupSelector);
	const isLoading = useAppSelector(isLoadingSelector);
	const currentPageFrom1 = useAppSelector(currentPageFrom1Selector);
	const perPage = useAppSelector(perPageSelector);
	const totalItemsCount = useAppSelector(totalItemsCountSelector);
	const columns = useGridColDefList();

	useEffect(() => {
		if (
			urlParamsWorkGroupsId != null &&
			urlParamsWorkGroupsId !== currentShowingWorkGroupsId
		) {
			dispatch(
				setCurrentShowingWorkGroup({
					workGroupId: urlParamsWorkGroupsId,
				})
			);
		}

		dispatch(reloadWorks());
	}, [currentShowingWorkGroupsId, dispatch, urlParamsWorkGroupsId, isSignedIn]);

	const handlePageChange = useCallback(
		(model: GridPaginationModel) => {
			dispatch(
				reloadWorks({
					currentPageFrom1: model.page + 1,
					perPage: model.pageSize,
				})
			);
		},
		[dispatch]
	);
	const handleAddPress = useCallback(() => {
		dispatch(setIsEditing({ isEditing: true }));
	}, [dispatch]);

	return (
		<Box sx={{ width: "100%" }}>
			<Box
				sx={{ display: "flex", justifyContent: "space-between", m: "0.5em" }}>
				<Typography
					variant="h5"
					component="h5">
					{t("Works")}
				</Typography>
				<Stack
					direction="row"
					spacing={2}>
					<Button
						onClick={handleAddPress}
						disabled={!canWrite}
						startIcon={<Add />}
						variant="outlined">
						{t("Add")}
					</Button>
				</Stack>
			</Box>
			<DataGrid
				loading={isLoading}
				rows={workList}
				autoHeight
				checkboxSelection
				editMode="row"
				initialState={{
					pagination: {
						paginationModel: {
							page: currentPageFrom1 - 1,
							pageSize: perPage,
						},
					},
				}}
				rowCount={totalItemsCount}
				onPaginationModelChange={handlePageChange}
				pageSizeOptions={PAGE_SIZE_OPTIONS}
				getRowId={getRowId}
				columns={columns}></DataGrid>
		</Box>
	);
};

export default memo(WorksPage);
