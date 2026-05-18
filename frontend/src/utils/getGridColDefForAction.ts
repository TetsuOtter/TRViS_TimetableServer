import type { GridColDef, GridValidRowModel } from "@mui/x-data-grid";

export const getGridColDefForAction = <R extends GridValidRowModel>(
	field: string,
	renderCell: GridColDef<R>["renderCell"]
): GridColDef<R> => {
	return {
		field,
		headerName: "",
		sortable: false,
		disableColumnMenu: true,
		disableReorder: true,
		disableExport: true,
		width: 40,
		align: "center",
		editable: false,
		type: "actions",
		renderCell,
	};
};
