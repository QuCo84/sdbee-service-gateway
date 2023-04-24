<?php
$json = [];
$region = $argv[1];
$functionName = $argv[0];
$gateway = "https://{$region}.cloudfunctions.net/{$functionName}";
// Get php files
$files = scandir( "./");
for ( $filei=0; $filei < count( $files); $filei++) {
    $file =$files[ $filei];
    if ( !strpos( $file, ".php") || $file == "model-service.php") continue;
    // Open each PHP file
    $fileContents = file_get_contents( $file);
    // Extract UD instructions
    $udBlock = LF_subString( $fileContents, "/* UD", "*/");
    if ( $udBlock) {
        $w = JSON_decode( $udBlock, true);
        if ( $w && count($w)) {
            foreach ( $w as $key=>$value) {
                if ( $key != "publish") continue;
                if ( is_array( $value)) {
                    foreach( $value as $key2 => $val2) {
                        $json[ $val2] = $gateway;
                    }
                } else $json[ $key] = $value;
            }
        }
    }
    // Extract provided services
}
file_put_contents( $functionName.'-services.json', JSON_encode( $json, JSON_PRETTY_PRINT));

function LF_subString( $str, $tag1, $tag2="") {
    if ($tag2 == "") $p1 = strrpos( $str, $tag1);	
    else $p1 = strpos( $str, $tag1);
    if ($p1 === false) return "";
    $p1 += strlen($tag1);
    if ($tag2 == "") $p2 = strlen($str);
    else $p2 = strpos( $str, $tag2, $p1);
    if ($p2 === false) return "";
    $r = substr( $str, $p1, $p2-$p1);
    return $r;
} // LF_subString()