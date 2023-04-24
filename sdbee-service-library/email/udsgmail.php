<?php
/**
  * udsgmail.php
  *   Sending messages with gmail API on a service account
  */
  /*
   * TODO Will need PYthon for crypting JWT credebtials
   * so probably better off doing complete Python chain
   * https://developers.google.com/identity/protocols/oauth2/service-account?hl=en#python
   * https://developers.google.com/gmail/api/guides/sending
   * https://developers.google.com/gmail/api/quickstart/python
   */
  // require __DIR__.'/../../vendor/autoload.php'; // vendor link !working
  require __DIR__.'/../../../vendor/autoload.php';
  
class UDS_gmail {    
    static private $client = null;
    static private $scopes = [];
    
    function __construct()  {
        self::$scopes = [
            "https://mail.google.com/",
            "https://www.googleapis.com/auth/gmail.compose",
            "https://www.googleapis.com/auth/gmail.modify",
            "https://www.googleapis.com/auth/gmail.send"
        ];        

    }
    
    function getClient() {
        if ( self::$client) { return self::$client;}
        // Setup file paths and redirect URI
        // if ( !file_exists) extract from config file        
        $userId = LF_env( 'user_id');
        $tokenFile = "gmailtoken{$userId}.json";
        $tokenFull = "tmp/{$tokenFile}";
        $cache = LF_env( 'cache');
        $credentialsFull =  "../GCP/sd-bee-ddf7c76ca3a1.json";
        $checkJSON = file_get_contents( $credentialsFull);
        if ( ! JSON_decode( $checkJSON)) {
            echo "Bad JSON $checkJSON";
            die();
        }
       // $appName = ( TEST_ENVIRONMENT || $cache > 10) ? 'rSmartDoc' : 'SD bee';
        $appName = 'SD bee';
        $redirect = "https://www.sd-bee.com/webdesk//AJAX_oauth/"; //$this->makeAbsoluteURL( "webdesk//AJAX_oauth"); // Add OID or env to indicate Google
        $service = self::$scopes;
        // Setup Google API parameters

        // Build ENViromental variable for oauth callback service
        $googleOAUTH = [ 
            'tokenFile'=>$tokenFile, 
            'callerURI'=>$_SERVER['REQUEST_URI'], 
            'credentials'=>$credentialsFull,
            'appName' =>$appName,
            'service' => $service,
            'redirect'=>$redirect
        ]; 
        LF_env( 'GoogleOAUTH', $googleOAUTH);        
        
        
        // Setup client
        $client = new Google_Client();
        $client->setApplicationName( $appName);
        $client->setScopes( self::$scopes);
        $client->setAuthConfig( $credentialsFull);
        // offline access will give you both an access and refresh token so that
        // your app can refresh the access token without user interaction.
        $client->setAccessType('offline');
        // Using "consent" ensures that your application always receives a refresh token.
        // If you are not using offline access, you can omit this.
        $client->setApprovalPrompt('consent');
        $client->setPrompt('select_account consent');
        $client->setRedirectUri( $redirect);

        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.
         if (file_exists($tokenFull)) { // 2DO use FILE_
            $accessToken = json_decode(file_get_contents( $tokenFull), true);
            $client->setAccessToken( $accessToken);
        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) 
        {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken( $client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authURL = $client->createAuthUrl();
                global $LF;
                $LF->out( "<meta http-equiv=\"refresh\" content=\"0; URL={$authURL}\">", 'head');
                echo "<meta http-equiv=\"refresh\" content=\"0; URL={$authURL}\">";
                // $client->setAccessType("offline"); 2TRY
                return null;               
             }
        }
         
        self::$client = $client;
        return $client;    
    
    }

    /**
    * @param $sender string sender email address
    * @param $to string recipient email address
    * @param $subject string email subject
    * @param $messageText string email text
    * @return Google_Service_Gmail_Message
    */
    function createMessage($sender, $to, $subject, $messageText) {
        $message = new Google_Service_Gmail_Message();
    
        $rawMessageString = "From: <{$sender}>\r\n";
        $rawMessageString .= "To: <{$to}>\r\n";
        $rawMessageString .= 'Subject: =?utf-8?B?' . base64_encode($subject) . "?=\r\n";
        $rawMessageString .= "MIME-Version: 1.0\r\n";
        $rawMessageString .= "Content-Type: text/html; charset=utf-8\r\n";
        $rawMessageString .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
        $rawMessageString .= "{$messageText}\r\n";
    
        $rawMessage = strtr(base64_encode($rawMessageString), array('+' => '-', '/' => '_'));
        $message->setRaw($rawMessage);
        return $message;
    }
   
   function sendMessage($client, $from, $message) {
     try {
        $service = new Google_Service_Gmail($client);
        $message = $service->users_messages->send($from, $message);
       print 'Message with ID: ' . $message->getId() . ' sent.';
       return $message;
     } catch (Exception $e) {
       print 'An error occurred: ' . $e->getMessage();
     }
   }

} // UDS_gmail()

global $UD_justLoadedClass;
$UD_justLoadedClass = "UDS_gmail";   
 
if ( $argv[0] && strpos( $argv[0], "udsgmail.php") !== false)
{    
    // Launched with php.ini so run auto-test
    echo "Syntaxe OK\n";
    include_once __DIR__.'/../../tests/testenv.php';
    /*
    try  {
        $service = new UDS_gmail();
        $client = $service->getClient();
        if ( $client) { 
            $from = "contact@sd-bee.com";
            $msg = $service->createMessage( $from, "qcornwell@gmail.com", "Test gmail API", "A test msg");
            $service->sendMessage($client, $from, $msg);
        } else echo "No Google client<br>";
    } catch (Exception $e) {
        print "An error occurred: " . $e->getMessage();
    }*/
    echo "Test completed\n";
} // end of auto-test