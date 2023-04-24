<?php
/** 
 * emailservice.php
 *
 * Email service connection for Smartdocs
 *
 */
 /*
 function EmailServiceCreate( $params) {
    switch( $params['provider']) {
        case "Mailjet" :
            require_once( "udsmailjet.php");
            return new UDS_mailjet( $params);
        case "Mailchimp" :
            require_once( "udsmailchimp.php");
            return new UDS_mailchimp( $params);
        }
    } 
    return null;
 }
 */
 
 //require __DIR__.'/../../vendor/mailjet/vendor/autoload.php';
 //use \Mailjet\Resources;
 require_once  __DIR__."/../../ud-view-model/udconstants.php";
 
 
 abstract class EmailService
 {
    // public $lastResponse = "";
    
    // Central function for all actions
    function call( $data)
    {
        $action = $data['action'];
        $r = false;
        if ( $data[ 'domainForImages']) {
            // Move images to specified domain
            $data[ 'body'] = $this->moveImages( $data[ 'body'],  $data[ 'domainForImages']);
        }            
        if ( $data[ 'domainForLinks']) {
            // Move images to specified domain
            $data[ 'body'] = $this->moveLinks( $data[ 'body'],  $data[ 'domainForImages'], $data[ 'redirectMap']);
        }
        if ( isset( $data[ 'body'])) {
            // Insert provideor-specific permanent and unsubscribe links and insert publicty messages
            // 2DO test LF_env( 'adsPolicy') to see if this user accepts ads
            /* 2DO make texts for mirror and unsubscribe modifiable outside model
            $unsubcribeMessage = $data[ ''] or $this->
            $permanentLinkMessageBefore

            */
            $sdbeePub = '<span style="font-size:10px;">Ce message a été généré automatiquement avec le logiciel <a href="https://www.sd-bee.com" target="_blank" style="text-decoration: underline; color: #065c53;" rel="noopener">SD bee</a>.</span><br>';
            $sdbeePub = "";
            $html = $data[ 'body'];
            $html = str_replace( 
                [ "{UD_permanentLink}", "{UD_unsubscribeLink}", "{UD_pub}"],
                [ $this->permanentLink, $this->unsubscribeLink, $sdbeePub . $this->ESPpub . $sdbeePub],
                $html
            );
            $data[ 'body'] = $html;
        }
        /*
        if ( is_string( $data[ 'from'])) {
            // look up email in parmaters
            // set from as Name & email
        }
        */
        switch ( $action)
        {
            case "send" :
                $r = $this->send( $data);
                FILE_write( "tmp", "debugemailbody.txt", 0, $data['body']); 
                break;
            case "setupCampaign" :
                $r = $this->setupCampaign( $data);
                break;
            case "setupCampaigns" :
                if ( method_exists( $this, "setupCampaigns")) {
                    $r = $this->setupCampaigns( $data);
                } elseif ( method_exists( $this, "setupCampaign")) { 
                    $targets = explode( ',', $data[ 'contactList']);
                    $targetNames = explode( ',', $data[ 'contactName']);
                    for ( $targi=0; $targi < LF_count( $targets); $targi++) {
                        $data2 = $data;
                        $data2[ 'contactList'] = $targets[ $targi];
                        $title = $data[ 'title'];
                        $title = str_replace( '{target}', $targetNames[ $targi], $title);
                        $data2[ 'title'] = $title;
                        $w = $this->lastResponseRaw[ 'id'];
                        $r = $this->setupCampaign( $data2);
                        if ( $w) $w .= ",";
                        $this->lastResponseRaw[ 'id'] = $w . $this->lastResponseRaw[ 'id'];
                    }
                } else $r = "ERR unknown method";
                FILE_write( "tmp", "debugemailbody.txt", 0, $data['body']);   
                break;
            case "getContactListsAndSegmentation" :
            case "getCampaignState" :
                $r = $this->getState( $data);
                break;
            case "getCampaignStats" :
                if ( strtolower( $data[ 'mode']) == "averagestackedbar") {
                    $previousValuesHolder = $data[ 'historyOID'];
                    $previousValues = $this->getPreviousValues( $previousValuesHolder, $data[ 'historyPath']);
                }
                if ( method_exists( $this, "getCampaignStats")) $r = $this->getCampaignStats( $data);
                else $r = $this->getStats( $data);
                // Data processing  requests
               
                if ( $r && strtolower( $data[ 'mode']) == "averagestackedbar") {
                    // Provided values for Average stacked bar 
                    $campaignValues = $this->lastResponseRaw[ 'values'];                   
                    $graphDataset1 = [];
                    $graphDataset2 = [];
                    $graphDataset3 = [];
                    $graphDataset4 = [];                    
                    $id = $data[ 'draftId'];
                    $historyLength = $data[ 'historyLength'];
                    // Identify each entry in previousValues using the 1st set of values
                    $previousIds = $previousValues[ 0];
                    $previousIndex = ( $previousIds) ? array_search( $id, $previousIds) : false;
                    if ( $previousIndex === false) {
                        $previousIndex = -1;
                        if ( !$previousIds) $previousIds = [];
                        elseif ( LF_count( $previousIds) > $historyLength) array_pop( $previousIds);
                        array_unshift( $previousIds, $id);
                        $previousValues[ 0] = $previousIds;
                    }
                    // For each metrix ( loaded, sent, opened, clicked)
                    for ( $vali=0; $vali < LF_count( $campaignValues); $vali++) {
                        // Get previous results for this metrix
                        $previous = $previousValues[ $vali+1];
                        if ( !$previous) $previous = [];
                        // Current value
                        $current = $campaignValues[ $vali];
                        // Compute total and average values
                        $total = ( $vali==0) ? max( $previous) : $campaignValues[ $vali-1];
                        $average = array_sum( $previous)/LF_count( $previous);
                        // Save value in previous
                        if ( $previousIndex == -1) {
                            if ( LF_count( $previous) > $historyLength) array_pop( $previous);
                            array_unshift( $previous, $current);                              
                        } else {
                            $previous[ $previousIndex] = $current;
                        }
                        $previousValues[ $vali+1] = $previous;
                        // Prepare stacked bar in pourcentage ( upto average, beyond average, below average, top up to max value)
                        if ( $current >= $average) {
                            // Current value is above or in line with average
                            $graphDataset1[] = round( 100*$average/$total, 1); // within average             
                            $graphDataset2[] = round( 100*($current - $average)/$total, 1); // above average
                            $graphDataset3[] = 0;
                            $graphDataset4[] = round( 100*($total - $current)/$total, 1); // up to max value
                        } else {
                            // Current value is below average
                            $graphDataset1[] = round( 100*$current/$total, 1); // within average
                            $graphDataset2[] = 0;
                            $graphDataset3[] = round( 100*($average - $current)/$total, 1); // below average part
                            $graphDataset4[] = round( 100*($total - $average)/$total, 1); // up to max value
                            
                        }
                    }
                    $this->lastResponseRaw[ 'averageStackedBar'] = [
                        'uptoAverage' => $graphDataset1,
                        'beyondAverage' => $graphDataset2,
                        'belowAverage' => $graphDataset3,
                        'topToTotal' => $graphDataset4
                    ]; 
                    // Save previous values       
                    $this->updatePreviousValues( $previousValuesHolder, $data[ 'historyPath'], $previousValues);         
                }
            break;
        }        
        return $r;
    } // EmailService->call()
    
    function moveImages( $body, $domain, $ftpParameters = null) {
        if ( !$ftpParameters) {
            // Get FTP credentials for domain
            // Read FTP setOfVaues
            $ftpData = LF_fetchNode( "SetOfValues--".LF_getClassId( "SetOfValues")."--nname|FTP*");
            if ( !LF_count( $ftpData)) return $body;
            $json = JSON_decode( $ftpData[1][ 'tvalues'], true);
            if ( !$json) return $body;
            // Find domain FTP credentials
            $ftpParameters = $json[ $domain];
        }
        if ( !$ftpParameters) return $body;        
        // Open FTP
        if ( !FILE_FTP_connect( $domain, $ftpParameters)) return $body;
        // Find images
        $widths = ['dummy'];
        $images = HTML_getImages( $body, $widths);
        // Make a copy of each image that not on target domain
        $error = "";
        for ( $imagei=0; $imagei < LF_count( $images); $imagei++) {
            $image = $images[ $imagei];
            if ( strpos( $image, $domain)) continue;
            $imgPath = explode("/", $image);
            $imgFilename = $imgPath[LF_count($imgPath)-1];
            $imgFilenameNoAccents = LF_removeAccents( $imgFilename);
            // SVG
            if ( strpos( $imgFilename, ".svg")) {
                // Convert SVG files
                $imgFilename = $this->convertSVG( $imgFilename);
                /*
                $image = $this->convertSVG( $image);
                $imgPath = explode("/", $image);
                $imgFilename = $imgPath[LF_count($imgPath)-1];
                */
            }
            // Copy image to domain stockage
            if ( !FILE_FTP_copyTo( $image, $domain/*, $widths[ $imagei]*/)) { 
                $error .= "can't copy $image\n"; 
            } else {
                // Add to Replace image path
                // 2DO if imgFilename is array, HTML_fractionImg
                $search[] = $image;
                $replace[] = "https://{$domain}/{$imgFilenameNoAccents}";
            }            
        }
        FILE_FTP_close( $domain);
        if ( !LF_count( $replace)) return $body;
        // Replace image paths
        $newBody = str_replace( $search, $replace, $body);
        // Return modified body
        return $newBody.$error;
    }
    
    function convertSVG( $image) {
        // 2DO conversion requires imagick
        $newImage = str_replace( ".svg", ".png", $image);
        return $newImage;
    }
    
    function moveLinks( $body, $domain, $linkMap = []) {
        $links = HTML_getLinks( $body);
        // Make a copy of each image
        $error = "";
        for ( $linki=0; $linki < LF_count( $links); $linki++) {
            $link = $links[ $linki];
            foreach ( $linkMap as $originalDomain => $redirect) {                
                if ( strpos( $link, $originalDomain) === 0) {
                    // Add to search replace arrays
                    $l = urlencode( str_replace( $originalDomain, "", $link));
                    $search[] = $link;
                    $replace[] = "https://{$domain}/{$redirect}?l={$l}";
                }
            }            
        }
        if ( !LF_count( $replace)) return $body;
        // Replace image paths
        $newBody = str_replace( $search, $replace, $body);
        // Return modified body
        return $newBody;
    } // EmailService.moveLinks()
    
    function getPreviousValues( $oid, $path = "data/value") {
        // return [[77125648], [140000], [80000], [25000], [3000]];
        $data = LF_fetchNode( $oid);
        $valuesStr = $data[ 1][ 'tcontent'];
        $decoded = JSON_decode( $valuesStr, true);
        $values = ( $decoded) ? $decoded[ 'data'][ 'value'] : [];
        if ( LF_count( $values) >= 3) {   // model + 2 rows of data
            // Get data
            $output = [[], [], [], [], []];
            $keys = ["Campaign", "Loaded", "Sent", "Opened", "Clicked"];
            for ( $wi=0; $wi < LF_count( $output); $wi++) {
                for ($wj=1; $wj < LF_count( $values); $wj++) {
                    $output[ $wi][] = $values[ $wj][ $keys[ $wi]]; 
                }
            }
        } elseif ( LF_count( $data) > 1) {
            // Get element of same name from mots recent docs with same model from same directory
            // Get directory and model
            $oidA = LF_stringToOid( $oid);
            array_pop( $oidA);  // pop element
            array_pop( $oidA);            
            array_pop( $oidA);  // pop view
            array_pop( $oidA);            
            $docOID = LF_oidToString( $oidA);
            $docId = $oidA[ LF_count( $oidA) - 1];
            array_pop( $oidA); // pop doc
            array_pop( $oidA);
            $dirOID = LF_oidToString( $oidA);
            $docData = LF_fetchNode( $docOID, "* dcreated");
            $model = $docData[ 1][ 'nstyle'];
            // PATCH to get stats first time with Newsv5
            if ( $model == "A0000001NILK200032_Newsv5") $model = "A00000010DFNV00032_Newsv4";
            // Use document service to get content from same element in similar doc
            include_once( __DIR__."/../doc/udsdocservice.php");
            $params = [
                'action'=>"getMostRecentByName",
                'model' => $model,
                'dir' => $dirOID,
                'exclude' => $docId,
                'dcreated' => $docData[1][ 'dcreated'],
                'elementName'=> $data[1][ 'nlabel']
            ];
            $docService = new UDS_doc();
            $content = $docService->call( $params);
            $oldDecoded = JSON_decode( $content, true);
            $oldValues = ( $oldDecoded) ? $oldDecoded[ 'data'][ 'value'] : [];
            if ( LF_count( $oldValues) > LF_count( $values)) {
                // More values than current so update current's content
                $data = [[ 'tcontent'], ['tcontent'=>$content, 'thtml'=>""]];
                $r = LF_updateNode( $oid, $data);
                // Get data 2DO recursive or fct call
                $output = [[], [], [], [], []];
                $keys = ["Campaign", "Loaded", "Sent", "Opened", "Clicked"];
                for ( $wi=0; $wi < LF_count( $output); $wi++) {
                    for ($wj=1; $wj < LF_count( $oldValues); $wj++) {
                        $output[ $wi][] = $oldValues[ $wj][ $keys[ $wi]]; 
                    }
                }
            }                                    
        } else {
            $output = [[], [], [], [], []];        
        }
        return $output;
    }
    function updatePreviousValues( $oid, $path="data/value", $newValues) {
        $data = LF_fetchNode( $oid);        
        $content = $data[ 1][ 'tcontent'];
        $values = JSON_decode( $content, true);
        if ( !$values) return 0;
        // Insert values in table
        $keys = ["Campaign", "Loaded", "Sent", "Opened", "Clicked"];
        $table = $values[ 'data'][ 'value'];
        for ( $wi=0; $wi < LF_count( $newValues); $wi++) {
            for ($wj=0; $wj < LF_count( $newValues[ $wi]); $wj++) {
                $table[ $wj+1][$keys[ $wi]] = $newValues[ $wi][ $wj]; 
            }
        }
        $values[ 'data'][ 'value'] = $table;
        $content = JSON_encode( $values);
        $data = [[ 'tcontent'], ['tcontent'=>$content, 'thtml'=>""]];
        $r = LF_updateNode( $oid, $data);
        //file_put_contents( 'tmp/debughist', $oid."\n".print_r( $data, true));
        return $r;                           
    }
    
    function _grabNonProviderParameters( $params) {
        $this->defaultSenderName = $params[ '__all'][ "senderName"];
        $this->defaultSenderEmail = $params[ '__all'][ "senderEmail"];
        if ( isset( $params[ '__all'][ "allowedSenders"])) { $this->allowedSenders = $params[ '__all'][ "allowedSenders"];}
    }
    abstract function send( $data);
    abstract function setupCampaign( $data);
    abstract function getContactListAndSegmentation( $data);
    abstract function getState( $data);
    abstract function getStats( $data);
 /*
    // Send an email 
    function send( $msg)
    {
        $mj = new \Mailjet\Client( $this->publicKey, $this->privateKey, true, ['version' => 'v3.1']);
        $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => $msg['from']['email'],
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
        $this->lastResponse = print_r( $response->getData(), true);
        return $response->success();
    } // EmailService->send()
    
    // Setup a campaign
    function setupCampaign( $campaign)
    {
        global $LF; // for debug
        // Get client
        $mj = new \Mailjet\Client( $this->publicKey, $this->privateKey, true, ['version' => 'v3']);
        // Get list of campaigns
        $response = $mj->get(Resources::$Campaign);
        $this->lastResponse = print_r( $response->getData(), true);
        //$LF->out( "debuginfo get camps".$response->getStatus().' '.$this->lastResponse."\n");
        $resp =  $this->lastResponse;        
        //$LF->out( "Existing campaigns : ".$resp->Count."\n");
        // Setup campaign  with contact list specified        
        $body = [
            'Locale' => "fr_FR",
            'Sender' => $campaign['from']['id'],
            'SenderName' => $campaign['from']['name'],
            'SenderEmail' => $campaign['from']['email'],
            'Subject' => $campaign['subject'],
            'ContactsListID' => $campaign['contactList'],
            'Title' => $campaign['title'],
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
        if ( !$response->success()) return false;
        // Program for in 2 hours by default
        $body = [
            'Date' => date( "Y-m-d H:i:s", time()+2*3600)
        ];
        / * Scheduling disabled for tests
        // Schedule campaign
        $response = $mj->post(Resources::$CampaigndraftSchedule, ['id' => $ID, 'body' => $body]);        
        $this->lastResponse .= print_r( $response->getData(), true);
        if ( !$response->success()) return $response->success();
        if ( $response->success()) return $ID; else return "ERR:".print_r( $response->getData(), true);
        * /
        return $response->success();
    } // Emailservice->setupCampaign()
    */
   /**
    * Get Contact lists and Segmentation
    */
    /*
    function getContactListAndSegmentation() {
        global $LF; // for debug
        / * No API fct available
         * use a specific email : newsletterLists to find list ids
         * no solution fund for segmentation will need parameters
         * /
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
    */
   /**
    * Get a campaign's state  
    */
    /*
    function getCampaignState( $campaignId) {
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
*/
   /**
    * Get a campaign's statistics   
    */
    /*
    function getCampaignStats( $campaignId) {
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
    */
 } // PHP EmailService PHP class
 
 // Auto-test
