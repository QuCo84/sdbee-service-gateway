<?php

/** 
  * textgengooseai.php
   * NEEDS CREDITS
  */
  
require_once( __DIR__."/../udservices.php");

class UDS_GooseAI_textgen extends UD_service {
    private $key = "";
    
    function __construct( $params, $throttle=null, $throttleId="") {
        $this->key = $params[ 'key'];
    }

    function call( $serviceRequest) {
        // 2DO action == "complete"
        try {
            $text = $serviceRequest[ 'text'];
            $lang =  $serviceRequest[ 'lang'];
            $engine = $serviceRequest[ 'engine'];            
            if ( $lang && $lang != 'en') {
                $textEN = $this->translate( $text, $lang, 'en');
                $genText = $this->complete( $textEN, $engine);
                $genText = $this->translate( $genText, 'en', $lang);                
            } else  $genText = $this->complete( $serviceRequest[ 'text']);
            // Build Response data 
            $this->lastResponse = implode( "\n", $genText);
            $this->lastResponseRaw = [ 'gentext'=>$genText];
            $this->cacheable = true;
            $this->creditsConsumed = 1;
        } catch ( Exception $e) {
            $this->lastResponse = "ERR : could not generate text on Goose AI".$this->key." is key";
            $this->lastResponseRaw = $e->getMessage();
            echo "ERR Goose AI : " . $e->getMessage();
            return false;
        }
        return true;
    }

    function getEngines() {
        $url = "https://api.goose.ai/v1/engines";
        $ch = curl_init( $url);
        $request = [ 
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->key}"
            ],
        ];
        curl_setopt_array( $ch, $request);
        $responseText = curl_exec( $ch);
        $response = JSON_decode( $responseText, true);
        // var_dump( $response);
    }
    
    function complete( $prompt, $engine="gpt-neo-20b") {
        $url = "https://api.goose.ai/v1/engines/{$engine}/completions";
        $ch = curl_init( $url);
        $postData = JSON_encode( [ 
            'prompt'=>$prompt, 
            'n' => 5,
            'temperature' => 0.2,
            'frequency_penalty' => 2, /* -2.0 - 2.0 */
            'repetition_penalty' =>7 /* 0-8.0 */
            // logit_bias token=>bias -100 -100 */
        ]);
        $request = [ 
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->key}",
                "Content-Type:application/json",
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData
        ];
        curl_setopt_array( $ch, $request);
        $responseText = curl_exec( $ch);
        $response = JSON_decode( $responseText, true);
        if ( $response[ 'error']) throw new Exception( $response[ 'error'][ 'message']);
        $choices = $response[ 'choices'];
        $completion = [];
        for ( $chi=0; $chi<LF_count( $choices); $chi++) {
            $completion[] = $choices[$chi]['text'];
        }
        // var_dump( $response, $responseText, $url, $postData);
        return $completion;               
    }
    
    function translate ($source, $sourceLang="en", $targetLang="fr") {
        $flatSource = ( is_array( $source)) ? implode( "/", $source) : $source;
        $translateRequest = [ 
            'key' => "AIzaSyBP--VaXzYRX-4LSqdL6P_ZWpisOHuzMYk",
            'q' => urlencode( $flatSource), 
            'target'=> $targetLang,
            'source' => $sourceLang
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
        $result = ( is_array( $source)) ? explode( "/", $translation) : $translation;
        return $result;
    }
} // PHP class GooseAI_

if ( $argv[0] && strpos( $argv[0], "udsgooseaitextgen.php") !== false) {
    // CLI launched for tests
    echo "Syntax OK\n";
    // Create an UD
    require_once( __DIR__."/../../tests/testenv.php");
    require_once( __DIR__."/../../tests/testsoilapi.php");    
    // Testing with pt95 account : sk-btqrc2T6ZIniYLeeEUeZoBODVBCYGqq4gJ0KPFFqXiaI7YKG
    // Testing width contact@sd-bee.com (via Google) sk-Sgzmd1Kzc0hoobTt8Fhzhete5wzbVx3Kbga9EeT97LTbFVSw
    $textgen = new UDS_GooseAI_textgen( [ 'key'=>"sk-Sgzmd1Kzc0hoobTt8Fhzhete5wzbVx3Kbga9EeT97LTbFVSw"]);
    // $textgen->getEngines();
    $text = "A website must be visible to Internet users. This means being referenced on a search engines.";
    for ( $i=0; $i < 1; $i++) {
        $test = "Call$i";
        try {
            $r = $textgen->complete( $text, "gpt-neo-20b");
            $text .= implode( ".", $r);
            // if ($r) {  $text .= $r; echo "Test $test: OK $r\n";} else echo "Test $test: KO\n";
        } catch ( Exception $e) {
            echo "ERR Goose AI TEST :" . $e->getMessage()."\n";
        }        
    }
    echo "Test $test: OK\n";
    echo $text."\n";
    //echo $textgen->translate( $text, 'en', 'fr')."\n";
    echo "Test completed\n";
}