import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiTimetableRow, toApiTimetableRow } from "../adapters";
import { timetableRowApi } from "../instances";
import { queryKeys } from "../queryKeys";

import type { TimetableRow } from "../../types/entities";
import type { QueryClient } from "@tanstack/react-query";

const timetableRowsQueryOptions = (trainId: string) => ({
	queryKey: queryKeys.timetableRows(trainId),
	queryFn: () =>
		timetableRowApi
			.getTimetableRowList({ trainId })
			.then((list) => list.map(fromApiTimetableRow)),
});

export const useTimetableRows = (trainId: string) =>
	useQuery({
		...timetableRowsQueryOptions(trainId),
		enabled: trainId !== "",
	});

export const fetchTimetableRows = (queryClient: QueryClient, trainId: string) =>
	queryClient.fetchQuery(timetableRowsQueryOptions(trainId));

export const useCreateTimetableRow = () => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (vars: {
			trainId: string;
			draft: Omit<TimetableRow, "id" | "trainId" | "createdAt" | "updatedAt">;
		}) =>
			timetableRowApi.createTimetableRow({
				trainId: vars.trainId,
				timetableRow: toApiTimetableRow(vars.draft),
			}),
		onSuccess: (_data, vars) => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.timetableRows(vars.trainId),
			});
		},
	});
};

export const useUpdateTimetableRow = () => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (vars: {
			trainId: string;
			rowId: string;
			draft: Omit<TimetableRow, "id" | "trainId" | "createdAt" | "updatedAt">;
		}) =>
			timetableRowApi.updateTimetableRow({
				timetableRowId: vars.rowId,
				timetableRow: toApiTimetableRow(vars.draft),
			}),
		onSuccess: (_data, vars) => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.timetableRows(vars.trainId),
			});
		},
	});
};

export const useDeleteTimetableRow = () => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (vars: { trainId: string; rowId: string }) =>
			timetableRowApi.deleteTimetableRow({ timetableRowId: vars.rowId }),
		onSuccess: (_data, vars) => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.timetableRows(vars.trainId),
			});
		},
	});
};
