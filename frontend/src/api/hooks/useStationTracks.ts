import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiStationTrack, toApiStationTrack } from "../adapters";
import { client, fetchAllPages, unwrap } from "../client";
import { queryKeys } from "../queryKeys";

import type { StationTrack } from "../../types/entities";

// Tracks belong to a station (station_tracks.stations_id). The list is fetched
// per-station and is meant to be enabled on-demand (e.g. when a row's track
// picker opens) rather than preloaded for every station in a timetable.
export const useStationTracks = (stationId: string, enabled = true) =>
	useQuery({
		queryKey: queryKeys.stationTracks(stationId),
		queryFn: async () => {
			const data = await fetchAllPages((p, limit) =>
				unwrap(
					client.GET("/stations/{stationId}/tracks", {
						params: { path: { stationId }, query: { p, limit } },
					})
				)
			);
			return data.map(fromApiStationTrack);
		},
		enabled: enabled && stationId !== "",
	});

export const useCreateStationTrack = (stationId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (draft: Omit<StationTrack, "id" | "stationId" | "createdAt">) =>
			unwrap(
				client.POST("/stations/{stationId}/tracks", {
					params: { path: { stationId } },
					body: toApiStationTrack(draft),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stationTracks(stationId),
			});
		},
	});
};

export const useUpdateStationTrack = (stationId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (
			vars: Pick<StationTrack, "id"> &
				Omit<StationTrack, "id" | "stationId" | "createdAt">
		) =>
			unwrap(
				client.PUT("/tracks/{stationTrackId}", {
					params: { path: { stationTrackId: vars.id } },
					body: toApiStationTrack({
						name: vars.name,
						description: vars.description,
						runInLimit: vars.runInLimit,
						runOutLimit: vars.runOutLimit,
					}),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stationTracks(stationId),
			});
		},
	});
};

export const useDeleteStationTrack = (stationId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (stationTrackId: string) =>
			unwrap(
				client.DELETE("/tracks/{stationTrackId}", {
					params: { path: { stationTrackId } },
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stationTracks(stationId),
			});
		},
	});
};
