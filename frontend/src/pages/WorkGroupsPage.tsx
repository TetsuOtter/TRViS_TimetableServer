import { memo, useCallback, useEffect, useMemo } from "react";
import { useNavigate } from "react-router-dom";

import { Add, Edit, Store, Work } from "@mui/icons-material";
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
import { WorkGroupPrivilegeTypeEnum } from "trvis-api";

import { EditDataDialog } from "../components/EditDataDialog";
import { FieldTypes } from "../components/FormParts/FieldTypes";
import PrivilegeTypeChip from "../components/PrivilegeTypeChip";
import DeleteButtonInDataGrid from "../parts/DeleteButtonInDataGrid";
import { useAppDispatch, useAppSelector } from "../redux/hooks";
import { isLoggedInSelector } from "../redux/selectors/authInfoSelector";
import {
	currentPageFrom1Selector,
	editTargetWorkGroupSelector,
	isEditingSelector,
	isLoadingSelector,
	perPageSelector,
	totalItemsCountSelector,
	workGroupListSelector,
} from "../redux/selectors/workGroupsSelector";
import {
	createWorkGroup,
	deleteWorkGroup,
	reloadWorkGroups,
	setIsEditing,
	updateWorkGroup,
} from "../redux/slices/workGroupsSlice";
import {
	DESCRIPTION_MAX_LENGTH,
	DESCRIPTION_MIN_LENGTH,
	NAME_MAX_LENGTH,
	NAME_MIN_LENGTH,
	PAGE_SIZE_OPTIONS,
	UUID_NULL,
} from "../utils/Constants";
import { getGridColDefForAction } from "../utils/getGridColDefForAction";
import {
	getPathToStationList,
	getPathToWorkList,
} from "../utils/getPathString";

import type { EditDataFormSetting } from "../components/FormParts/FieldTypes";
import type { DateToNumberObjectType } from "../utils/DateToNumberType";
import type {
	GridColDef,
	GridPaginationModel,
	GridValueFormatterParams,
} from "@mui/x-data-grid";
import type { WorkGroup } from "trvis-api";

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
	const dispatch = useAppDispatch();

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
	const showStationList = useCallback(
		(workGroupsId?: string) => {
			console.log(workGroupsId);
			if (workGroupsId != null) {
				navigate(getPathToStationList(workGroupsId));
				console.log("navigate");
			}
		},
		[navigate]
	);

	const showEditDataDialog = useCallback(
		(workGroupsId: string | undefined) => {
			if (workGroupsId == null) {
				return;
			}

			dispatch(setIsEditing({ isEditing: true, targetId: workGroupsId }));
		},
		[dispatch]
	);

	return useMemo(
		(): GridColDef<DateToNumberObjectType<WorkGroup>>[] => [
			getGridColDefForAction("showWork", (params) =>
				params.row.workGroupsId == null ? undefined : (
					<Tooltip title={t("Show Work List")}>
						<IconButton onClick={() => showWorkList(params.row.workGroupsId)}>
							<Work />
						</IconButton>
					</Tooltip>
				)
			),
			getGridColDefForAction("showStation", (params) =>
				params.row.workGroupsId == null ? undefined : (
					<Tooltip title={t("Show Station List")}>
						<IconButton
							onClick={() => showStationList(params.row.workGroupsId)}>
							<Store />
						</IconButton>
					</Tooltip>
				)
			),
			getGridColDefForAction("editData", (params) =>
				params.row.workGroupsId == null ? undefined : (
					<Tooltip title={t("Edit Data")}>
						<span>
							<IconButton
								disabled={
									params.row.privilegeType !==
										WorkGroupPrivilegeTypeEnum.Admin &&
									params.row.privilegeType !== WorkGroupPrivilegeTypeEnum.Write
								}
								onClick={() => showEditDataDialog(params.row.workGroupsId)}>
								<Edit />
							</IconButton>
						</span>
					</Tooltip>
				)
			),
			getGridColDefForAction("deleteData", (params) =>
				params.row.workGroupsId == null ? undefined : (
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
		[language, showEditDataDialog, showWorkList, t]
	);
};

const useEditFormSetting = (): EditDataFormSetting<
	DateToNumberObjectType<WorkGroup>
>[] => {
	const { t } = useTranslation();

	return [
		{
			name: "name",
			label: t("Name"),
			type: FieldTypes.TEXT,
			isRequired: true,
			minLength: NAME_MIN_LENGTH,
			maxLength: NAME_MAX_LENGTH,
		},
		{
			name: "description",
			label: t("Description"),
			type: FieldTypes.TEXT,
			isRequired: true,
			isMultiline: true,
			rows: 4,
			minLength: DESCRIPTION_MIN_LENGTH,
			maxLength: DESCRIPTION_MAX_LENGTH,
		},
	];
};

const WorkGroupsPage = () => {
	const { t } = useTranslation();
	const editFormSetting = useEditFormSetting();

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
				columns={columns}
			/>
			<EditDataDialog<DateToNumberObjectType<WorkGroup>>
				createData={createWorkGroup}
				updateData={updateWorkGroup}
				formSettings={editFormSetting}
				createModeTitle={t("Add New Work Group")}
				editModeTitle={t("Edit Work Group")}
				getId={(data) => data.workGroupsId}
				initialStateSelector={editTargetWorkGroupSelector}
				isEditingSelector={isEditingSelector}
				setIsEditing={setIsEditing}
			/>
		</Box>
	);
};

export default memo(WorkGroupsPage);
