<?php

/**
 * TRViS用 時刻表管理用API
 * PHP version 7.4
 *
 * @package dev_t0r
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 */

/**
 * No description provided (generated by Openapi Generator https://github.com/openapitools/openapi-generator)
 * The version of the OpenAPI document: 1.0.0
 * Generated by: https://github.com/openapitools/openapi-generator.git
 */

/**
 * NOTE: This class is auto generated by the openapi generator program.
 * https://github.com/openapitools/openapi-generator
 */
namespace dev_t0r\trvis_backend\model;

use dev_t0r\BaseModel;

/**
 * Color
 *
 * @package dev_t0r\trvis_backend\model
 * @author  OpenAPI Generator team
 * @link    https://github.com/openapitools/openapi-generator
 */
class Color extends BaseModel
{
    /**
     * @var string Models namespace.
     * Can be required for data deserialization when model contains referenced schemas.
     */
    protected const MODELS_NAMESPACE = '\dev_t0r\trvis_backend\model';

    /**
     * @var string Constant with OAS schema of current class.
     * Should be overwritten by inherited class.
     */
    protected const MODEL_SCHEMA = <<<'SCHEMA'
{
  "title" : "Color",
  "required" : [ "color_8bit", "description", "name" ],
  "type" : "object",
  "properties" : {
    "colors_id" : {
      "type" : "string",
      "description" : "ColorのID (UUID)",
      "format" : "uuid",
      "readOnly" : true
    },
    "work_groups_id" : {
      "type" : "string",
      "description" : "この色が属するWorkGroupのID (UUID)",
      "format" : "uuid",
      "readOnly" : true
    },
    "created_at" : {
      "type" : "string",
      "description" : "作成日時",
      "format" : "date-time",
      "readOnly" : true
    },
    "description" : {
      "type" : "string",
      "description" : "WorkGroupの説明",
      "example" : "WorkGroupの説明が入ります"
    },
    "updated_at" : {
      "type" : "string",
      "description" : "更新日時",
      "format" : "date-time",
      "readOnly" : true
    },
    "name" : {
      "type" : "string",
      "description" : "Colorの名前 (詳細な説明はdescriptionに書く)",
      "example" : "赤"
    },
    "color_8bit" : {
      "$ref" : "#/components/schemas/Color8bit"
    },
    "color_real" : {
      "$ref" : "#/components/schemas/ColorReal"
    }
  }
}
SCHEMA;
}
