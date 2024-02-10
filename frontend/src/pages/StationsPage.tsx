import { memo, useCallback, useEffect, useMemo } from "react";
import { useNavigate } from "react-router-dom";

import { Add, Train } from "@mui/icons-material";
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

import { EditDataDialog, FieldTypes } from "../components/EditDataDialog";
import { useUpdateCurrentShowingWorkGroups } from "../hooks/updateCurrentShowingDataHook";
import DeleteButtonInDataGrid from "../parts/DeleteButtonInDataGrid";
import { useAppDispatch, useAppSelector } from "../redux/hooks";
import { isLoggedInSelector } from "../redux/selectors/authInfoSelector";
import {
	currentPageFrom1Selector,
	editTargetStationSelector,
	isEditingSelector,
	isLoadingSelector,
	perPageSelector,
	totalItemsCountSelector,
	stationListSelector,
} from "../redux/selectors/stationsSelector";
import { canWriteToCurrentShowingWorkGroupSelector } from "../redux/selectors/workGroupsSelector";
import {
	createStation,
	deleteStation,
	reloadStations,
	setIsEditing,
	updateStation,
} from "../redux/slices/stationsSlice";
import {
	DESCRIPTION_MAX_LENGTH,
	DESCRIPTION_MIN_LENGTH,
	NAME_MAX_LENGTH,
	NAME_MIN_LENGTH,
	PAGE_SIZE_OPTIONS,
	UUID_NULL,
} from "../utils/Constants";
import { getGridColDefForAction } from "../utils/getGridColDefForAction";
import { getPathToTrainList } from "../utils/getPathString";

import type { EditDataFormSetting } from "../components/EditDataDialog";
import type { Station } from "../oas";
import type { DateToNumberObjectType } from "../utils/DateToNumberType";
import type {
	GridColDef,
	GridPaginationModel,
	GridValueFormatterParams,
} from "@mui/x-data-grid";

const getRowIdOrUndef = (row: DateToNumberObjectType<Station>) =>
	row.stationsId;
const getRowId = (row: DateToNumberObjectType<Station>) =>
	getRowIdOrUndef(row) ?? UUID_NULL;

const useGridColDefList = (): GridColDef[] => {
	const {
		t,
		i18n: { language },
	} = useTranslation();
	const navigate = useNavigate();

	const canWrite = useAppSelector(canWriteToCurrentShowingWorkGroupSelector);

	const showTrainList = useCallback(
		(stationsId?: string) => {
			console.log(stationsId);
			if (stationsId != null) {
				navigate(getPathToTrainList(stationsId));
				console.log("navigate");
			}
		},
		[navigate]
	);
	return useMemo(
		() => [
			getGridColDefForAction(
				"showTrainList",
				(params) =>
					getRowIdOrUndef(params.row) && (
						<Tooltip title={t("Show Train List")}>
							<IconButton onClick={() => showTrainList(getRowId(params.row))}>
								<Train />
							</IconButton>
						</Tooltip>
					)
			),
			getGridColDefForAction(
				"deleteData",
				(params) =>
					getRowIdOrUndef(params.row) && (
						<DeleteButtonInDataGrid<void, { stationId: string }>
							disabled={!canWrite}
							thunk={deleteStation}
							thunkArg={{ stationId: getRowId(params.row) }}
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
				field: "affectDate",
				headerName: t("Affect Date"),
				width: 180,
				sortable: false,
				valueFormatter: (params: GridValueFormatterParams<number>) => {
					const date = new Date(params.value);
					return date.toLocaleDateString(language);
				},
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
				field: "stationsId",
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
		[canWrite, language, showTrainList, t]
	);
};

const useEditFormSetting = (): EditDataFormSetting<
	DateToNumberObjectType<Station>
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
		{
			name: "recordType",
			label: t("Record Type"),
			type: FieldTypes.NUMBER,
			isRequired: false,
		},
		{
			name: "locationKm",
			label: t("Location [km]"),
			type: FieldTypes.NUMBER,
			isRequired: true,
		},
		// {
		// 	name: "locationLonlat",
		// 	label: t("Location [lon,lat]"),
		// 	type: FieldTypes.NUMBER,
		// 	isRequired: false,
		// },
		{
			name: "onStationDetectRadiusM",
			label: t("On Station Detect Radius [m]"),
			type: FieldTypes.NUMBER,
			isRequired: false,
		},
	];
};

const StationsPage = () => {
	useUpdateCurrentShowingWorkGroups();

	const { t } = useTranslation();
	const editFormSetting = useEditFormSetting();

	const dispatch = useAppDispatch();
	const stationList = useAppSelector(stationListSelector);

	const isSignedIn = useAppSelector(isLoggedInSelector);
	const canWrite = useAppSelector(canWriteToCurrentShowingWorkGroupSelector);
	const isLoading = useAppSelector(isLoadingSelector);
	const currentPageFrom1 = useAppSelector(currentPageFrom1Selector);
	const perPage = useAppSelector(perPageSelector);
	const totalItemsCount = useAppSelector(totalItemsCountSelector);
	const columns = useGridColDefList();

	useEffect(() => {
		dispatch(reloadStations());
	}, [dispatch, isSignedIn]);

	const handlePageChange = useCallback(
		(model: GridPaginationModel) => {
			dispatch(
				reloadStations({
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
					{t("Stations")}
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
				rows={stationList}
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
			<EditDataDialog<DateToNumberObjectType<Station>>
				createData={createStation}
				updateData={updateStation}
				formSettings={editFormSetting}
				createModeTitle={t("Add New Station")}
				editModeTitle={t("Edit Station")}
				getId={(data) => data.stationsId}
				initialStateSelector={editTargetStationSelector}
				isEditingSelector={isEditingSelector}
				setIsEditing={setIsEditing}
			/>
		</Box>
	);
};

export default memo(StationsPage);
