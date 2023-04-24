<?php
/** 
 * UD Service Mailjet 
 *
 * Email service connection for Smartdocs
 *
 */
 /* Composer
 {
  "require" : { "mailjet/mailjet-apiv3-php" : "^1.5"}
}
*/
/* UD
{
  "require" : [ "email/emailservice.php"],
  "publish" : [ "email-mailjet-service"]
}
*/

 require "emailservice.php";
 require VENDOR_AUTOLOAD;
 use \Mailjet\Resources;
 
 class UDS_mailjet extends EmailService {
    public $lastResponse = "";
    public $lastResponseRaw = [];
    public $permanentLink = "[[PERMALINK]]";
    public $unsubscribeLink = "[[UNSUB_LINK]]";
    public $ESPpub = "";
    protected $privateKey="";
    protected $publicKey="";
    protected $id = 1;
    protected $defaultSenderName = "";
    protected $defaultSenderEmail = "";
    protected $allowedSenders = [];
    
    function  __construct( $params)
    {
        // parent::__construct( $params);
        // $this->provider = $account_json[ 'provider'];  
        // 2DO uncrypt
        if ( isset( $params[ 'mailjet'])) {
            $this->publicKey = $params[ 'mailjet']['public'];
            $this->privateKey = $params[ 'mailjet']['private'];
        } else {
            // DEPRECATED
            $this->publicKey = $params['public'];
            $this->privateKey = $params['private'];
        }        
        // 2DO Grab aliases for senders
        // Get paramaters that are not specific to a provider
        $this->_grabNonProviderParameters( $params);
        /*$this->defaultSenderName = $params[ "senderName"];
        $this->defaultSenderEmail = $params[ "senderEmail"];
        if ( isset( $params[ "allowedSenders"])) { $this->allowedSenders = $params[ "allowedSenders"];}
        */
        // 2DO Grab messages for unsubscribe etc
    } 
    
 
    // Send an email 
    function send( $data)    
    {
        $msg = $data;
        // 2DO check sender is authorised, use default elsewise
        $sender = $msg['from']['email'];
        /* BUG  allowedSenders[ 'newsletter'] or any
        if ( !in_array( $sender, $this->allowedSenders) && $this->defaultSenderEmail) {
            $sender = $this->defaultSenderEmail;
        }
        */
        $mj = new \Mailjet\Client( $this->publicKey, $this->privateKey, true, ['version' => 'v3.1']);
        $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => $sender,
                        'Name' =>  $msg['from']['name'],
                    ],
                    'To' => [
                        [
                            'Email' => $msg['to']['email'],
                            'Name' => $msg['to']['name']
                        ]
                    ],
                    'Subject' => $msg['subject'],
                    'TextPart' => HTML_stripTags( $msg['body']),
                    'HTMLPart' => $msg['body'],
                    'CustomID' => "Send".$this->id++,
                ]
            ]
        ];
        $response = $mj->post(Resources::$Email, ['body' => $body]);       
        $this->lastResponseRaw = $response->getData(); //print_r( $response->getData(), true);
        // $jsonResponse = JSON_decode( $this->lastResponseRaw, true);
        // if ( $jsonResponse) $this->lastResponseRaw = $jsonResponse;
        return $response->success();
    } // EmailService->send()
    
    // Setup a campaign
    function setupCampaign( $data)
    {
        $campaign = $data;
        global $LF; // for debug
        // Get client
        $mj = new \Mailjet\Client( $this->publicKey, $this->privateKey, true, ['version' => 'v3']);
        // Get list of campaigns
        $response = $mj->get(Resources::$Campaign);
        $this->lastResponse = print_r( $response->getData(), true);
        //$LF->out( "debuginfo get camps".$response->getStatus().' '.$this->lastResponse."\n");
        $resp =  $this->lastResponse;        
        //$LF->out( "Existing campaigns : ".$resp->Count."\n");
        $title = $campaign['title'];
        // Setup campaign  with contact list specified        
        $body = [
            'Locale' => "fr_FR",
            'Sender' => $campaign['from']['id'],
            'SenderName' => $campaign['from']['name'],
            'SenderEmail' => $campaign['from']['email'],
            'Subject' => $campaign['subject'],
            'ContactsListID' => $campaign['contactList'],
            'Title' => $title,
            'EditMode' => "html2",
            //'Html-part' => $campaign['body'],
            //'MJMLContent' => "",
            //'Text-part' => HTML_stripTags( $campaign['body'])            
        ];
        $response = $mj->post(Resources::$Campaigndraft, ['body' => $body]);
        $this->lastResponse .= print_r( $response->getData(), true);
        //$LF->out( "debuginfo create draft".$response->getStatus().' '.$this->lastResponse."\n");
        if ( !$response->success()) return false;
        $ID = $response->getData()[0]['ID'];        
        // Create email
        $body =  str_replace( '\"', '"', $campaign['body']);
        $body = [
            'Headers' => "object",
            'Html-part' => $body,
            'MJMLContent' => "",
            'Text-part' => HTML_stripTags( $body)
        ];
        $response = $mj->post(Resources::$CampaigndraftDetailcontent, ['id' => $ID, 'body' => $body]);
        $this->lastResponse .= print_r( $response->getData(), true);
        if ( !$response->success()) return false;
        // BAT test and alert
        $body = [
            'Recipients' => [
                [
                    'Email' => "qcornwell@gmail.com",
                    'Name' => "Quentin Cornwell"
                ]
            ]
        ];
        $response = $mj->post(Resources::$CampaigndraftTest, ['id' => $ID, 'body' => $body]);
        $this->lastResponse .= print_r( $response->getData(), true);
        $this->lastResponseRaw = $response->getData();
        $this->lastResponseRaw[ 'id'] = $ID;
        if ( !$response->success()) return false;
        // Program for in 2 hours by default
        $body = [
            'Date' => date( "Y-m-d H:i:s", time()+2*3600)
        ];
        /* Scheduling disabled for tests Use a paramter (shootDate)
        // Schedule campaign
        $response = $mj->post(Resources::$CampaigndraftSchedule, ['id' => $ID, 'body' => $body]);        
        $this->lastResponse .= print_r( $response->getData(), true);
        if ( !$response->success()) return $response->success();
        if ( $response->success()) return $ID; else return "ERR:".print_r( $response->getData(), true);
        */
        return $response->success();
    } // Emailservice->setupCampaign()
    
    /* Idea
    function setupCampaigns( $data) {
        // Get lists and titles
        // loop
           // Use data with single contact list & title
           // if bad result stop
           // else cumulate result
    }
    */
   /**
    * Get Contact lists and Segmentation
    */
    function getContactListAndSegmentation( $data) {
        global $LF; // for debug
        /* No API fct available
         * use a specific email : newsletterLists to find list ids
         * no solution fund for segmentation will need parameters
         */
        // Get client
        $mj = new \Mailjet\Client( $this->publicKey, $this->privateKey, true, ['version' => 'v3']);
        $body = [
            'Messages' => [
            ]
        ];
        $response = $mj->post(Resources::$Email, ['body' => $body]);
        $this->lastResponse = print_r( $response->getData(), true);
        return $response->success();
        
    }   
    
   /**
    * Get a campaign's state  
    */
    function getState( $data) { // getStateOfLastCampaignForList( listId)
        global $LF; // for debug
        // Get client
        $mj = new \Mailjet\Client( $this->publicKey, $this->privateKey, true, ['version' => 'v3']);
        // Get list of campaigns sent to contact list for a period covering sent date 
        $sentDate = LF_date( $data[ 'sentDate']);
        $sentSince = $sentDate - LF_date();
        if ( $sentSince < 7*86400) $period = "Week";
        elseif ( $sentSince < 30*86400) $period = "Month";
        else $period = "Year";        
        $body = [
            'listId' => $data[ 'listId'],
            'period' => $period
        ];
        $draftId = $data[ 'id'];
        $response = $mj->get(Resources::$Campaign, $body);
        $responseData = $response->getData();
        for ( $respi = 0; $respi < LF_count( $responseData); $respi++) {
            $campaign = $responseData[ $respi];
            if ( $campaign[ 'NewsLetterID'] == $draftId) {
                $endDate =  $campaign[ 'SendEndAt'];
                break;
            }
        }        
        /*        
        $endDate =  $data[ 'SendEndAt'];
        // Sort through list to find campaign
        
        $body = [
            'id' => $campaignId,
            // Period : Week
            // ContactsListId : listId
        ];
        $response = $mj->get(Resources::$Campaign, $body);
        $data = $response->getData()[0];
        $endDate =  $data[ 'SendEndAt'];
        */
        $state = "unknown";
        if ( $endDate > LF_date( time())) $state = "Sent";
        $this->lastResponse = print_r( $data, true);
        $this->lastResponseRaw = [ 'state'=>$state, 'date'=>$endDate];
        return $response->success();
    }
   
   /*
    * Get sent campaign's new id (changes when campaign is programmed) 
    */
    function getSentCampaignId( $data, $mj) {
        $r = [];
        // Get list of id's stored by caller
        $draftId = explode( ',', $data[ 'draftId']);
        // Get list of campaigns sent to contact list for a period covering sent date 
        $sentDate = LF_date( $data[ 'sentDate']);
        $sentSince = LF_date() - $sentDate;
        $this->lastResponseRaw = $sentSince."secs".$sentDate." ";
        if ( $sentSince < 7*86400) $period = "Week";
        elseif ( $sentSince < 30*86400) $period = "Month";
        else $period = "Year";        
        $body = [
            //'listId' => $data[ 'listId'],
            //'Period' => $period
            'FromTS' => LF_timestamp( $sentDate)
        ];       
        $response = $mj->get(Resources::$Campaign, [ 'filters' => $body]);
        $responseData = $response->getData();
        $this->lastResponseRaw = LF_count( $responseData)." ".$draftId[0]." ".$sentDate." ";
        // Look for sent campaigns whose NewsletterID is the same as the id supplied by caller
        for ( $respi = 0; $respi < LF_count( $responseData); $respi++) {
            $campaign = $responseData[ $respi];
            $this->lastResponseRaw .= " ".$campaign[ 'NewsLetterID']." ".$campaign[ 'ID'];
            if ( in_array( $campaign[ 'NewsLetterID'], $draftId)) {
                $r[] =  $campaign[ 'ID'];
            }
        }
        return $r;
    } // UDS_mailjet->getSentCampaignId()

   /**
    * Get a campaign's statistics   
    */
    function getStats( $data) {
        global $LF; // for debug
        // Get Mailjet API client
        $mj = new \Mailjet\Client( $this->publicKey, $this->privateKey, true, ['version' => 'v3']);
        // Get campaign ids corresponding to draft ids   
        if ( substr( $data[ 'draftId'],0, 2)  == "77") {
            // Supplied campaign id(s) looks like a sent campaign id(s)
            $campaignIds = explode( ',', $data[ 'draftId']);
        } else {
            // Supplied campaign id(s) looks like draft campaign id(s), so get sent campaign ids
            $campaignIds = $this->getSentCampaignId( $data, $mj);
        }
        $this->lastResponseRaw .= "ID not found";
        if ( !LF_count( $campaignIds)) return false;
        // Aggregate data for each campaign
        $stats = [ 0, 0, 0, 0];
        for ( $campi=0; $campi < LF_count( $campaignIds); $campi++) {
            $campaignId = $campaignIds[ $campi];
            // Get stats for this campaign
            $body = [
                'SourceId' => $campaignId,
                'CounterSource' => 'Campaign',
                'CounterTiming' => 'Message',
                'CounterResolution' => 'Lifetime'
            ];
            $response = $mj->get(Resources::$Statcounters, ['filters' => $body]);
            $campaignData = $response->getData()[0];
            $this->lastResponse = print_r( $data, true);
            if ( $response->success()) {
                //$perf = round( 100*$campaignData['EventClickCount']/$campaignData['MessageSentCount'], 2);
                $sentCount = $campaignData['MessageSentCount']+$campaignData['MessageBlockedCount']+$campaignData['MessageDeferredCount'];
                $sentCount += $campaignData['MessageHardBouncedCount'] + $campaignData['MessageSoftBouncedCount'];
                $stats[0] += $sentCount;
                $stats[1] += round( $campaignData['MessageSentCount']); // /100
                $stats[2] += round( $campaignData['MessageOpenedCount']); // /10
                $stats[3] += $campaignData['MessageClickedCount'];

                // FILE_write( 'tmp', 'MJstats', 20000, print_r( $campaignData, true));
            }
        }
        $this->lastResponseRaw = [ "campaignIds"=>$campaignIds, "values"=>$stats];
        return $response->success(); //$response->success();
    }
 } // PHP Mailjet PHP class
 
 // Auto-test
if ( $argv[0] && strpos( $argv[0], "udsmailjet.php") !== false)
{
    // CLI launched for tests
    echo "Syntax OK\n";
    // Create an UD
   // require_once( __DIR__."/../../tests/testenv.php");
   // require_once( __DIR__."/../../ud-utilities/udutilities.php");    
    /*
    $data = [ 'nname'=>"B01000000001000000M", "stype"=>13, "tcontent"=>"<ul id=\"myList\"><li>One</li><li>two></li></ul>"];
    UD_utilities::analyseContent( $data, $captionIndexes);
    $table = new UDlist( $data);
    echo "\nTest HTML\n";
    echo $table->renderAsHTMLandJS()['content'];
    // JSON
    $data2 = [ 'nname'=>"B01000000002000000M", "stype"=>13, "tcontent"=>'{ "1":"one","2":"two", "3":"three"}'];
    UD_utilities::analyseContent( $data2, $captionIndexes);
    $table2 = new UDlist( $data2);
    echo "\nTest JSON\n";    
    echo $table2->renderAsHTMLandJS()['content'];
    */
    // Add some elements
    // Render
    echo "Test completed\n";
}  

 ?>