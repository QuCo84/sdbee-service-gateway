<?php
/** 
 * UD Service Email SendGrid (Trelio) 
 *
 * Email service connection for Smartdocs
 *
 * 2DO on server 
 *   composer require sendgrid/sendgrid
 *   
 */
 
require "emailservice.php";
require VENDOR_AUTOLOAD;
use SendGrid\Mail\To;
use SendGrid\Mail\Cc;
use SendGrid\Mail\Bcc;
use SendGrid\Mail\From;
use SendGrid\Mail\Content;
use SendGrid\Mail\Mail;
use SendGrid\Mail\Personalization;
use SendGrid\Mail\Subject;
use SendGrid\Mail\Header;
use SendGrid\Mail\CustomArg;
use SendGrid\Mail\SendAt;
use SendGrid\Mail\Attachment;
use SendGrid\Mail\Asm;
use SendGrid\Mail\MailSettings;
use SendGrid\Mail\BccSettings;
use SendGrid\Mail\SandBoxMode;
use SendGrid\Mail\BypassListManagement;
use SendGrid\Mail\Footer;
use SendGrid\Mail\SpamCheck;
use SendGrid\Mail\TrackingSettings;
use SendGrid\Mail\ClickTracking;
use SendGrid\Mail\OpenTracking;
use SendGrid\Mail\SubscriptionTracking;
use SendGrid\Mail\Ganalytics;
use SendGrid\Mail\ReplyTo;
 
class UDS_sendGrid extends EmailService {
    public $lastResponse = "";
    public $lastResponseRaw = [];
    public $permanentLink = "PERMA LINK TO DEFINE IN udssendgrid.php";
    public $unsubscribeLink = "UNSUBSCRIBE LINK TO DEFINE IN udssendgrid.php";
    public $ESPpub = "";
    protected $key = "";
    protected $id = 1;
    private $defaultSenderName = "";
    private $defaultSenderEmail = "";
    private $allowedSenders = [];
    private $suppressionGroupId = "";
    
    function  __construct( $params)
    {
        // parent::__construct( $params);
        // $this->provider = $account_json[ 'provider'];  
        if ( isset( $params[ 'sendGrid'])) {
            $this->apiKey = $params[ 'sendGrid']['apiKey'];
            $this->suppressionGroupId = $params[ 'sendGrid']['blacklist'];
        }        
        $this->defaultSenderName = $params[ "senderName"];
        $this->defaultSenderEmail = $params[ "senderEmail"];
        if ( isset( $params[ "allowedSenders"])) { $this->allowedSenders = $params[ "allowedSenders"];}
    } 
    
 
    // Send an email 
    function send( $data)    
    {
        $msg = $data;
        // 2DO check sender is authorised, use default elsewise
        $sender = $msg['from']['email'];
        if ( !in_array( $sender, $this->allowedSenders) && $this->defaultSenderEmail) {
            $sender = $this->defaultSenderEmail;
        }
        var_dump( $this->apiKey);
        $sg = new \SendGrid( $this->apiKey);
        $mail = new Mail();
        // Recipient
        $enveloppe = new Personalization();
        $enveloppe->addTo( new To( $msg['to']['email'], $msg['to']['name']));
        $mail->addPersonalization( $enveloppe);
        // Sender
        $mail->setFrom( new From( $sender, $msg['from']['name']));
        $mail->setSubject( new Subject( $msg[ 'subject']));
        $mail->addContent( new Content( 'text/html', $msg[ 'body']));
        $mail->addContent( new Content( 'text/text', HTML_stripTags( $msg[ 'body'])));
        try {
            // $response = $sg->client->mail()->send()->post($mail);
            $response = $sg->send( $mail);
            print $response->statusCode() . "\n";
            print $response->headers() . "\n";
            print $response->body() . "\n";
            $this->lastResponse = $response->body();
            return ( $response->statusCode() == 202);
        } catch (Exception $ex) {
            echo 'Caught exception: '.  $ex->getMessage();
        }
        return false;
    } // UDS_sendGrid->send()
    
    // Setup a campaign
    function setupCampaign( $data)
    {
        $campaign = $data;
        global $LF; // for debug
        // Get client
        $sg = new \SendGrid( $this->apiKey);
        // Setup campaign  with contact list specified    
        $senderId = $this->getVerifiedSenderId( $campaign[ 'from'][ 'email']);
        if ( !$senderId) {
            $this->lastResponse = "Non verified sender email";
            return false;
        }
        $html =  str_replace( '\"', '"', $campaign['body']);        
        $body = (object) [
            'name' => $campaign[ 'title'],
            'email_config' => (object) [
                'sender_id' => $senderId,
                'subject' => $campaign['subject'],
                'html_content' => $html,
                'plain_content' => HTML_stripTags( $html),
                'editor' => "code",
                'suppression_group_id' => (int) $this->suppressionGroupId
            ],
            'send_to' => (object) [
                'list_ids' => explode(",", $campaign['contactList']),
                // 'segment_ids' => explode( ",", $campaign['segment'])
            ],      
        ];
        try {
            $response = $sg->client->marketing()->singlesends()->post( $body);            
            // print $response->statusCode() . "\n";
            // print $response->headers() . "\n";
            // print $response->body() . "\n";
            $responseBody = JSON_decode( $response->body());
            $this->lastResponseRaw[ 'id'] = $responseBody->id."ID";             
            $this->lastResponse = $responseBody;
            return in_array( $response->statusCode(), [ 200, 201, 202]);
        } catch( Exception $ex) {
            print $response->statusCode() . "\n";
            print $response->headers() . "\n";
            print $response->body() . "\n";
            $this->lastResponse .= 'Caught exception: '.  $ex->getMessage();
            return false;
        }
        return false;
    } // UDS_sendGrid->setupCampaigns()
    
