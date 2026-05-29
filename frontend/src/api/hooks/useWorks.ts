import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiWork, toApiWork } from "../adapters";
import { client, fetchAllPages, unwrap, unwrapCreated } from "../client";
import { queryKeys } from "../queryKeys";

import type { Work } from "../../types/entities";

export const useWorks = (workGroupId: string) =>
	useQuery({
		queryKey: queryKeys.works(workGroupId),
		queryFn: async () => {
			const data = await fetchAllPages((p, limit) =>
				unwrap(
					client.GET("/work_groups/{workGroupId}/works", {
						params: { path: { workGroupId }, query: { p, limit } },
					})
				)
			);
			return data.map(fromApiWork);
		},
		enabled: workGroupId !== "",
	});

export const useCreateWork = (workGroupId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (draft: Omit<Work, "id" | "workGroupId" | "createdAt">) =>
			unwrapCreated(
				client.POST("/work_groups/{workGroupId}/works", {
					params: { path: { workGroupId } },
					body: toApiWork(draft),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.works(workGroupId),
			});
		},
	});
};

export const useUpdateWork = (workGroupId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (
			vars: Pick<Work, "id"> & Omit<Work, "id" | "workGroupId" | "createdAt">
		) =>
			unwrap(
				client.PUT("/works/{workId}", {
					params: { path: { workId: vars.id } },
					body: toApiWork({
						name: vars.name,
						description: vars.description,
						affectDate: vars.affectDate,
						affixContentType: vars.affixContentType,
						affixContent: vars.affixContent,
						remarks: vars.remarks,
						hasETrainTimetable: vars.hasETrainTimetable,
						eTrainTimetableContentType: vars.eTrainTimetableContentType,
						eTrainTimetableContent: vars.eTrainTimetableContent,
					}),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.works(workGroupId),
			});
		},
	});
};

export const useDeleteWork = (workGroupId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (id: string) =>
			unwrap(
				client.DELETE("/works/{workId}", {
					params: { path: { workId: id } },
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.works(workGroupId),
			});
		},
	});
};
