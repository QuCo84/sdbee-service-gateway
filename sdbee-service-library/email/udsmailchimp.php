<?php
/** 
 * UD Service for MailChimp 
 *
 * * // 2DO Update with lastResponseRaw
 */
 
 require "emailservice.php";
 require VENDOR_AUTOLOAD;
 use \Mailjet\Resources;
 
 class UDS_mailchimp extends EmailService {
    public $lastResponse = "";
    public $lastResponseRaw = [];
    public $permanentLink = "*|ARCHIVE|*";
    public $unsubscribeLink = "*|UNSUB|*";
    public $ESPpub = "";
    private $chimpKey = "";
    private $mandrillKey = "";
    private $server = "";
    private $client = null;
    private $folderId = "";
    private $defaultSenderName = "";
    private $defaultSenderEmail = "";
    private $allowedSenders = [];
    function  __construct( $params)
    {
        // parent::__construct( $params);
        if ( isset( $params[ 'mailchimp'])) {
            $this->publicKey = $params[ 'mailchimp'][ 'chimpKey'];
            $this->privateKey = $params[ 'mailchimp'][ 'mandrillKey'];
        } else {
            // DEPRECATED
            $this->chimpKey = $params[ 'chimpKey'];
            $this->mandrillKey = $params[ 'mandrillKey'];
        }        
        $this->server = $params[ 'server'];
        $this->defaultSenderName = $params[ "senderName"];
        $this->defaultSenderEmail = $params[ "senderEmail"];
        $this->allowedSenders = $params[ "allowedSenders"];
    } 
    
 
    // Send an email 
    function send( $msg)
    {
        try {
            $mailchimp = new MailchimpTransactional\ApiClient();
            $mailchimp->setApiKey( $this->mandrillKey);
            if( isset( $msg[ 'from'])) {
                $fromEmail = $msg['from']['email'];
                $fromName = $msg[ 'from']['name'];
            } else {
                $fromEmail = $this->defaultSenderEmail;
                $fromName = $this->defaultSenderName;
            }
            // 2DO Pb with head CSS
            $message = [
                'from_email' => $fromEmail,
                'from_name' => $fromName,
                'to' => [
                    [
                        'email' => $msg['to']['email'],
                        'type' => "to"
                    ]
                ],
                'subject' => $msg['subject'],
                'text' => HTML_stripTags( $msg['body']),
                'html' => $msg['body'],
                'inline_css' => true,
            ];
            $robj = $mailchimp->messages->send(["message" => $message]);
            $r = JSON_decode( JSON_encode( $robj), true);
            $this->lastResponse = $r;
            return( $r[0][ 'status'] == "sent");
        } catch (Error $e) {
            echo 'Error: ', $e->getMessage(), "\n";
        }

    } // EmailService->send()
   
   /**
    * Get client to Mailchimp API
    *
    */
    function getClient() {

        if ( !$this->client) {
            $this->client = new MailchimpMarketing\ApiClient();
            $this->client->setConfig([
                'apiKey' => $this->chimpKey,
                'server' => $this->server,
            ]);
        }
        if ( !$this->folderId) { $this->setFolder( "SDBEE templates");}
        return $this->client;          
    }  
   
   /**
    * Return list of available templates in a folder on Mailchimp account
    * @param string $folder Template folder to read
    * @return string[] List of templates
    */
    function getTemplates( $folder = "") {
        $client = $this->getClient(); 
        $folderId = $this->folderId;
        $params = [ 'folder_id' => $folderId];
        $robj = $client->templates->list( $params);
        $r = JSON_decode( JSON_encode( $robj), true);
        $this->lastResponse = $r;
        // Build associative array from templates response (id=>name)
        $templateData = $r[ 'templates'];
        $templates = [];
        for ($templatei=0; $templatei < LF_count( $ftemplateData); $templatei++) { 
            $templates[ $templateData[ $templatei][ 'id']] =  $templateData[ $templatei][ 'name'];
        }
        return $templates;     
    } // UDC_mailchimp->getTemplates()

   /**
    * Add template in specified folder if it doesn't already exist
    * @param string $html The template content
    * @return integer Template id
    */
    function addTemplate( $html) {
        // Create unique name based on template's HTML code
        $md5 = md5( $html); 
        $templateName = "SDBEE{$md5}";
        $templates = $this->getTemplates();
        // Look up tempalte corresponding to provded HTML
        $templateId = array_search( $templateName, $templates);
        if ( $templateId === false) {
            // Template doesn't exist so create it
            $client = $this->getClient(); 
            $params = [ 'name' => $templateName, 'html'=>$html, 'folder_id'=>$this->folderId];
            $robj = $client->templates->create( $params);
            $r = JSON_decode( JSON_encode( $robj), true);
            $this->lastResponse = $r;
            $templateId = $r[ 'id'];
        }
        return $templateId;
    } // UDC_mailchimp->addTemplate()

   /**
    * Set and return the folderId to use for templates. Create the folder in Mailchimp account if needed
    * @param string $folderName Existing mailchimp client
    * @param string $folder Template folder to read
    * @return string[] List of templates
    */
    function setFolder( $folderName) {

        $client = $this->client; // !!! IMPORTANT don't use getClient() or it will loop
        $robj = $client->templateFolders->list();
        $r = JSON_decode( JSON_encode( $robj), true);
        // Build associative array from folders response (id=>name)
        $folderData = $r[ 'folders'];
        $folders = [];
        for ($folderi=0; $folderi < LF_count( $folderData); $folderi++) { 
            $folders[ $folderData[ $folderi][ 'id']] =  $folderData[ $folderi][ 'name'];
        }
        // Look up required folder
        $this->folderId = array_search( $folderName, $folders);
        if ( $this->folderId === false) {
            // Folder doesn't exist so create it
            $robj = $client->templateFolders->create( [ 'name'=>$folderName]);
            $r = JSON_decode( JSON_encode( $robj), true);
            $this->lastResponse = $r;
            $this->folderId = $r[ 'id'];            
        }
        return $this->folderId;
    } // UDC_mailchimp->setFolder()
    
    
    // Setup a campaign
    function setupCampaign( $campaign)
    {
        global $LF; // for debug       
        $client = $this->getClient();
        // Get template to use for this HTML
        $html = $campaign[ 'body'];
        $templateId = $this->addTemplate( $html);
        if ( !$templateId) { return false;}
        // Sender
        if( isset( $campaign[ 'from'])) {
            $fromEmail =  $campaign['from']['id'];
            $fromName = $campaign['from']['name'];
        } else {
            $fromEmail = $this->defaultSenderEmail;
            $fromName = $this->defaultSenderName;
        }        
        // Create campaign        
        $settings = [
            'subject_line' => $campaign[ 'subject'],
            'preview_text' => $campaign[ 'subject']. " version en ligne",
            'title' => $campaign[ 'title'],
            'reply_to' => $fromEmail,
            'from_name' => $fromName,
            'to_name' => "{first_name}",
            'auto_footer' =>true, // false
            'template_id' => $templateId             
        ];
        $robj = $client->campaigns->create(["type" => "regular", "settings"=>$settings]);
        $r = JSON_decode( JSON_encode( $robj), true);
        $this->lastResponse = $r;
        if (  $r[ 'id']) return true; else return false;

    } // Emailservice->setupCampaign()
    
   /** NOT DONE FOR MAILCHIMP FROM HERE
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
    function getState( $campaignId) {
        global $LF; // for debug
        // Get client
        $mj = new \Mailjet\Client( $this->publicKey, $this->privateKey, true, ['version' => 'v3']);
        $body = [
            'id' => $campaignId,
        ];
        $response = $mj->post(Resources::$Campaign, ['body' => $body]);
        $data = $response->getData()[0];
        $endDate = LF_date( $data->SendEndAt);
        $state = "unknown";
        if ( $endDate > LF_date( now())) $state = "Sent";
        $this->lastResponse = print_r( $data, true);
        return $state;
    }

   /**
    * Get a campaign's statistics   
    */
    function getStats( $campaignId) {
        global $LF; // for debug
        // Get client
        $mj = new \Mailjet\Client( $this->publicKey, $this->privateKey, true, ['version' => 'v3']);
        $body = [
            'SourceId' => $campaignId,
            'CounterSource' => 'Campaign',
            'CounterTiming' => 'Message',
            'CounterResolution' => 'Lifetme'
        ];
        $response = $mj->post(Resources::$Statcounters, ['body' => $body]);
        $data = $response->getData()[0];
        $this->lastResponse = print_r( $data, true);
        $stats = [];
        if ( $response->success()) {
            $perf = round( 100*$data->EventClickCount/$data->MessageSentCount, 2);
            $stats = [ "opens"=>$data->EventOpenedCount, "clicks"=>$data->EventClickCount, "performance"=>$perf];
        }
        return $stats;
    }
 } // PHP Mailjet PHP class
 
 // Auto-test
