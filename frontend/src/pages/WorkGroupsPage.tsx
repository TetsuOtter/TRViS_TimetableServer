import { memo, useCallback, useEffect, useMemo } from "react";
import { useNavigate } from "react-router-dom";

import { Add, Work } from "@mui/icons-material";
import {
	Box,
	Button,
	IconButton,
	Stack,
	Tooltip,
	Typography,
} from "@mui/material";
import { DataGrid } from "@mui/x-data-grid";
import { useTranslation } from "react-i18next";

import { EditWorkGroupDialog } from "../components/EditWorkGroupDialog";
import PrivilegeTypeChip from "../components/PrivilegeTypeChip";
import { WorkGroupPrivilegeTypeEnum } from "../oas";
import DeleteButtonInDataGrid from "../parts/DeleteButtonInDataGrid";
import { useAppDispatch, useAppSelector } from "../redux/hooks";
import { isLoggedInSelector } from "../redux/selectors/authInfoSelector";
import {
	currentPageFrom1Selector,
	isLoadingSelector,
	perPageSelector,
	totalItemsCountSelector,
	workGroupListSelector,
} from "../redux/selectors/workGroupsSelector";
import {
	deleteWorkGroup,
	reloadWorkGroups,
	setIsEditing,
} from "../redux/slices/workGroupsSlice";
import { PAGE_SIZE_OPTIONS, UUID_NULL } from "../utils/Constants";
import { getGridColDefForAction } from "../utils/getGridColDefForAction";
import { getPathToWorkList } from "../utils/getPathString";

import type { WorkGroup } from "../oas";
import type { DateToNumberObjectType } from "../utils/DateToNumberType";
import type {
	GridColDef,
	GridPaginationModel,
	GridValueFormatterParams,
} from "@mui/x-data-grid";

const getRowId = (row: DateToNumberObjectType<WorkGroup>) =>
	row.workGroupsId ?? UUID_NULL;

const useGridColDefList = (): GridColDef<
	DateToNumberObjectType<WorkGroup>
>[] => {
	const {
		t,
		i18n: { language },
	} = useTranslation();
	const navigate = useNavigate();

	const showWorkList = useCallback(
		(workGroupsId?: string) => {
			console.log(workGroupsId);
			if (workGroupsId != null) {
				navigate(getPathToWorkList(workGroupsId));
				console.log("navigate");
			}
		},
		[navigate]
	);

	return useMemo(
		(): GridColDef<DateToNumberObjectType<WorkGroup>>[] => [
			getGridColDefForAction(
				"showWork",
				(params) =>
					params.row.workGroupsId && (
						<Tooltip title={t("Show Work List")}>
							<IconButton onClick={() => showWorkList(params.row.workGroupsId)}>
								<Work />
							</IconButton>
						</Tooltip>
					)
			),
			getGridColDefForAction(
				"deleteData",
				(params) =>
					params.row.workGroupsId && (
						<DeleteButtonInDataGrid<void, { workGroupId: string }>
							disabled={
								params.row.privilegeType !== WorkGroupPrivilegeTypeEnum.Admin
							}
							thunk={deleteWorkGroup}
							thunkArg={{ workGroupId: params.row.workGroupsId }}
						/>
					)
			),
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
				field: "privilegeType",
				headerName: t("Role"),
				renderCell: (params) => (
					<PrivilegeTypeChip
						privilegeType={params.value as WorkGroup["privilegeType"]}
					/>
				),
				width: 120,
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
				field: "workGroupsId",
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

const WorkGroupsPage = () => {
	const { t } = useTranslation();

	const dispatch = useAppDispatch();
	const workGroupList = useAppSelector(workGroupListSelector);

	const isSignedIn = useAppSelector(isLoggedInSelector);
	const isLoading = useAppSelector(isLoadingSelector);
	const currentPageFrom1 = useAppSelector(currentPageFrom1Selector);
	const perPage = useAppSelector(perPageSelector);
	const totalItemsCount = useAppSelector(totalItemsCountSelector);
	const columns = useGridColDefList();

	useEffect(() => {
		dispatch(reloadWorkGroups());
	}, [dispatch, isSignedIn]);

	const handlePageChange = useCallback(
		(model: GridPaginationModel) => {
			dispatch(
				reloadWorkGroups({
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
					{t("Work Groups")}
				</Typography>
				<Stack
					direction="row"
					spacing={2}>
					<Button
						onClick={handleAddPress}
						disabled={!isSignedIn}
						startIcon={<Add />}
						variant="outlined">
						{t("Add")}
					</Button>
				</Stack>
			</Box>
			<DataGrid
				loading={isLoading}
				rows={workGroupList}
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
			<EditWorkGroupDialog />
		</Box>
	);
};

export default memo(WorkGroupsPage);
