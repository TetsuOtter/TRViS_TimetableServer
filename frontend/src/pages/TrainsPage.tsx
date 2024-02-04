import { memo, useCallback, useEffect, useMemo } from "react";
import { useNavigate } from "react-router-dom";

import { Add, TableView } from "@mui/icons-material";
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
import { useUpdateCurrentShowingWorks } from "../hooks/updateCurrentShowingDataHook";
import DeleteButtonInDataGrid from "../parts/DeleteButtonInDataGrid";
import { useAppDispatch, useAppSelector } from "../redux/hooks";
import { isLoggedInSelector } from "../redux/selectors/authInfoSelector";
import {
	currentPageFrom1Selector,
	editTargetTrainSelector,
	isEditingSelector,
	isLoadingSelector,
	perPageSelector,
	totalItemsCountSelector,
	trainListSelector,
} from "../redux/selectors/trainsSelector";
import { canWriteToCurrentShowingWorkGroupSelector } from "../redux/selectors/workGroupsSelector";
import {
	createTrain,
	deleteTrain,
	reloadTrains,
	setIsEditing,
	updateTrain,
} from "../redux/slices/trainsSlice";
import {
	DESCRIPTION_MAX_LENGTH,
	DESCRIPTION_MIN_LENGTH,
	NAME_MAX_LENGTH,
	NAME_MIN_LENGTH,
	PAGE_SIZE_OPTIONS,
	UUID_NULL,
} from "../utils/Constants";
import { getGridColDefForAction } from "../utils/getGridColDefForAction";
import { getPathToTimetableRowList } from "../utils/getPathString";

import type { EditDataFormSetting } from "../components/EditDataDialog";
import type { Train } from "../oas";
import type { DateToNumberObjectType } from "../utils/DateToNumberType";
import type {
	GridColDef,
	GridPaginationModel,
	GridValueFormatterParams,
} from "@mui/x-data-grid";

const TRAIN_DIRECTION = {
	OUTBOUND: 1,
	INBOUND: -1,
} as const;

const getRowIdOrUndef = (row: DateToNumberObjectType<Train>) => row.trainsId;
const getRowId = (row: DateToNumberObjectType<Train>) =>
	getRowIdOrUndef(row) ?? UUID_NULL;

