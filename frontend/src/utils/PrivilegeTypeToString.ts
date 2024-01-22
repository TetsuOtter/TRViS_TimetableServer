import { WorkGroupPrivilegeTypeEnum } from "../oas";

import type { WorkGroupsPrivilegePrivilegeTypeEnum } from "../oas";
import type { TFunction } from "i18next";

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
