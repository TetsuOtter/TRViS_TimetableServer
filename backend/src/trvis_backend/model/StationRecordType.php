<?php

namespace dev_t0r\trvis_backend\model;

/**
 * Faithful 1:1 port of the legacy int-backed station-record-type enum.
 *
 * Behaviour preserved exactly (fromString/fromOrNull/jsonSerialize). Same
 * code-first style as InviteKeyPrivilegeType: no `declare(strict_types=1)`
 * on purpose (ported repos/service rely on legacy coercion semantics);
 * explicit `public` on the static factories (PSR1 method-visibility).
 */
enum StationRecordType: int implements \JsonSerializable
{
	case normal = 0;
	case normal_no_ett = 1;
	case info = 2;
	case info_ex = 3;

	public static function fromString(string $string): self
	{
		switch (strtolower($string)) {
			case 'normal':
				return StationRecordType::normal;

			case 'normal_no_ett':
				return StationRecordType::normal_no_ett;

			case 'info':
				return StationRecordType::info;

			case 'info_ex':
				return StationRecordType::info_ex;

			default:
				throw new \Exception("Unknown StationRecordType: {$string}");
		}
	}

	public static function fromOrNull(int|string|null $value): ?self
	{
		if (is_null($value)) {
			return null;
		}

		if (is_string($value)) {
			return StationRecordType::fromString($value);
		} else {
			return self::from($value);
		}
	}

	public function jsonSerialize(): string
	{
		return $this->name;
	}
}
