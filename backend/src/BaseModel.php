<?php

declare(strict_types=1);

namespace dev_t0r;

use InvalidArgumentException;
use StdClass;

/**
 * Trimmed, hand-written model base (code-first).
 *
 * The legacy base was an OpenAPI-Generator artifact carrying an embedded
 * JSON `MODEL_SCHEMA` and a data-mocker dependency. Here the schema is
 * declared by two consts the subclass overrides:
 *
 *   protected const OAS_PROPERTIES = ['snake_case', ...]; // ordered
 *   protected const OAS_REQUIRED   = ['snake_case', ...];
 *
 * Object OAS type only (every TRViS model is an object). swagger-php is
 * NOT used at runtime — the #[OA\Schema]/#[OA\Property] attributes on the
 * subclass drive generation only (require-dev). A drift-guard test keeps
 * the attributes and these consts in lock-step.
 *
 * Behaviour preserved 1:1 from the legacy base so reused repos/services
 * and tests/Integration/IntegrationTestCase::makeModel keep working:
 * createFromData / setData / getData / __get / __set / jsonSerialize /
 * getOpenApiSchema.
 */
class BaseModel implements \JsonSerializable
{
	/** @var string[] ordered snake_case property names; overridden by subclass */
	protected const OAS_PROPERTIES = [];

	/** @var string[] subset of OAS_PROPERTIES that are required; overridden by subclass */
	protected const OAS_REQUIRED = [];

	/** @var array<string,mixed> data container (object OAS type) */
	protected array $dataContainer = [];

	/**
	 * OAS-shaped schema for the runtime model. Only property *names* matter
	 * here (validation in __set/__get, IntegrationTestCase::makeModel which
	 * does array_fill_keys(array_keys($schema['properties']))). Property
	 * types/descriptions live in the #[OA\Property] attributes.
	 *
	 * `required` is omitted entirely when empty: swagger-php would emit a
	 * literal `"required": []`, which is an OpenAPI 3.0 smell.
	 *
	 * @return array<string,mixed>
	 */
	public static function getOpenApiSchema(): array
	{
		$schema = [
			'type' => 'object',
			'properties' => array_fill_keys(static::OAS_PROPERTIES, []),
		];
		if (static::OAS_REQUIRED !== []) {
			$schema['required'] = static::OAS_REQUIRED;
		}
		return $schema;
	}

	/**
	 * @param array<string,mixed>|object $data
	 * @return static
	 */
	public static function createFromData($data): static
	{
		$instance = new static();
		$instance->setData($data);
		return $instance;
	}

	/**
	 * @param array<string,mixed>|object $data
	 */
	public function setData($data): void
	{
		foreach ($data as $key => $value) {
			// routes through __set (schema validation)
			$this->{$key} = $value;
		}
	}

	/**
	 * Object form: declared-and-set props are included; a required prop that
	 * was never set becomes null; an optional prop that was never set is
	 * OMITTED entirely (so response bodies never carry keys the caller did
	 * not provide).
	 */
	public function getData(): StdClass
	{
		$data = new StdClass();
		foreach (static::OAS_PROPERTIES as $propName) {
			if (array_key_exists($propName, $this->dataContainer)) {
				$data->{$propName} = $this->dataContainer[$propName];
			} elseif (in_array($propName, static::OAS_REQUIRED, true)) {
				$data->{$propName} = null;
			}
		}
		return $data;
	}

	public function __set(string $param, $value): void
	{
		if (!in_array($param, static::OAS_PROPERTIES, true)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot set %s property of %s model because it doesn\'t exist in related OAS schema',
				$param,
				static::class,
			));
		}
		$this->dataContainer[$param] = $value;
	}

	public function __get(string $param)
	{
		if (in_array($param, static::OAS_PROPERTIES, true)) {
			// guarded with `?? null`: partial-PATCH-constructed models do NOT
			// pre-fill every prop, so they return null instead of emitting an
			// undefined-key warning, while makeModel()-built models still
			// return their pre-filled value unchanged.
			return $this->dataContainer[$param] ?? null;
		}

		throw new InvalidArgumentException(sprintf(
			'Cannot get %s property of %s model because it doesn\'t exist in related OAS schema',
			$param,
			static::class,
		));
	}

	public function jsonSerialize(): mixed
	{
		return $this->getData();
	}
}
