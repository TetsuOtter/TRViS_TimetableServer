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
 * @interface TokenResponse
 */
export interface TokenResponse {
    /**
     * クライアントの識別子
     * @type {string}
     * @memberof TokenResponse
     */
    clientId?: string;
    /**
     * 発行された認証トークン
     * @type {string}
     * @memberof TokenResponse
     */
    token?: string;
    /**
     * トークンの有効期限
     * @type {string}
     * @memberof TokenResponse
     */
    expires?: string;
}

/**
 * Check if a given object implements the TokenResponse interface.
 */
export function instanceOfTokenResponse(value: object): boolean {
    let isInstance = true;

    return isInstance;
}

export function TokenResponseFromJSON(json: any): TokenResponse {
    return TokenResponseFromJSONTyped(json, false);
}

export function TokenResponseFromJSONTyped(json: any, ignoreDiscriminator: boolean): TokenResponse {
    if ((json === undefined) || (json === null)) {
        return json;
    }
    return {
        
        'clientId': !exists(json, 'client_id') ? undefined : json['client_id'],
        'token': !exists(json, 'token') ? undefined : json['token'],
        'expires': !exists(json, 'expires') ? undefined : json['expires'],
    };
}

export function TokenResponseToJSON(value?: TokenResponse | null): any {
    if (value === undefined) {
        return undefined;
    }
    if (value === null) {
        return null;
    }
    return {
        
        'client_id': value.clientId,
        'token': value.token,
        'expires': value.expires,
    };
}

