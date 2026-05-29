<?php

namespace dev_t0r\trvis_backend\model;

/**
 * Faithful 1:1 port of the legacy int-backed content-type enum.
 *
 * Behaviour preserved exactly (fromString/fromOrNull/jsonSerialize). Same
 * code-first style as InviteKeyPrivilegeType: no `declare(strict_types=1)`
 * on purpose (ported repos/service rely on legacy coercion semantics);
 * explicit `public` on the static factories (PSR1 method-visibility).
 */
enum TrvisContentType: int implements \JsonSerializable
{
	case text = 0;
	case URI = 1;
	case PNG = 2;
	case PDF = 3;
	case JPG = 4;

	public static function fromString(string $string): self
	{
		switch (strtolower($string)) {
			case 'text':
				return TrvisContentType::text;

			case 'uri':
				return TrvisContentType::URI;

			case 'png':
				return TrvisContentType::PNG;

			case 'pdf':
				return TrvisContentType::PDF;

			case 'jpg':
				return TrvisContentType::JPG;

			default:
				throw new \Exception("Unknown TrvisContentType: {$string}");
		}
	}

	public static function fromOrNull(int|string|null $value): ?self
	{
		if (is_null($value)) {
			return null;
		}

		if (is_string($value)) {
			return TrvisContentType::fromString($value);
		} else {
			return self::from($value);
		}
	}

	public function jsonSerialize(): string
	{
		return $this->name;
	}
}
