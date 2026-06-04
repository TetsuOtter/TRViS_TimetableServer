import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiColor, toApiColor } from "../adapters";
import { client, fetchAllPages, unwrap } from "../client";
import { queryKeys } from "../queryKeys";

import type { Color } from "../../types/entities";

export const useColors = (projectId: string) =>
	useQuery({
		queryKey: queryKeys.colors(projectId),
		queryFn: async () => {
			const data = await fetchAllPages((p, limit) =>
				unwrap(
					client.GET("/projects/{projectId}/colors", {
						params: { path: { projectId }, query: { p, limit } },
					})
				)
			);
			return data.map(fromApiColor);
		},
		enabled: projectId !== "",
	});

export const useCreateColor = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (draft: Omit<Color, "id" | "projectId" | "createdAt">) =>
			unwrap(
				client.POST("/projects/{projectId}/colors", {
					params: { path: { projectId } },
					body: toApiColor(draft),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.colors(projectId),
			});
		},
	});
};

export const useUpdateColor = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (
			vars: Pick<Color, "id"> & Omit<Color, "id" | "projectId" | "createdAt">
		) =>
			unwrap(
				client.PUT("/colors/{colorId}", {
					params: { path: { colorId: vars.id } },
					body: toApiColor({
						name: vars.name,
						description: vars.description,
						red: vars.red,
						green: vars.green,
						blue: vars.blue,
					}),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.colors(projectId),
			});
		},
	});
};

export const useDeleteColor = (projectId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (colorId: string) =>
			unwrap(
				client.DELETE("/colors/{colorId}", {
					params: { path: { colorId } },
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.colors(projectId),
			});
		},
	});
};
