import { useQuery } from "@tanstack/react-query";

import { fromApiTimetableRow } from "../adapters";
import { timetableRowApi } from "../instances";
import { queryKeys } from "../queryKeys";

export const useTimetableRows = (trainId: string) =>
	useQuery({
		queryKey: queryKeys.timetableRows(trainId),
		queryFn: () =>
			timetableRowApi
				.getTimetableRowList({ trainId })
				.then((list) => list.map(fromApiTimetableRow)),
		enabled: trainId !== "",
	});
