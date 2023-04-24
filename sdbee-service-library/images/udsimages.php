<?php
/**
 * udsimages.php - find images
 */


 require_once( __DIR__."/../udservices.php");

 class UDS_images extends UD_service {

    private $handler;
    
    function call( $request) {
        $r = false;
        $action = $request[ 'action'];
        $provider = "shutterstock";
        $serviceClass = "UDS_".$provider;
        // include        
        $handler = new $serviceClass( $this->params);
        switch ( $action) {
            case 'search' :   
                $query = $request[ 'query']; 
                $imageType = ( $request[ 'imageType']) ? $request[ 'imageType'] : "photo";
                // $peopleNb // add params            
                $r = $handler->search( $query);
                $this->lastResponseRaw = $handler->lastResponseRaw;
                break;   
            case 'licence' :
                $imageID = $request[ 'imageId'];
                $r = $handler->licence( $imageID);
                $this->lastResponseRaw = $handler->lastResponseRaw;
                break;
        }

        

        return $r;
    }

 }


 class UDS_shutterstock extends UDS_images {
   
    public $lastError = "";    
    private $SHUTTERSTOCK_API_TOKEN = "v2/ZmlYaTR3cEtkYnNHNnhHRWRRZGlmd1dUU3Z6OHRUbUgvMzUxNDk3NTYxL2N1c3RvbWVyLzQvWXlLRnpGSWpNTXBXM1NWekE0d1ZYMEUyenRoY25JczV2UFJFZFNMaTh1X1l2TjZPdTJNMHNqX21RT3ItZXVjamV1WVFfbHNpQ0xjbHR4dzVJb2l0b255aG1hXzNGMkJzcVBHTHp4MllBQkstUlBIbm9BdjliQmZ3TWo5QTJVRlJITWxYYUJfY29lWGdDM1MtT0E1MXZqMUtoenFJWE5fMVJQZVNaS1JnN0RmSV9Kbk5UVWZIZ2xZWlRGbTgxaEpDTVNBeERUaHBmU3BJUnVRLS1rdEJzQS90TjNUSXg2di1DQXJjOHR4dnFsdTBB";

    
    function search( $query) {
        $queryFields = [
        "query" => $query,
        "image_type" => "photo",
        //"orientation" => "vertical",
        //"people_number" => 3
        ];

        $options = [
            CURLOPT_URL => "https://api.shutterstock.com/v2/images/search?" . http_build_query($queryFields),
            CURLOPT_USERAGENT => "php/curl",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->SHUTTERSTOCK_API_TOKEN}"
            ],
            CURLOPT_RETURNTRANSFER => 1
        ];
        /* MULTIPLE SEARCH VERSION
        $queryFields = [
            [
                "query" => $query,
                "image_type" => "photo",
                //"orientation" => "vertical",
                //"people_number" => 3
            ],
            [
                query" => $query,
                "image_type" => "photo",
                //"orientation" => "vertical",
                "people_number" => 0
            ]
        ];
        $options = [
            CURLOPT_URL => "https://api.shutterstock.com/v2/images/search" . http_build_query($queryFields),
            CURLOPT_USERAGENT => "php/curl",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->SHUTTERSTOCK_API_TOKEN}",
                "Content-Type: application/json"
            ],
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => JSON_encode($queryFields),
            CURLOPT_RETURNTRANSFER => 1
        ];
        */
        $handle = curl_init();
        curl_setopt_array($handle, $options);
        $response = curl_exec($handle);
        curl_close($handle);
        $decodedResponse = json_decode($response, true);
        //print_r($decodedResponse);
        $images = [];
        for ( $imagei=0; $imagei < LF_count( $decodedResponse['data']); $imagei++) {
            $images[] = $decodedResponse['data'][ $imagei]['assets']['preview_1500']['url'];
        }
        // Search id & first id
        $searchId = $decodedResponse['search_id'];
        $firstImage = $decodedResponse['data'][0]['assets']['preview_1500']['url']; // 2DO could be preview_1000 or 1500
        $this->lastResponse = $firstImage;
        $this->lastResponseRaw = [ 'images' => $images, 'details'=> $decodedResponse[ 'data']];
        return true;
    }

    function licence( $imageIdOrURL) {

        // Get id
        $imageId = $imageIdOrURL;
        if ( strpos( $imageIdOrURL, "https") === 0) {
           /*
            * Example of Shutterstock URL
            * https:\/\/image.shutterstock.com\/display_pic_with_logo\/301518489\/1717280833\/
            * stock-photo-businesspeople-on-city-background-and-social-network-interface-and-glowing-human-resource-concept-1717280833.jpg
            */
             // Get last part fo path
            $urlParts = explode( '/', $imageIdOrURL);
            // Seperate file name & extension
            $fileNameParts = explode( '.', $urlParts[ LF_count( $urlParts)-1]);
            // Split file name elements
            $imageNameParts = explode( '-', $fileNameParts[0]);
            // Id is last part of image name
            $imageId = $imageNameParts[ LF_count( $imageNameParts) - 1];
        }
        // Get subscription id
        $options = [
        CURLOPT_URL => "https://api.shutterstock.com/v2/user/subscriptions",
        CURLOPT_USERAGENT => "php/curl",
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $SHUTTERSTOCK_API_TOKEN"
        ],
        CURLOPT_RETURNTRANSFER => 1
        ];
        $handle = curl_init();
        curl_setopt_array($handle, $options);
        $response = curl_exec($handle);
        curl_close($handle);
        $decodedResponse = json_decode($response, true);
        $myData = $decodedResponse[ 'data'][ 0];
        $subscriptionId = $myData[ 'id'];
        $downloadsLeft = $myData[ 'downloads_left'];
        print_r( $decodedResponse); print_r($subscriptionsId); 

        //Licence 1st image        
        $body = [
            "images" => [
                [
                    "image_id" => $imageId,
                    "subscription_id" => $subscriptionId,
                    "price"=> 12.50,
                    "metadata"=> [
                        "customer_id"=> "12345"
                    ]
                ]
            ]
        ];
        $encodedBody = json_encode($body);
        $options = [
        CURLOPT_URL => "https://api.shutterstock.com/v2/images/licenses",
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $encodedBody,
        CURLOPT_USERAGENT => "php/curl",
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $SHUTTERSTOCK_API_TOKEN",
            "Content-Type: application/json"
        ],
        CURLOPT_RETURNTRANSFER => 1
        ];
        if ( $licence) {
            $handle = curl_init();
            curl_setopt_array($handle, $options);
            $response = curl_exec($handle);
            curl_close($handle);
            $decodedResponse = json_decode($response, true);
            $download = $decodedResponse[ 'data'][ 'download'];
            // 2DO grab download and save in user's FTP space and return saved URL
            $this->lastResponse = $download;
            $this->lastResponseRaw = $decodedResponse[ 'data'];
            print_r($decodedResponse);
            // Consume a credit
            if ( $this->throttle && $this->throttleId)
                $this->throttle->consume( $this->throttleId, 1, "Licenced image {$imageId} from Shutterstock");
        }
    }
}

