<?php



$json = [];
// Get php files
$files = scandir( "./");
for ( $filei=0; $filei < count( $files); $filei++) {
    $file =$files[ $filei];
    if ( !strpos( $file, ".php") || $file == "model-service.php") continue;
    // Open each PHP file
    $fileContents = file_get_contents( $file);
    // Extract composer instructions
    $composerBlock = LF_subString( $fileContents, "/* Composer", "*/");
    if ( $composerBlock) {
        $w = JSON_decode( $composerBlock, true);
        if ( $w && count($w)) {
            foreach ( $w as $key=>$value) {
                if ( is_array( $value)) {
                    foreach( $value as $key2 => $val2) {
                        $json[ $key][ $key2] = $val2;
                    }
                } else $json[ $key] = $value;
            }
        }
    }
    // Extract UD instructions
    $udBlock = LF_subString( $fileContents, "/* UD", "*/");
    if ( $udBlock) {
        $w = JSON_decode( $udBlock, true);
        if ( $w && count($w)) {
            foreach ( $w as $key=>$value) {
                if ( $key != "require") continue;
                if ( is_array( $value)) {
                    foreach( $value as $val2) {
                        // Copy local files to deployment directory
                        $fileParts = explode( '/', $val2);
                        $filename = $fileParts[ count( $fileparts) - 1];
                        $c = file_get_contents( __DIR__.'/../sdbee-service-library/'.$val2);
                        file_put_contents( $filename, $c);
                    }
                }
            }
        }
    }
}
file_put_contents( 'composer.json', JSON_encode( $json, JSON_PRETTY_PRINT));



// Write to composer.json file




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