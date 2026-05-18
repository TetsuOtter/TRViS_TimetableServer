import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiStationOnLine, toApiStationOnLine } from "../adapters";
import { stationOnLineApi } from "../instances";
import { queryKeys } from "../queryKeys";

import type { StationOnLine } from "../../types/entities";

export const useStationsOnLine = (lineId: string) =>
	useQuery({
		queryKey: queryKeys.stationsOnLine(lineId),
		queryFn: () =>
			stationOnLineApi
				.getStationOnLineList({ lineId })
				.then((list) => list.map(fromApiStationOnLine)),
		enabled: lineId !== "",
	});

export const useCreateStationOnLine = (lineId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (
			draft: Omit<StationOnLine, "id" | "projectId" | "createdAt">
		) =>
			stationOnLineApi.createStationOnLine({
				lineId,
				stationOnLine: toApiStationOnLine(draft),
			}),
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
			stationOnLineApi.updateStationOnLine({
				stationOnLineId: vars.id,
				stationOnLine: toApiStationOnLine({
					lineId: vars.lineId,
					projectStationId: vars.projectStationId,
					locationM: vars.locationM,
					longitude: vars.longitude,
					latitude: vars.latitude,
					trackHiddenByDefault: vars.trackHiddenByDefault,
				}),
			}),
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
			stationOnLineApi.deleteStationOnLine({ stationOnLineId }),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.stationsOnLine(lineId),
			});
		},
	});
};
