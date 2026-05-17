import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiProjectStation, toApiProjectStation } from "../adapters";
import { projectStationApi } from "../instances";
import { queryKeys } from "../queryKeys";

import type { ProjectStation } from "../../types/entities";

export const useProjectStations = (projectId: string) =>
	useQuery({
		queryKey: queryKeys.projectStations(projectId),
		queryFn: () =>
			projectStationApi
				.getProjectStationList({ projectId })
				.then((list) => list.map(fromApiProjectStation)),
		enabled: projectId !== "",
	});

export const useCreateProjectStation = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (
			draft: Omit<ProjectStation, "id" | "projectId" | "createdAt">
		) =>
			projectStationApi.createProjectStation({
				projectId,
				projectStation: toApiProjectStation(draft),
			}),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.projectStations(projectId),
			});
		},
	});
};

export const useUpdateProjectStation = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (
			vars: Pick<ProjectStation, "id"> &
				Omit<ProjectStation, "id" | "projectId" | "createdAt">
		) =>
			projectStationApi.updateProjectStation({
				projectStationId: vars.id,
				projectStation: toApiProjectStation({
					name: vars.name,
					fullName: vars.fullName,
					longitude: vars.longitude,
					latitude: vars.latitude,
					onStationDetectRadiusM: vars.onStationDetectRadiusM,
					alwaysShowHh: vars.alwaysShowHh,
				}),
			}),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.projectStations(projectId),
			});
		},
	});
};

export const useDeleteProjectStation = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (projectStationId: string) =>
			projectStationApi.deleteProjectStation({ projectStationId }),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.projectStations(projectId),
			});
			void queryClient.invalidateQueries({
				queryKey: ["lines"],
			});
		},
	});
};
