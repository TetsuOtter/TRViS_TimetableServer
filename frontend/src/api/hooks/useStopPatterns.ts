import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiStopPattern, toApiStopPattern } from "../adapters";
import { client, fetchAllPages, unwrap, unwrapCreated } from "../client";
import { queryKeys } from "../queryKeys";

import type { StopPattern } from "../../types/entities";

export const useStopPatterns = (projectId: string) =>
	useQuery({
		queryKey: queryKeys.stopPatterns(projectId),
		queryFn: async () => {
			const data = await fetchAllPages((p, limit) =>
				unwrap(
					client.GET("/projects/{projectId}/stop_patterns", {
						params: { path: { projectId }, query: { p, limit } },
					})
				)
			);
			return data.map(fromApiStopPattern);
		},
		enabled: projectId !== "",
	});

export const useCreateStopPattern = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (draft: Omit<StopPattern, "id" | "projectId" | "createdAt">) =>
			unwrapCreated(
				client.POST("/projects/{projectId}/stop_patterns", {
					params: { path: { projectId } },
					body: toApiStopPattern(draft),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stopPatterns(projectId),
			});
		},
	});
};

export const useUpdateStopPattern = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (
			vars: Pick<StopPattern, "id"> &
				Omit<StopPattern, "id" | "projectId" | "createdAt">
		) =>
			unwrap(
				client.PUT("/stop_patterns/{stopPatternId}", {
					params: { path: { stopPatternId: vars.id } },
					body: toApiStopPattern({
						lineId: vars.lineId,
						name: vars.name,
						fromProjectStationId: vars.fromProjectStationId,
						toProjectStationId: vars.toProjectStationId,
						direction: vars.direction,
					}),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stopPatterns(projectId),
			});
		},
	});
};

export const useDeleteStopPattern = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (id: string) =>
			unwrap(
				client.DELETE("/stop_patterns/{stopPatternId}", {
					params: { path: { stopPatternId: id } },
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stopPatterns(projectId),
			});
			void queryClient.invalidateQueries({
				queryKey: ["stopPatterns"],
			});
		},
	});
};
