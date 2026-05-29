import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiLine, toApiLine } from "../adapters";
import { client, fetchAllPages, unwrap } from "../client";
import { queryKeys } from "../queryKeys";

import type { Line } from "../../types/entities";

export const useLines = (projectId: string) =>
	useQuery({
		queryKey: queryKeys.lines(projectId),
		queryFn: async () => {
			const data = await fetchAllPages((p, limit) =>
				unwrap(
					client.GET("/projects/{projectId}/lines", {
						params: { path: { projectId }, query: { p, limit } },
					})
				)
			);
			return data.map(fromApiLine);
		},
		enabled: projectId !== "",
	});

export const useCreateLine = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (draft: Omit<Line, "id" | "projectId" | "createdAt">) =>
			unwrap(
				client.POST("/projects/{projectId}/lines", {
					params: { path: { projectId } },
					body: toApiLine(draft),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.lines(projectId),
			});
		},
	});
};

export const useUpdateLine = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (
			vars: Pick<Line, "id"> & Omit<Line, "id" | "projectId" | "createdAt">
		) =>
			unwrap(
				client.PUT("/lines/{lineId}", {
					params: { path: { lineId: vars.id } },
					body: toApiLine({
						name: vars.name,
						description: vars.description,
					}),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.lines(projectId),
			});
		},
	});
};

export const useDeleteLine = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (lineId: string) =>
			unwrap(
				client.DELETE("/lines/{lineId}", {
					params: { path: { lineId } },
				})
			),
		onSuccess: (_data, lineId) => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.lines(projectId),
			});
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stationsOnLine(lineId),
			});
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stopPatterns(projectId),
			});
		},
	});
};
