import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiStopPatternRow, toApiStopPatternRow } from "../adapters";
import { stopPatternRowApi } from "../instances";
import { queryKeys } from "../queryKeys";

import type { StopPatternRow } from "../../types/entities";

export const useStopPatternRows = (stopPatternId: string) =>
	useQuery({
		queryKey: queryKeys.stopPatternRows(stopPatternId),
		queryFn: () =>
			stopPatternRowApi
				.getStopPatternRowList({ stopPatternId })
				.then((list) => list.map(fromApiStopPatternRow)),
		enabled: stopPatternId !== "",
	});

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
			stopPatternRowApi.createStopPatternRow({
				stopPatternId: vars.stopPatternId,
				stopPatternRow: vars.drafts.map(toApiStopPatternRow),
			}),
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
			stopPatternRowApi.deleteStopPatternRow({
				stopPatternRowId: vars.id,
			}),
		onSuccess: (_d, vars) => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stopPatternRows(vars.stopPatternId),
			});
		},
	});
};