if ( $argv[0] && strpos( $argv[0], "emailservice.php") !== false)
{
    // CLI launched for tests
    echo "Syntax OK\n";
    // Create an UD
    require_once( __DIR__."/../../tests/testenv.php");
    require_once( __DIR__."/../../tests/testsoilapi.php");
    //$LFF = new Test_dataModel();    
    // Open session
    //$r = $LFF->openSession( "retr1", "retr1", 133);   
//get_loaded_extensions(); //phpinfo();   
    // FTP trial
    $body = file_get_contents( "../TestImageMove.html");
    $ftpParams = [ 'hostname' => "ftp.cluster029.hosting.ovh.net", 'user' => "calculrezt", 'password'=>"9uYrSZkT8Cqa"];
    class testEmailService extends EmailService {
        function send( $data) {}
        function setupCampaign( $data) {}
        function getContactListAndSegmentation( $data) {}
        function getState( $data) {}
        function getStats( $data) {}      
    }
    $service = new testEmailService();
    $newBody = $service->moveImages( $body, "calculretraite.org", $ftpParams);
    if ( !$newBody) echo "Test moveImages: KO\n";
    else {
        echo "Test moveImages: OK\n";
        $linkMap = [ "https://www.retraite.com" => "retr.php"];
        $newBody = $service->moveLinks( $newBody, "calculretraite.org", $linkMap);
        if ( !$newBody) echo "Test moveLinks: KO\n";
        else {
            // var_dump( HTML_getImages($newBody));
            file_put_contents( "../TestImageMoveOut.html", $newBody);
            echo "Test moveLinks: OK\n";
        }
    }
    echo "Test completed\n";
}  
 ?>