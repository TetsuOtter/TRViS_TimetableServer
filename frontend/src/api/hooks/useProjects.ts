import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiProject, toApiProject } from "../adapters";
import { projectApi } from "../instances";
import { queryKeys } from "../queryKeys";

import type { Project } from "../../types/entities";

export const useProjects = () =>
	useQuery({
		queryKey: queryKeys.projects(),
		queryFn: () =>
			projectApi.getProjectList({}).then((list) => list.map(fromApiProject)),
	});

export const useCreateProject = () => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (draft: Pick<Project, "name" | "description">) =>
			projectApi.createProject({ project: toApiProject(draft) }),
		onSuccess: () => {
			void queryClient.invalidateQueries({ queryKey: queryKeys.projects() });
		},
	});
};

export const useUpdateProject = () => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (vars: Pick<Project, "id" | "name" | "description">) =>
			projectApi.updateProject({
				projectId: vars.id,
				project: toApiProject({
					name: vars.name,
					description: vars.description,
				}),
			}),
		onSuccess: () => {
			void queryClient.invalidateQueries({ queryKey: queryKeys.projects() });
		},
	});
};

export const useDeleteProject = () => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (id: string) => projectApi.deleteProject({ projectId: id }),
		onSuccess: () => {
			void queryClient.invalidateQueries({ queryKey: queryKeys.projects() });
		},
	});
};
