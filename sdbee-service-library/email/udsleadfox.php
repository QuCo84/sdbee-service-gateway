<?php
/**
 * udsleadfox.php Email service with LeadFox
 */
require_once "emailservice.php";

class UDS_leadFox extends EmailService {
    public $lastResponse = "";
    public $lastResponseRaw = [];
    public $permanentLink = "https://app.leadfox.co/htmlemails/{{msgId}}/";
    public $unsubscribeLink = "{{email_unsubscribe}}";
    public $ESPpub = '<span style="font-size:10px;">Ce message a été expédié par le logiciel <a href="https://www.leadfox.co" target="_blank" style="text-decoration: underline; color: #065c53;" rel="noopener">Leadfox</a>.</span>';
    private $baseURL = "https://rest.leadfox.co";
    private $apiKey;
    private $secret;
    private $token;

    function __construct( $params) {
                // parent::__construct( $params);
        // $this->provider = $account_json[ 'provider'];  
        if ( isset( $params[ 'leadfox'])) {
            $myParams = $params[ 'leadfox'];
            $this->apiKey = $myParams['apiKey'];
            $this->secret = $myParams['secret']; 
        }        
        $this->defaultSenderName = $params[ "senderName"];
        $this->defaultSenderEmail = $params[ "senderEmail"];
        if ( isset( $params[ "allowedSenders"])) { $this->allowedSenders = $params[ "allowedSenders"];}

    }

