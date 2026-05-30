import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiTimetableRow, toApiTimetableRow } from "../adapters";
import { client, fetchAllPages, unwrap, unwrapCreated } from "../client";
import { queryKeys } from "../queryKeys";

import type { TimetableRow } from "../../types/entities";
import type { QueryClient } from "@tanstack/react-query";

const timetableRowsQueryOptions = (trainId: string) => ({
	queryKey: queryKeys.timetableRows(trainId),
	queryFn: async () => {
		const data = await fetchAllPages((p, limit) =>
			unwrap(
				client.GET("/trains/{trainId}/timetable_rows", {
					params: { path: { trainId }, query: { p, limit } },
				})
			)
		);
		// The API returns rows ordered by id DESC (reverse insertion). Rows must
		// render in route/insertion order: first/last-row detection and the
		// drive-time cascade (computeRowFormats) both depend on it. IDs are
		// UUID v7 (lexicographically chronological), so sort by id ascending.
		return data
			.slice()
			.sort((a, b) =>
				(a.timetable_rows_id ?? "").localeCompare(b.timetable_rows_id ?? "")
			)
			.map(fromApiTimetableRow);
	},
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
			unwrapCreated(
				client.POST("/trains/{trainId}/timetable_rows", {
					params: { path: { trainId: vars.trainId } },
					body: toApiTimetableRow(vars.draft),
				})
			),
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
			unwrap(
				client.PUT("/timetable_rows/{timetableRowId}", {
					params: { path: { timetableRowId: vars.rowId } },
					body: toApiTimetableRow(vars.draft),
				})
			),
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
			unwrap(
				client.DELETE("/timetable_rows/{timetableRowId}", {
					params: { path: { timetableRowId: vars.rowId } },
				})
			),
		onSuccess: (_data, vars) => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.timetableRows(vars.trainId),
			});
		},
	});
};
