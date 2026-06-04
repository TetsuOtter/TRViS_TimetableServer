import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { fromApiInviteKey, toApiInviteKey } from "../adapters";
import { client, fetchAllPages, unwrap } from "../client";
import { queryKeys } from "../queryKeys";

import type { InviteKey } from "../../types/entities";

export const useInviteKeys = (workGroupId: string) =>
	useQuery({
		queryKey: queryKeys.inviteKeys(workGroupId),
		queryFn: async () => {
			const data = await fetchAllPages((p, limit) =>
				unwrap(
					client.GET("/work_groups/{workGroupId}/invite_keys", {
						params: {
							path: { workGroupId },
							query: { p, limit, expired: true },
						},
					})
				)
			);
			return data.map(fromApiInviteKey);
		},
		enabled: workGroupId !== "",
	});

export const useCreateInviteKey = (workGroupId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (
			draft: Pick<
				InviteKey,
				"description" | "privilegeType" | "validFrom" | "expiresAt" | "useLimit"
			>
		) =>
			unwrap(
				client.POST("/work_groups/{workGroupId}/invite_keys", {
					params: { path: { workGroupId } },
					body: toApiInviteKey(draft),
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.inviteKeys(workGroupId),
			});
		},
	});
};

export const useDeleteInviteKey = (workGroupId: string) => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (inviteKeyId: string) =>
			unwrap(
				client.DELETE("/invite_keys/{inviteKeyId}", {
					params: { path: { inviteKeyId } },
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({
				queryKey: queryKeys.inviteKeys(workGroupId),
			});
		},
	});
};

export const useUseInviteKey = () => {
	const queryClient = useQueryClient();
	return useMutation({
		mutationFn: (inviteKeyId: string) =>
			unwrap(
				client.POST("/invite_keys/{inviteKeyId}", {
					params: { path: { inviteKeyId } },
				})
			),
		onSuccess: () => {
			void queryClient.invalidateQueries({ queryKey: queryKeys.projects() });
		},
	});
};
