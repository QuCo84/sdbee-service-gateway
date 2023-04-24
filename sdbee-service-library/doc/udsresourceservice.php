<?php
/** 
 * uddocservice.php
 *
 *   Methods to extract data from UD documents
 *
 */
 
 
class UDS_resource {

    function __construct() {
        
    }
    
    // Central function for all actions
    function call( $data)
    {
        $action = $data['action'];
        $r = "";
        switch ( $action) {
            case "getInfo" : {
                $path = $data[ 'path'];
                $r = [
                    'exists'=>file_exists( 'upload/smartdoc/media/'.$path)
                ];
                $r = JSON_encode( $r);
            } break;
        }        
        return $r;
    }
} // PHP class UDS_resource

// Auto-test
if ( $argv[0] && strpos( $argv[0], "udresourceservice.php") !== false)
{
    function nextTest() {
        global $TEST_NO, $LF, $LFF;
        switch ( $TEST_NO) {
            case 1 : // Login
                $r = $LFF->openSession( "retr1", "LudoKov!tZ", 98);
                // echo strlen( $r).substr( $r, 23000, 500);
                if (  strlen( $r) > 1000 && stripos( $r, "HomeDir")) echo "Login test : OK\n";
                else echo "Login test: KO $r\n";
                break;
            case 2 :
                $params = [
                    'action'=>"getInfo",
                    'path' => "demointro.webm",
                ];
                $service = new UDS_resource();
                $r = $service->call( $params);
                var_dump( $r);
                break;
        }
        $TEST_NO++;
    }    
    // CLI launched for tests
    echo "Syntax OK\n";
    // Create an UD
    require_once( __DIR__."/../../tests/testenv.php");
    require_once( __DIR__."/../../ud-view-model/ud_new.php");
    require_once( __DIR__."/../../ud-utilities/udutilities.php");
    require_once( __DIR__."/../../tests/testsoilapi.php");
    $LFF = new Test_dataModel();
    // require_once( __DIR__."/../ud-view-model/ud.php");    
    $TEST_NO = 1;
    while( $TEST_NO < 3) { sleep(1); nextTest();}    
    echo "Test completed\n";      
} 

?>