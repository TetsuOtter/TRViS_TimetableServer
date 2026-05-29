import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiWorkGroup, toApiWorkGroup } from "../adapters";
import { client, fetchAllPages, unwrap } from "../client";
import { queryKeys } from "../queryKeys";

import type { WorkGroup } from "../../types/entities";

export const useWorkGroups = (projectId: string) =>
	useQuery({
		queryKey: queryKeys.workGroups(projectId),
		queryFn: async () => {
			const data = await fetchAllPages((p, limit) =>
				unwrap(
					client.GET("/projects/{projectId}/work_groups", {
						params: { path: { projectId }, query: { p, limit } },
					})
				)
			);
			return data.map(fromApiWorkGroup);
		},
		enabled: projectId !== "",
	});

export const useCreateWorkGroup = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (draft: Pick<WorkGroup, "name" | "description">) =>
			unwrap(
				client.POST("/projects/{projectId}/work_groups", {
					params: { path: { projectId } },
					body: toApiWorkGroup(draft),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.workGroups(projectId),
			});
		},
	});
};

export const useUpdateWorkGroup = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (vars: Pick<WorkGroup, "id" | "name" | "description">) =>
			unwrap(
				client.PUT("/work_groups/{workGroupId}", {
					params: { path: { workGroupId: vars.id } },
					body: toApiWorkGroup({
						name: vars.name,
						description: vars.description,
					}),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.workGroups(projectId),
			});
		},
	});
};

export const useDeleteWorkGroup = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (id: string) =>
			unwrap(
				client.DELETE("/work_groups/{workGroupId}", {
					params: { path: { workGroupId: id } },
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.workGroups(projectId),
			});
		},
	});
};
