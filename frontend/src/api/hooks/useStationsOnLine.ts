import {
	useMutation,
	useQueries,
	useQuery,
	useQueryClient,
} from "@tanstack/react-query";

import { fromApiStationOnLine, toApiStationOnLine } from "../adapters";
import { client, fetchAllPages, unwrap, unwrapCreated } from "../client";
import { queryKeys } from "../queryKeys";

import type { StationOnLine } from "../../types/entities";

export const useStationsOnLine = (lineId: string) =>
	useQuery({
		queryKey: queryKeys.stationsOnLine(lineId),
		queryFn: async () => {
			const data = await fetchAllPages((p, limit) =>
				unwrap(
					client.GET("/lines/{lineId}/stations_on_line", {
						params: { path: { lineId }, query: { p, limit } },
					})
				)
			);
			return data.map(fromApiStationOnLine);
		},
		enabled: lineId !== "",
	});

/** Fetch stationsOnLine for multiple lines in parallel and return the combined
 *  flat array. Uses the same per-line queryKey as useStationsOnLine so results
 *  are shared with the cache — no duplicate requests. Returns whatever is
 *  already loaded; individual lines fill in as their queries complete. */
export const useAllStationsOnLine = (lineIds: string[]): StationOnLine[] => {
	const results = useQueries({
		queries: lineIds.map((lineId) => ({
			queryKey: queryKeys.stationsOnLine(lineId),
			queryFn: async () => {
				const data = await fetchAllPages((p, limit) =>
					unwrap(
						client.GET("/lines/{lineId}/stations_on_line", {
							params: { path: { lineId }, query: { p, limit } },
						})
					)
				);
				return data.map(fromApiStationOnLine);
			},
			enabled: lineId !== "",
		})),
	});
	return results.flatMap((r) => r.data ?? []);
};

export const useCreateStationOnLine = (lineId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (
			draft: Omit<StationOnLine, "id" | "projectId" | "createdAt">
		) =>
			unwrapCreated(
				client.POST("/lines/{lineId}/stations_on_line", {
					params: { path: { lineId } },
					body: toApiStationOnLine(draft),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stationsOnLine(lineId),
			});
		},
	});
};

export const useUpdateStationOnLine = (lineId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (
			vars: Pick<StationOnLine, "id"> &
				Omit<StationOnLine, "id" | "projectId" | "createdAt">
		) =>
			unwrap(
				client.PUT("/stations_on_line/{stationOnLineId}", {
					params: { path: { stationOnLineId: vars.id } },
					body: toApiStationOnLine({
						lineId: vars.lineId,
						projectStationId: vars.projectStationId,
						locationM: vars.locationM,
						longitude: vars.longitude,
						latitude: vars.latitude,
						trackHiddenByDefault: vars.trackHiddenByDefault,
					}),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stationsOnLine(lineId),
			});
		},
	});
};

export const useDeleteStationOnLine = (lineId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (stationOnLineId: string) =>
			unwrap(
				client.DELETE("/stations_on_line/{stationOnLineId}", {
					params: { path: { stationOnLineId } },
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stationsOnLine(lineId),
			});
		},
	});
};
