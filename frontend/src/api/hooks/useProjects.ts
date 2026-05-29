import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiProject, toApiProject } from "../adapters";
import { client, fetchAllPages, unwrap } from "../client";
import { queryKeys } from "../queryKeys";

import type { Project } from "../../types/entities";

// First hook migrated to openapi-typescript + openapi-fetch (P4.5 pilot).

export const useProjects = () =>
	useQuery({
		queryKey: queryKeys.projects(),
		queryFn: async () => {
			const data = await fetchAllPages((p, limit) =>
				unwrap(client.GET("/projects", { params: { query: { p, limit } } }))
			);
			return data.map(fromApiProject);
		},
	});

export const useCreateProject = () => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (draft: Pick<Project, "name" | "description">) =>
			unwrap(client.POST("/projects", { body: toApiProject(draft) })),
		onSuccess: () => {
			void queryClient.invalidateQueries({ queryKey: queryKeys.projects() });
		},
	});
};

export const useUpdateProject = () => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (vars: Pick<Project, "id" | "name" | "description">) =>
			unwrap(
				client.PUT("/projects/{projectId}", {
					params: { path: { projectId: vars.id } },
					body: toApiProject({
						name: vars.name,
						description: vars.description,
					}),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({ queryKey: queryKeys.projects() });
		},
	});
};

export const useDeleteProject = () => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (id: string) =>
			unwrap(
				client.DELETE("/projects/{projectId}", {
					params: { path: { projectId: id } },
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({ queryKey: queryKeys.projects() });
		},
	});
};
