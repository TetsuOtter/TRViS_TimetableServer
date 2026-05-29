import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiStopPatternRow, toApiStopPatternRow } from "../adapters";
import { client, fetchAllPages, unwrap } from "../client";
import { queryKeys } from "../queryKeys";

import type { StopPatternRow } from "../../types/entities";
import type { QueryClient } from "@tanstack/react-query";

const stopPatternRowsQueryOptions = (stopPatternId: string) => ({
	queryKey: queryKeys.stopPatternRows(stopPatternId),
	queryFn: async () => {
		const data = await fetchAllPages((p, limit) =>
			unwrap(
				client.GET("/stop_patterns/{stopPatternId}/rows", {
					params: { path: { stopPatternId }, query: { p, limit } },
				})
			)
		);
		return data.map(fromApiStopPatternRow);
	},
});

export const useStopPatternRows = (stopPatternId: string) =>
	useQuery({
		...stopPatternRowsQueryOptions(stopPatternId),
		enabled: stopPatternId !== "",
	});

export const fetchStopPatternRows = (
	queryClient: QueryClient,
	stopPatternId: string
) => queryClient.fetchQuery(stopPatternRowsQueryOptions(stopPatternId));

export const useCreateStopPatternRows = () => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (vars: {
			stopPatternId: string;
			drafts: Omit<
				StopPatternRow,
				"id" | "projectId" | "stopPatternId" | "createdAt"
			>[];
		}) =>
			unwrap(
				client.POST("/stop_patterns/{stopPatternId}/rows", {
					params: { path: { stopPatternId: vars.stopPatternId } },
					body: vars.drafts.map(toApiStopPatternRow),
				})
			),
		onSuccess: (_d, vars) => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stopPatternRows(vars.stopPatternId),
			});
		},
	});
};

export const useDeleteStopPatternRow = () => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (vars: { stopPatternId: string; id: string }) =>
			unwrap(
				client.DELETE("/stop_pattern_rows/{stopPatternRowId}", {
					params: { path: { stopPatternRowId: vars.id } },
				})
			),
		onSuccess: (_d, vars) => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stopPatternRows(vars.stopPatternId),
			});
		},
	});
};