const useGridColDefList = (): GridColDef[] => {
	const {
		t,
		i18n: { language },
	} = useTranslation();
	const navigate = useNavigate();

	const canWrite = useAppSelector(canWriteToCurrentShowingWorkGroupSelector);

	const showTimetableRowList = useCallback(
		(trainsId?: string) => {
			console.log(trainsId);
			if (trainsId != null) {
				navigate(getPathToTimetableRowList(trainsId));
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
							<IconButton
								onClick={() => showTimetableRowList(getRowId(params.row))}>
								<TableView />
							</IconButton>
						</Tooltip>
					)
			),
			getGridColDefForAction(
				"deleteData",
				(params) =>
					getRowIdOrUndef(params.row) && (
						<DeleteButtonInDataGrid<void, { trainId: string }>
							disabled={!canWrite}
							thunk={deleteTrain}
							thunkArg={{ trainId: getRowId(params.row) }}
						/>
					)
			),
			{
				field: "trainNumber",
				headerName: t("Train Number"),
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
				field: "direction",
				headerName: t("Direction"),
				valueFormatter: (params: GridValueFormatterParams<number>) => {
					const direction = params.value;

					switch (direction) {
						case TRAIN_DIRECTION.OUTBOUND:
							return t("Outbound");
						case TRAIN_DIRECTION.INBOUND:
							return t("Inbound");
						default:
							return direction < 0 ? t("Inbound") : t("Outbound");
					}
				},
				width: 140,
				sortable: false,
			},
			{
				field: "dayCount",
				headerName: t("Day Count"),
				width: 80,
				sortable: false,
			},
			{
				field: "carCount",
				headerName: t("Car Count"),
				width: 80,
				sortable: false,
			},
			{
				field: "isRideOnMoving",
				headerName: t("Ride On Moving"),
				width: 80,
				sortable: false,
				type: "boolean",
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
				field: "trainsId",
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
		[canWrite, language, showTimetableRowList, t]
	);
};

const useEditFormSetting = (): EditDataFormSetting<
	DateToNumberObjectType<Train>
>[] => {
	const { t } = useTranslation();

	return [
		{
			name: "trainNumber",
			label: t("Train Number"),
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
			name: "direction",
			label: t("Direction"),
			type: FieldTypes.SELECT,
			isRequired: true,
			items: {
				[TRAIN_DIRECTION.OUTBOUND]: {
					value: TRAIN_DIRECTION.OUTBOUND,
					label: t("Outbound"),
				},
				[TRAIN_DIRECTION.INBOUND]: {
					value: TRAIN_DIRECTION.INBOUND,
					label: t("Inbound"),
				},
			},
		},
		{
			name: "dayCount",
			label: t("Day Count"),
			type: FieldTypes.NUMBER,
			isRequired: true,
			min: 0,
		},
		{
			name: "carCount",
			label: t("Car Count"),
			type: FieldTypes.NUMBER,
			isRequired: false,
			min: 0,
		},
		{
			name: "maxSpeed",
			label: t("Max Speed"),
			type: FieldTypes.TEXT,
			isRequired: false,
			isMultiline: true,
			rows: 4,
			maxLength: DESCRIPTION_MAX_LENGTH,
		},
		{
			name: "speedType",
			label: t("Speed Type"),
			type: FieldTypes.TEXT,
			isRequired: false,
			isMultiline: true,
			rows: 4,
			maxLength: DESCRIPTION_MAX_LENGTH,
		},
		{
			name: "nominalTractiveCapacity",
			label: t("Nominal Tractive Capacity"),
			type: FieldTypes.TEXT,
			isRequired: false,
			isMultiline: true,
			rows: 4,
			maxLength: DESCRIPTION_MAX_LENGTH,
		},
		{
			name: "destination",
			label: t("Destination"),
			type: FieldTypes.TEXT,
			isRequired: false,
			isMultiline: false,
			maxLength: 8,
		},
		{
			name: "beginRemarks",
			label: t("Begin Remarks"),
			type: FieldTypes.TEXT,
			isRequired: false,
			isMultiline: true,
			rows: 2,
			maxLength: DESCRIPTION_MAX_LENGTH,
		},
		{
			name: "afterRemarks",
			label: t("After Remarks"),
			type: FieldTypes.TEXT,
			isRequired: false,
			isMultiline: true,
			rows: 2,
			maxLength: DESCRIPTION_MAX_LENGTH,
		},
		{
			name: "remarks",
			label: t("Train Remarks"),
			type: FieldTypes.TEXT,
			isRequired: false,
			isMultiline: true,
			rows: 4,
		},
		{
			name: "beforeDeparture",
			label: t("Before Departure"),
			type: FieldTypes.TEXT,
			isRequired: false,
			isMultiline: true,
			rows: 2,
			maxLength: DESCRIPTION_MAX_LENGTH,
		},
		{
			name: "afterArrive",
			label: t("After Arrive"),
			type: FieldTypes.TEXT,
			isRequired: false,
			isMultiline: true,
			rows: 2,
			maxLength: DESCRIPTION_MAX_LENGTH,
		},
		{
			name: "trainInfo",
			label: t("Train Info"),
			type: FieldTypes.TEXT,
			isRequired: false,
			isMultiline: true,
			rows: 2,
			maxLength: DESCRIPTION_MAX_LENGTH,
		},
		{
			name: "isRideOnMoving",
			label: t("Ride On Moving"),
			type: FieldTypes.SWITCH,
			isRequired: false,
		},
	];
};

const TrainsPage = () => {
	useUpdateCurrentShowingWorks();

	const { t } = useTranslation();
	const editFormSetting = useEditFormSetting();

	const dispatch = useAppDispatch();
	const trainList = useAppSelector(trainListSelector);

	const isSignedIn = useAppSelector(isLoggedInSelector);
	const canWrite = useAppSelector(canWriteToCurrentShowingWorkGroupSelector);
	const isLoading = useAppSelector(isLoadingSelector);
	const currentPageFrom1 = useAppSelector(currentPageFrom1Selector);
	const perPage = useAppSelector(perPageSelector);
	const totalItemsCount = useAppSelector(totalItemsCountSelector);
	const columns = useGridColDefList();

	useEffect(() => {
		// サインイン状態が変化すると権限も変化する可能性があるため、再取得する
		dispatch(reloadTrains());
	}, [dispatch, isSignedIn]);

	const handlePageChange = useCallback(
		(model: GridPaginationModel) => {
			dispatch(
				reloadTrains({
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
					{t("Trains")}
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
				rows={trainList}
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
			<EditDataDialog<DateToNumberObjectType<Train>>
				createData={createTrain}
				updateData={updateTrain}
				formSettings={editFormSetting}
				createModeTitle={t("Add New Train")}
				editModeTitle={t("Edit Train")}
				getId={(data) => data.trainsId}
				initialStateSelector={editTargetTrainSelector}
				isEditingSelector={isEditingSelector}
				setIsEditing={setIsEditing}
			/>
		</Box>
	);
};

export default memo(TrainsPage);
