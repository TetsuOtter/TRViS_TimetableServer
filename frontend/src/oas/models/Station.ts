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
import type { StationLocationLonlat } from './StationLocationLonlat';
import {
    StationLocationLonlatFromJSON,
    StationLocationLonlatFromJSONTyped,
    StationLocationLonlatToJSON,
} from './StationLocationLonlat';

/**
 * 
 * @export
 * @interface Station
 */
export interface Station {
    /**
     * Stationの説明
     * @type {string}
     * @memberof Station
     */
    description: string;
    /**
     * 駅名
     * @type {string}
     * @memberof Station
     */
    name: string;
    /**
     * 駅の位置 (km)
     * @type {number}
     * @memberof Station
     */
    locationKm: number;
    /**
     * 駅の種類
     * @type {number}
     * @memberof Station
     */
    recordType: number;
    /**
     * StationのID (UUID)
     * @type {string}
     * @memberof Station
     */
    readonly stationsId?: string;
    /**
     * WorkGroupのID (UUID)
     * @type {string}
     * @memberof Station
     */
    readonly workGroupsId?: string;
    /**
     * 作成日時
     * @type {Date}
     * @memberof Station
     */
    readonly createdAt?: Date;
    /**
     * 
     * @type {StationLocationLonlat}
     * @memberof Station
     */
    locationLonlat?: StationLocationLonlat;
    /**
     * その駅にいるかどうかを判定する円の半径 (m)
     * @type {number}
     * @memberof Station
     */
    onStationDetectRadiusM?: number;
}

/**
 * Check if a given object implements the Station interface.
 */
export function instanceOfStation(value: object): boolean {
    let isInstance = true;
    isInstance = isInstance && "description" in value;
    isInstance = isInstance && "name" in value;
    isInstance = isInstance && "locationKm" in value;
    isInstance = isInstance && "recordType" in value;

    return isInstance;
}

export function StationFromJSON(json: any): Station {
    return StationFromJSONTyped(json, false);
}

export function StationFromJSONTyped(json: any, ignoreDiscriminator: boolean): Station {
    if ((json === undefined) || (json === null)) {
        return json;
    }
    return {
        
        'description': json['description'],
        'name': json['name'],
        'locationKm': json['location_km'],
        'recordType': json['record_type'],
        'stationsId': !exists(json, 'stations_id') ? undefined : json['stations_id'],
        'workGroupsId': !exists(json, 'work_groups_id') ? undefined : json['work_groups_id'],
        'createdAt': !exists(json, 'created_at') ? undefined : (new Date(json['created_at'])),
        'locationLonlat': !exists(json, 'location_lonlat') ? undefined : StationLocationLonlatFromJSON(json['location_lonlat']),
        'onStationDetectRadiusM': !exists(json, 'on_station_detect_radius_m') ? undefined : json['on_station_detect_radius_m'],
    };
}

export function StationToJSON(value?: Station | null): any {
    if (value === undefined) {
        return undefined;
    }
    if (value === null) {
        return null;
    }
    return {
        
        'description': value.description,
        'name': value.name,
        'location_km': value.locationKm,
        'record_type': value.recordType,
        'location_lonlat': StationLocationLonlatToJSON(value.locationLonlat),
        'on_station_detect_radius_m': value.onStationDetectRadiusM,
    };
}
