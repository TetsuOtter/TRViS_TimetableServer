import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiLine, toApiLine } from "../adapters";
import { lineApi } from "../instances";
import { queryKeys } from "../queryKeys";

import type { Line } from "../../types/entities";

export const useLines = (projectId: string) =>
	useQuery({
		queryKey: queryKeys.lines(projectId),
		queryFn: () =>
			lineApi.getLineList({ projectId }).then((list) => list.map(fromApiLine)),
		enabled: projectId !== "",
	});

export const useCreateLine = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (draft: Omit<Line, "id" | "projectId" | "createdAt">) =>
			lineApi.createLine({ projectId, line: toApiLine(draft) }),
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
			lineApi.updateLine({
				lineId: vars.id,
				line: toApiLine({
					name: vars.name,
					description: vars.description,
				}),
			}),
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
		mutationFn: (lineId: string) => lineApi.deleteLine({ lineId }),
		onSuccess: (_data, lineId) => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.lines(projectId),
			});
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stationsOnLine(lineId),
			});
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stopPatterns(lineId),
			});
		},
	});
};
