<?php


class UDS_translation extends UD_service{

    function __construct() {
        $this->cacheable = true;
    }

    function call( $serviceRequest) {
        return $this->_translate( $serviceRequest);        
    }

    function _translate( $serviceRequest) {
        // 2DO Use secret for key
        $translateRequest = [ 
            'key' => "<API key>",
            'q' => urlencode( $serviceRequest[ 'text']), 
            'target'=> $serviceRequest[ 'target'],
            'source' => $serviceRequest[ 'source']
        ];
        $url = "https://www.googleapis.com/language/translate/v2?";
        foreach( $translateRequest as $key=>$val) {
            $url .= "&{$key}={$val}";
        }
        $ch = curl_init( $url);
        $request = [ 
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => 0,
        ];
        curl_setopt_array( $ch, $request);
        $responseText = curl_exec( $ch);
        $response = JSON_decode( $responseText, true);
        $translation = html_entity_decode( $response[ 'data']['translations'][0]['translatedText']);
        $translation = str_replace( "&#"."39;", "'", $translation);
        $jsonResponse = [ 
            'success' => True, 
            'message'=> "Google Translate", 
            'data'=>[ 'translation'=>$translation, /*'url'=>$url, 'rep'=>$responseText*/]
        ];                     
    }
}