<?php
/**
 * Facebook service
 */
/* Composer
 {
  "require" : { "facebook/graph-sdk" : "^2.10"}
}
*/
/* UD
{
  "require" : [ "social/udsocialservice.php"],
  "publish" : [ "social-facebook-service"]
}
*/ 
require_once "udsocialservice.php";
require VENDOR_AUTOLOAD;
 
class UDS_facebook extends UD_socialService {
    
    $appId = "";     // SD bee app on Facebook
    $appSecret = "";
    $graphVersion = "";
    $fbClient = null;
 
 
    function getClient() {
        // Use existing client
        if ( !$this->fbClient) { 
            // Setup new client
            $fb = new Facebook\Facebook([
              'app_id' => $this->appId,
              'app_secret' => $this->appSecret,
              'default_graph_version' => $this->graphVersion,
            ]);
            if ( $fb) {$this->client = $fb;}
            // 2DO errors
        }
        return $this->client;
    }
    
    function getAccessToken() {}
    function getPageAccessToken() {}
    function publishPost() {}
    function getPostInsights() {}
    
    function event() {} // Webhook 
    
 
} // UDS_facebook PHP class