if ( $argv[0] && strpos( $argv[0], "udsimages.php") !== false)
{
    // CLI launched for tests
    echo "Syntax OK\n";
    // Create an UD
    // define( "LINKS_DIR", __DIR__."/../../../../core/dev/");
    require_once( __DIR__."/../../tests/testenv.php");
    require_once( __DIR__."/../../tests/testsoilapi.php");
    $LFF = new Test_dataModel();
    //require_once( __DIR__."/../udservices.php");
    // Launched with php.ini so run auto-test
    function nextTest( $services) {
        global $TEST_NO, $LF, $LFF;
        switch ( $TEST_NO) {
            case 1 : // Login
                $r = $LFF->openSession( "demo", "demo", 133);
                // echo strlen( $r).substr( $r, 23000, 500);
                if (  strlen( $r) > 1000 && stripos( $r, "Autotest")) echo "Login test : OK\n";
                else echo "Login test: KO\n";
                break;
            case 2 :
                $test = "2 - search for images";
                $serviceRequest = [
                    'service' => "images",
                    'provider' => "default",
                    'action'=> "search",
                    'query'=> "recycler vêtements" //AI in marketing",
                ];
                $r = $services->do( $serviceRequest);
                if (  LF_count( $r[ 'data'][ 'images'])) echo "Test $test : OK\n";
                else echo "Login test: KO" . print_r( $r, true) . "\n";
                break;            
            case 3 :
                break;
        }
        $TEST_NO++;
    }
    $TEST_NO = 1;
    $services = new UD_services();
    while( $TEST_NO < 3) { sleep(1); nextTest( $services);}
    /*
    $shutter = new UDS_images();
    $r = $shutter->call( $serviceRequest);
    print_r( $shutter->lastResponseRaw);   
    */
    echo "Test completed\n";
    
}    