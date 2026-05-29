import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiProjectStation, toApiProjectStation } from "../adapters";
import { client, fetchAllPages, unwrap } from "../client";
import { queryKeys } from "../queryKeys";

import type { ProjectStation } from "../../types/entities";

export const useProjectStations = (projectId: string) =>
	useQuery({
		queryKey: queryKeys.projectStations(projectId),
		queryFn: async () => {
			const data = await fetchAllPages((p, limit) =>
				unwrap(
					client.GET("/projects/{projectId}/stations", {
						params: { path: { projectId }, query: { p, limit } },
					})
				)
			);
			return data.map(fromApiProjectStation);
		},
		enabled: projectId !== "",
	});

export const useCreateProjectStation = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (
			draft: Omit<ProjectStation, "id" | "projectId" | "createdAt">
		) =>
			unwrap(
				client.POST("/projects/{projectId}/stations", {
					params: { path: { projectId } },
					body: toApiProjectStation(draft),
				})
			),
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
			unwrap(
				client.PUT("/stations/{stationId}", {
					params: { path: { stationId: vars.id } },
					body: toApiProjectStation({
						name: vars.name,
						fullName: vars.fullName,
						longitude: vars.longitude,
						latitude: vars.latitude,
						onStationDetectRadiusM: vars.onStationDetectRadiusM,
						alwaysShowHh: vars.alwaysShowHh,
					}),
				})
			),
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
			unwrap(
				client.DELETE("/stations/{stationId}", {
					params: { path: { stationId: projectStationId } },
				})
			),
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
