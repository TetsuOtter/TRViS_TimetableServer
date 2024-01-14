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
 * トークンの発行をリクエストする際に使用するオブジェクト
 * @export
 * @interface TokenRequest
 */
export interface TokenRequest {
    /**
     * APIキー
     * @type {string}
     * @memberof TokenRequest
     */
    apiKey: string;
    /**
     * APIキー
     * @type {string}
     * @memberof TokenRequest
     */
    clientId?: string;
}

/**
 * Check if a given object implements the TokenRequest interface.
 */
export function instanceOfTokenRequest(value: object): boolean {
    let isInstance = true;
    isInstance = isInstance && "apiKey" in value;

    return isInstance;
}

export function TokenRequestFromJSON(json: any): TokenRequest {
    return TokenRequestFromJSONTyped(json, false);
}

export function TokenRequestFromJSONTyped(json: any, ignoreDiscriminator: boolean): TokenRequest {
    if ((json === undefined) || (json === null)) {
        return json;
    }
    return {
        
        'apiKey': json['api_key'],
        'clientId': !exists(json, 'client_id') ? undefined : json['client_id'],
    };
}

export function TokenRequestToJSON(value?: TokenRequest | null): any {
    if (value === undefined) {
        return undefined;
    }
    if (value === null) {
        return null;
    }
    return {
        
        'api_key': value.apiKey,
        'client_id': value.clientId,
    };
}

