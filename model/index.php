<?php
/**
 * sdbee-service-gateway.php - Endpoint on SD bee server for access to local and remote services
 * Copyright (C) 2023  Quentin CORNWELL
 *  
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

 function SDBEE_endpoint_service( $request) {
    // Parameters
    $localServices = [ 'doc'];
    // Get request and service
    $reqRaw = $request['nServiceRequest'];
    $serviceRequest = JSON_decode( urldecode( $reqRaw), true);
    $serviceName = $serviceRequest['service'];
    $serviceProvider = $serviceRequest['provider'];
    if ( in_array( $serviceName, $localServices)) {
        // Handle request here
        // Get parameters
        $params = [ 'throttle'=>'none'];
        // Call service via local gateway
        include_once __DIR__.'/../local-services/udservices.php';
        $services = new UD_services( $params);
	    $response = $services->do( $serviceRequest);
    } else {
        // Use gateway for external services
        $gateway = $USER[ 'service-gateway'];
        // 2DO JSON_decode as map service:url, get service from $serviceRequest
        if ( !$gateway) {
            $response = '{ "result":"KO", "msg":"Not configured"}';
        } else {
            $user = $USER[ 'service-username'];
            $pass = $USER[ 'service-password'];
            $opts = array('http' =>
                array(
                    'method'  => 'POST',
                    'header'  => 'Content-type: application/x-www-form-urlencoded', // json
                    'content' => "tusername={$user}&tpassword={$pass}&nServiceRequest=".urlencode($reqRaw)
                )
            );
            $context = stream_context_create($opts);
            $url = $gateway."/AJAX_service/LINKS_noRedirect|1/";
            $response = file_get_contents( $url, false, $context);
        }
    }
    // Reply    
    echo $response;
}

SDBEE_endpoint_service( $_POST);