   /**
    * ABSTRACT CLASS IMPLEMENTATION
    */ 
    function send( $data, $folderId="", $emailId="") {
        // Prepare jSON
        $json = '{"page":{"body":{"container":{"style":{"background-color":"#FFFFFF"}},"content":{"computedStyle":{"linkColor":"#0068A5","messageBackgroundColor":"transparent","messageWidth":"650px"},"style":{"color":"#000000","font-family":"Arial, Helvetica Neue, Helvetica, sans-serif"}},"type":"mailup-bee-page-proprerties"},"description":"","rows":[{"columns":[{"grid-columns":12,"modules":[{"type":"mailup-bee-newsletter-modules-html","descriptor":{"html":{"html":"{htmlContent}","style":{"padding-top":"0px","padding-right":"0px","padding-bottom":"0px","padding-left":"0px"},"computedStyle":{"hideContentOnMobile":false}},"uuid":"60e4d267-524a-41b0-8ecb-68b6b34828cb"},"style":{"background-color":"transparent","border-bottom":"0px dotted transparent","border-left":"0px dotted transparent","border-right":"0px dotted transparent","border-top":"0px dotted transparent","padding-bottom":"5px","padding-left":"0px","padding-right":"0px","padding-top":"5px"},"uuid":"173b5e2c-8d49-4ec4-abab-f3793368eae5"}],"container":{"style":{"background-color":"transparent"}},"content":{"style":{"background-color":"transparent","color":"#000000","width":"650px"},"computedStyle":{"rowColStackOnMobile":true,"rowReverseColStackOnMobile":false,"verticalAlign":"top","hideContentOnMobile":false,"hideContentOnDesktop":false}},"type":"one-column-empty","uuid":"90521572-426c-4d55-81f0-833c0b83d6f0"}],"template":{"name":"template-base","type":"basic","version":"0.0.1"},"title":""}],"comments":{}}}';
        $structure = JSON_decode( $json, true);
        //var_dump( $structure['page']['rows'][0]['columns'][0]['modules'][0]['descriptor'][ 'html']['html']);
        $htmlInStructure = $structure['page']['rows'][0]['columns'][0]['modules'][0]['descriptor'][ 'html']['html'];
        $htmlInStructure = str_replace( '{htmlContent}', $data[ 'body'], $htmlInStructure);
        $structure['page']['rows'][0]['columns'][0]['modules'][0]['descriptor'][ 'html']['html'] = $htmlInStructure;
        $json = JSON_encode( $structure);
        //var_dump( $htmlInStructure); die();
        // Get token
        if ( !$this->_getToken()) {
            $this->lastResponseRaw = "Bad credentials - no token";
            return false;
        }
        // Get folder Id 2Do using API trials
        if ( !$folderId) $folderId = "637fa1099469e70008e7a435"; 
        if ( !$emailId) {
            // Create email variant
            $emailName = "BAT - " . $data[ 'title'];
            $lfData = [
                'name' => $emailName,
                'fromemail' => $data[ 'from'][ 'email'],
                'fromname' => $data[ 'from'][ 'name'],
                'subject' => $data[ 'subject'],
                'editor' => "bee",
                'body' => $data[ 'body'],
                'json' => $json,
                'description' => "Description of the email",
                'preheader' => "Preview text",
                'unsubscribetext' => "Désinscription",
            ];
            FILE_write( 'tmp', 'Leadfox_debug.txt', -1, JSON_encode( $structure, JSON_PRETTY_PRINT) ."\n".);
            $r = $this->_post( "email", $lfData); 
            if ( isset( $r[ 'error']) || !isset( $r[ '_id'])) {
                // dataSource : "id"
                $this->lastResponseRaw = $r;
                return false;
            }
            $emailId = $r[ '_id'];
        
        }
        // Place email variant in folder
        $lfData = [
            /* to send
            "status" : "a",
            "start" : "2022-11-11T14:37:20.000Z",
            */
            "status" => "d",
            "name" => $emailName,
            "folder" => $folderId, // $data[ 'folderId'],
            "type" => 2,
            "emails" => [            
                "email" => $emailId,
                "weight" => 100
            ],
            "lists" => [
                "list" => "6399df0e894922000836a9c6", // Use BAT list"633c7d30d5d24e00082fec48",
                "sent" => false
            ]
        ];
        $r = $this->_post( "campaign", $lfData);
        if ( isset( $r[ 'error']) || !isset( $r[ '_id'])) {
            $this->lastResponseRaw = JSON_encode( $r);
            return false;            
        }
        if ( strpos( $data[ 'body'], '{{msgId}}')) {
            // Update msgId
            $html = str_replace( '{{msgId}}', $emailId, $data[ 'body']);
            $htmlInStructure = $structure['page']['rows'][0]['columns'][0]['modules'][0]['descriptor'][ 'html']['html'];
            $htmlInStructure = str_replace( '{htmlContent}', $data[ 'body'], $htmlInStructure);
            $structure['page']['rows'][0]['columns'][0]['modules'][0]['descriptor'][ 'html']['html'] = $htmlInStructure;
            $json = JSON_encode( $structure);
            $lfData = [
                'name' => $emailName,
                'fromemail' => $data[ 'from'][ 'email'],
                'fromname' => $data[ 'from'][ 'name'],
                'subject' => $data[ 'subject'],
                'editor' => "bee",
                'body' => $html,
                'json' => $json,
                'description' => "Description of the email",
                'preheader' => "Preview text",
                'unsubscribetext' => "Désinscription",
            ];
            $r = $this->_put( "email/$emailId", $lfData); 
            if ( isset( $r[ 'error']) || !isset( $r[ '_id'])) {
                // dataSource : "id"
                $this->lastResponseRaw = $r;
                return true;
            }
        }    
        // 2DO if ( strpos( $data[ 'body'], '{msgId}')) { // replace {msgId}, $emailid, $body} and Update
        // dataSource : "id"
        $this->lastResponseRaw = [ 'id' => $emailId];
        return true;
       
    }
    // renalmm send _send function send( $data) { $this._send( $data, $testFolder);}


    function sendCampaign( $data) {

    }

    function getStats( $data) {
     // use /metric/emailid
    }

    function setupCampaign( $data) {
        // Lookup email & folder Id
        $newsFolder = "63baef44126ecb0008a89a50"; // Or look up Newsletter YYYY
        $this._send( $data, $newsFolder);
    }
    /**
    * Get Contact lists and Segmentation
    */
    function getContactListAndSegmentation( $data) {
    }   
    
   /**
    * Get a campaign's state  
    */
    function getState( $data) { // getStateOfLastCampaignForList( listId)
    }

   /**
    * LOCAL METHODS
    */ 
    
    function _getToken() {
        if ( !$this->token) {
            $dataload = [ 'apikey'=>$this->apiKey, 'secret'=>$this->secret];
            $j = $this->_post( "auth", $dataload);
            if ( $j) $this->token = $j[ 'jwt'];
        }
        return $this->token;
    }

