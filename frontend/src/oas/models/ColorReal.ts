// @ts-nocheck
/* tslint:disable */
/* eslint-disable */
/**
 * TRViS用 時刻表管理用API
 * No description provided (generated by Openapi Generator https://github.com/openapitools/openapi-generator)
 *
 * The version of the OpenAPI document: 1.0.0
 * 
 *
 * NOTE: This class is auto generated by OpenAPI Generator (https://openapi-generator.tech).
 * https://openapi-generator.tech
 * Do not edit the class manually.
 */

import { exists, mapValues } from '../runtime';
/**
 * 
 * @export
 * @interface ColorReal
 */
export interface ColorReal {
    /**
     * 色の赤色成分 (小数)
     * @type {number}
     * @memberof ColorReal
     */
    red?: number;
    /**
     * 色の緑色成分 (小数)
     * @type {number}
     * @memberof ColorReal
     */
    green?: number;
    /**
     * 色の青色成分 (小数)
     * @type {number}
     * @memberof ColorReal
     */
    blue?: number;
}

/**
 * Check if a given object implements the ColorReal interface.
 */
export function instanceOfColorReal(value: object): boolean {
    let isInstance = true;

    return isInstance;
}

export function ColorRealFromJSON(json: any): ColorReal {
    return ColorRealFromJSONTyped(json, false);
}

export function ColorRealFromJSONTyped(json: any, ignoreDiscriminator: boolean): ColorReal {
    if ((json === undefined) || (json === null)) {
        return json;
    }
    return {
        
        'red': !exists(json, 'red') ? undefined : json['red'],
        'green': !exists(json, 'green') ? undefined : json['green'],
        'blue': !exists(json, 'blue') ? undefined : json['blue'],
    };
}

export function ColorRealToJSON(value?: ColorReal | null): any {
    if (value === undefined) {
        return undefined;
    }
    if (value === null) {
        return null;
    }
    return {
        
        'red': value.red,
        'green': value.green,
        'blue': value.blue,
    };
}

