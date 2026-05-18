import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiWork, toApiWork } from "../adapters";
import { workApi } from "../instances";
import { queryKeys } from "../queryKeys";

import type { Work } from "../../types/entities";

export const useWorks = (workGroupId: string) =>
	useQuery({
		queryKey: queryKeys.works(workGroupId),
		queryFn: () =>
			workApi
				.getWorkList({ workGroupId })
				.then((list) => list.map(fromApiWork)),
		enabled: workGroupId !== "",
	});

export const useCreateWork = (workGroupId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (draft: Omit<Work, "id" | "workGroupId" | "createdAt">) =>
			workApi.createWork({ workGroupId, work: toApiWork(draft) }),
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
			workApi.updateWork({
				workId: vars.id,
				work: toApiWork({
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
			}),
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
		mutationFn: (id: string) => workApi.deleteWork({ workId: id }),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.works(workGroupId),
			});
		},
	});
};
