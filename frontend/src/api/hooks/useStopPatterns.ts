import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiStopPattern, toApiStopPattern } from "../adapters";
import { stopPatternApi } from "../instances";
import { queryKeys } from "../queryKeys";

import type { StopPattern } from "../../types/entities";

export const useStopPatterns = (projectId: string) =>
	useQuery({
		queryKey: queryKeys.stopPatterns(projectId),
		queryFn: () =>
			stopPatternApi
				.getStopPatternList({ projectId })
				.then((list) => list.map(fromApiStopPattern)),
		enabled: projectId !== "",
	});

export const useCreateStopPattern = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (draft: Omit<StopPattern, "id" | "projectId" | "createdAt">) =>
			stopPatternApi.createStopPattern({
				projectId,
				stopPattern: toApiStopPattern(draft),
			}),
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
			stopPatternApi.updateStopPattern({
				stopPatternId: vars.id,
				stopPattern: toApiStopPattern({
					lineId: vars.lineId,
					name: vars.name,
					fromProjectStationId: vars.fromProjectStationId,
					toProjectStationId: vars.toProjectStationId,
					direction: vars.direction,
				}),
			}),
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
			stopPatternApi.deleteStopPattern({ stopPatternId: id }),
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