if ( $argv[0] && strpos( $argv[0], "udsmailchimp.php") !== false)
{
    // CLI launched for tests
    echo "Syntax OK\n";
    require_once __DIR__.'/../../tests/testenv.php';    
    $params = [ 'chimpKey'=> "<chimp key>", 'mandrillKey'=>"<mandrill key>", 'server'=>"us7"];
    $service = new UDS_mailchimp( $params);
    if (strtoupper(substr(PHP_OS, 0, 3)) != 'WIN') {
        $test = "Send :";
        $msg = [ 
            'from'=>[ "email"=>"noreply@sd-bee.fr", "name"=>"SDbee"], 
            'to'=>["email"=>"qcornwell@gmail.com"], 
            'subject'=>"udsmailchimp.php unit test",
            'body' => "This is a message sent by the udsmailchimp unit test."
        ];
        $r = $service->send( $msg); 
        if ( $r) echo "{$test} OK"; else echo "{$test} KO";
        $test = "Campaign :";
        $campaign = [ 
            'from'=>[ "email"=>"noreply@sd-bee.fr", "name"=>"SDbee"], 
            'subject'=>"udsmailchimp.php unit test",
            'body' => "This is a campaign set up by the udsmailchimp unit test."
        ];
        $r = $service->setupCampaign( $campaign); 
        if ( $r) echo "{$test} OK"; else echo "{$test} KO";
    }
    echo "Test completed\n";
}  
 ?>