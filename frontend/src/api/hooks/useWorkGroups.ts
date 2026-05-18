import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiWorkGroup, toApiWorkGroup } from "../adapters";
import { workGroupApi } from "../instances";
import { queryKeys } from "../queryKeys";

import type { WorkGroup } from "../../types/entities";

export const useWorkGroups = (projectId: string) =>
	useQuery({
		queryKey: queryKeys.workGroups(projectId),
		queryFn: () =>
			workGroupApi
				.getWorkGroupListByProject({ projectId })
				.then((list) => list.map(fromApiWorkGroup)),
		enabled: projectId !== "",
	});

export const useCreateWorkGroup = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (draft: Pick<WorkGroup, "name" | "description">) =>
			workGroupApi.createWorkGroupInProject({
				projectId,
				workGroup: toApiWorkGroup(draft),
			}),
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
			workGroupApi.updateWorkGroup({
				workGroupId: vars.id,
				workGroup: toApiWorkGroup({
					name: vars.name,
					description: vars.description,
				}),
			}),
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
			workGroupApi.deleteWorkGroup({ workGroupId: id }),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.workGroups(projectId),
			});
		},
	});
};
