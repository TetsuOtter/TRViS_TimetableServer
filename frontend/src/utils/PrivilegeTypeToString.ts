import { WorkGroupPrivilegeTypeEnum } from "trvis-api";

import type { TFunction } from "i18next";
import type { WorkGroupsPrivilegePrivilegeTypeEnum } from "trvis-api";

export const privilegeTypeToString = (
	privilegeType:
		| undefined
		| WorkGroupPrivilegeTypeEnum
		| WorkGroupsPrivilegePrivilegeTypeEnum,
	t: TFunction<"translation", undefined>
) => {
	switch (privilegeType) {
		case undefined:
			return t("PrivilegeType.Unknown");
		case WorkGroupPrivilegeTypeEnum.Admin:
			return t("PrivilegeType.Admin");
		case WorkGroupPrivilegeTypeEnum.Write:
			return t("PrivilegeType.Write");
		case WorkGroupPrivilegeTypeEnum.Read:
			return t("PrivilegeType.Read");
	}
};
