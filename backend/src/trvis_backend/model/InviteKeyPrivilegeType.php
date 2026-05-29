<?php

namespace dev_t0r\trvis_backend\model;

/**
 * Faithful 1:1 port of the legacy int-backed privilege enum.
 *
 * Behaviour preserved exactly (fromString/fromInt/hasPrivilege/jsonSerialize);
 * the only change vs legacy is explicit `public` on the static factories
 * (PSR1 method-visibility — satisfied without widening the phpcs excludes).
 * No `declare(strict_types=1)` on purpose: the ported repos/service rely on
 * legacy coercion semantics (same precedent as Utils/Constants/RetValueOrError).
 */
enum InviteKeyPrivilegeType: int implements \JsonSerializable
{
	case none = 0;
	case read = 1;
	case write = 2;
	case admin = 3;

	public static function fromString(string $str): InviteKeyPrivilegeType
	{
		switch (strtolower($str)) {
			case 'none':
				return InviteKeyPrivilegeType::none;
			case 'read':
				return InviteKeyPrivilegeType::read;
			case 'write':
				return InviteKeyPrivilegeType::write;
			case 'admin':
				return InviteKeyPrivilegeType::admin;
			default:
				throw new \Exception("Invalid privilege type: $str");
		}
	}

	public static function fromInt(int $int): InviteKeyPrivilegeType
	{
		switch ($int) {
			case 0:
				return InviteKeyPrivilegeType::none;
			case 1:
				return InviteKeyPrivilegeType::read;
			case 2:
				return InviteKeyPrivilegeType::write;
			case 3:
				return InviteKeyPrivilegeType::admin;
			default:
				throw new \Exception("Invalid privilege type: $int");
		}
	}

	public function hasPrivilege(InviteKeyPrivilegeType $privilegeType): bool
	{
		return $privilegeType->value <= $this->value;
	}

	public function jsonSerialize(): string
	{
		return $this->name;
	}
}