    function _get( $endpoint, $dataload = ""){
        $options = array(
            CURLOPT_URL => "{$this->baseURL}/v1/{$endpoint}",// . ( ($dataload) ? ":" . $dataload : ""),      
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [ "Authorization: JWT " . $this->token]
        );
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $r = curl_exec( $ch);
        return JSON_decode( $r, true);
    }

    function _post( $endpoint, $dataload) {
        $options = array(
            CURLOPT_URL => "{$this->baseURL}/v1/{$endpoint}",     
            CURLOPT_HTTPHEADER => [ "Authorization: JWT " . $this->token],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query( $dataload),
        );
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $r = curl_exec( $ch);
        return JSON_decode( $r, true);
    }

    function _put( $endpoint, $dataload) {
        $options = array(
            CURLOPT_URL => "{$this->baseURL}/v1/{$endpoint}",     
            CURLOPT_HTTPHEADER => [ "Authorization: JWT " . $this->token],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_POSTFIELDS => http_build_query( $dataload),
        );
        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $r = curl_exec( $ch);
        return JSON_decode( $r, true);
    }

} // PHP class UDS_leadFox

// Auto-test
if ( $argv[0] && strpos( $argv[0], "udsleadfox.php") !== false)
{
    // CLI launched for tests
    echo "Syntax OK\n";
    require_once( __DIR__."/../../tests/testenv.php");
    print( hash( "sha256", "lEad369Fox!"));
    $params = [
        'leadFox' => [
            'apiKey'=>"api key",
            'secret' => "secret"
        ],
        'senderName' => "Retraite.com",
        'senderEmail' => "contact@retraite.com"
    ];
    $lf = new UDS_leadFox( $params);

    {
        $test = "1 - connect";
        $token = $lf->_getToken();
        echo $token."\n";
        $folders = $lf->_get( 'folder');
        /* 2DO
           Loop to find name
           then get id
        */
        $folderId = "637fa1099469e70008e7a435";   
    }
    if (false) {
        $token = $lf->_getToken();
        echo $token."\n";
        $json = $lf->_get( '/email/638a2536006457000890d871');
        //var_dump( $json);
        FILE_write( 'tmp', "jsonlf.json", -1, JSON_encode( $json));
    }
    if (true) {
        $test = "2 - send email";
        $data = [
            'folderId' => $folderId,
            'from' => [ 'name'=>"SD bee", 'email'=>"contact@sd-bee.com"],
            'to' => [ 'name'=>"QCornwell", 'email'=>"qcornwell@gmail.com"],
            'subject' => "LeadFox AutoTest",
            'body' => "This is a <strong>test</strong> message for <strong>Leadfox</strong>."    
        ];
        $sg = $lf->send( $data);

    }
    // $result = $sg->Send( $data);
    // if ( $result) echo "Test $test:OK\n"; else echo "Test $test:KO\n";
    /*
    $test = "Test 2 - create campaign";
    $msg = "Hi There";
    // $msg = file_get_contents( __DIR__."/../../../News2211.html");
    $data = [
        'title' => "Test campaign",
        'from' => [ 'name'=>"Retraite.com", 'email'=>"contact@retraite.com"],
        'subject' =>"News News",
        'body' => $msg,
        'contactList' => "e8cfafdc-6a42-42d9-b144-de8e640cf328" // "TestList"
    ];
    //$result =  $sg->SetupCampaign( $data);
    //if ( $result) echo "Test $test:OK {$sg->lastResponseRaw[ 'id']}\n"; else echo "Test $test:KO {$sg->lastResponse}\n";
    $test = "3 - Stats";
    $data = [
        'draftId' => "0802b20c-a6c4-11ec-a4f8-1e5333259b03"
    ];
    $result =  $sg->getStats( $data);
    $result &= ( LF_count( $sg->lastResponseRaw[ 'values'])>0);
    if ( $result) echo "Test $test:OK\n"; else echo "Test $test:KO {$sg->lastResponse}\n";
    */
    
    echo "Test completed\n";
    
}  