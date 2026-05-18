import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiTrain, toApiTrain } from "../adapters";
import { trainApi } from "../instances";
import { queryKeys } from "../queryKeys";

import type { Train } from "../../types/entities";

export const useTrains = (workId: string) =>
	useQuery({
		queryKey: queryKeys.trains(workId),
		queryFn: () =>
			trainApi.getTrainList({ workId }).then((list) => list.map(fromApiTrain)),
		enabled: workId !== "",
	});

export const useCreateTrain = (workId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (draft: Omit<Train, "id" | "workId" | "createdAt">) =>
			trainApi.createTrain({ workId, train: toApiTrain(draft) }),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.trains(workId),
			});
		},
	});
};

export const useUpdateTrain = (workId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (
			vars: Pick<Train, "id"> & Omit<Train, "id" | "workId" | "createdAt">
		) =>
			trainApi.updateTrain({
				trainId: vars.id,
				train: toApiTrain({
					description: vars.description,
					trainNumber: vars.trainNumber,
					direction: vars.direction,
					dayCount: vars.dayCount,
					maxSpeed: vars.maxSpeed,
					speedType: vars.speedType,
					nominalTractiveCapacity: vars.nominalTractiveCapacity,
					carCount: vars.carCount,
					destination: vars.destination,
					beginRemarks: vars.beginRemarks,
					afterRemarks: vars.afterRemarks,
					remarks: vars.remarks,
					beforeDeparture: vars.beforeDeparture,
					afterArrive: vars.afterArrive,
					trainInfo: vars.trainInfo,
					isRideOnMoving: vars.isRideOnMoving,
				}),
			}),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.trains(workId),
			});
		},
	});
};

export const useDeleteTrain = (workId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (id: string) => trainApi.deleteTrain({ trainId: id }),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.trains(workId),
			});
		},
	});
};