    function setupCampaigns( $data) {
       return $this->setupCampaign( $data);
    }
    
    function getVerifiedSenderId( $email) {
        $sg = new \SendGrid($this->apiKey);
        try {
            $response = $sg->client->verified_senders()->get();
            $results = JSON_decode( $response->body())->results;
            for ( $resi=0; $resi < LF_count( $results); $resi++) {
                $result = $results[ $resi];
                if ( $result->from_email == $email) return $result->id;
            }
            return 0;
        } catch (Exception $ex) {
            $this->lastResponse .= 'Caught exception: '.  $ex->getMessage();
            return 0;
        }
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
    * Get a campaign's statistics   
    */
    function getStats( $data) {
        $sg = new \SendGrid($this->apiKey);
        $query_params = json_decode('{
            "aggregated_by": "total",
            "start_date": "2022-01-01",
            "end_date": "2022-12-31",
            "timezone": "UTC",
            "page_size": 50
        }');
        $id = $data[ 'draftId'];

        try {
            $response = $sg->client->marketing()->stats()->singlesends()->_($id)->get(null, $query_params);            
            $responseBody = JSON_decode( $response->body());                      
            $statObj = $responseBody->results[0]->stats;
            $stats = [ 0, 0, 0, 0];
            $stats[0] += $statObj->delivered+$statObj->bounces+$statObj->invalid_emails;
            $stats[1] += $statObj->delivered;
            $stats[2] += $statObj->unique_opens;
            $stats[3] += $statObj->unique_clicks;
            $this->lastResponseRaw = [ "campaignIds"=>$id, "values"=>$stats];
            $this->lastResponse = "Stats OK"; 
            return in_array( $response->statusCode(), [ 200, 201, 202]);
        } catch (Exception $ex) {
            $this->lastResponse .= 'Caught exception: '.  $ex->getMessage();
            return false;
        }
    } // UDS_sendGrid->getStats()
    
    function deleteCampaign( $data) {
        $id = $data[ 'draft_id'];
        $sg = new \SendGrid($this->apiKey);
        try {
            $response = $sg->client->campaigns()->_($id)->delete();
            return in_array( $response->statusCode(), [ 200, 201, 202]);
        } catch (Exception $ex) {
            echo 'Caught exception: '.  $ex->getMessage();
        }        
    } // UDS_sendGrid->deteleCampaign()
    
 } // PHP UDS_sendGrid PHP class
 
// Auto-test
if ( $argv[0] && strpos( $argv[0], "udssendgrid.php") !== false)
{
    // CLI launched for tests
    echo "Syntax OK\n";
    require_once( __DIR__."/../../tests/testenv.php");
     
    // Test 1  = Send email
    $test = "1 - send email";
    $data = [
        'from' => [ 'name'=>"SD bee", 'email'=>"contact@sd-bee.com"],
        'to' => [ 'name'=>"QCornwell", 'email'=>"qcornwell@gmail.com"],
        'subject' => "udssendgrid AutoTest",
        'body' => "This is a <strong>test</strong> message for <strong>SendGrid</strong>."    
    ];
    // 2DO Login as demo and get params
    $params = [
        'sendGrid' => [
            'apiKey'=>"<api key>",
            'blacklist'=>"<blacklist no>"
        ],
        'senderName' => "SD bee",
        'senderEmail' => "contact@sd-bee.com"
    ];
    $sg = new UDS_sendGrid( $params);
    // $result = $sg->Send( $data);
    // if ( $result) echo "Test $test:OK\n"; else echo "Test $test:KO\n";
    $test = "Test 2 - create campaign";
    $msg = "Hi There";
    // $msg = file_get_contents( __DIR__."/../../../News2211.html");
    $data = [
        'title' => "Test campaign",
        'from' => [ 'name'=>"SD bee", 'email'=>"contact@sd-bee.com"],
        'subject' =>"News News",
        'body' => $msg,
        'contactList' => "<Test List>"
    ];
    //$result =  $sg->SetupCampaign( $data);
    //if ( $result) echo "Test $test:OK {$sg->lastResponseRaw[ 'id']}\n"; else echo "Test $test:KO {$sg->lastResponse}\n";
    $test = "3 - Stats";
    $data = [
        'draftId' => "<draft id>"
    ];
    $result =  $sg->getStats( $data);
    $result &= ( LF_count( $sg->lastResponseRaw[ 'values'])>0);
    if ( $result) echo "Test $test:OK\n"; else echo "Test $test:KO {$sg->lastResponse}\n";
    
    
    echo "Test completed\n";
    
}  

 ?>