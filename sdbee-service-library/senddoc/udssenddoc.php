<?php
/**
 *  udssenddoc.php - service to send a private link to a UD
 */
/* UD
{
  "require" : [],
  "publish" : [ "senddoc-service"]
}
*/
 class UDS_sendDoc extends UD_service {

    function call( $serviceRequest) {
        // Only  for the moment
        return $this->_sendDoc( $serviceRequest);
    }

    function _sendDoc( $serviceRequest) {
         // Doc to diffuse
         $docOid = $serviceRequest[ 'oid'];
         // Get email service parameters
         $emailServiceParams = LF_fetchNode( "SetOfValues--16--nname|email service");
         // Get recipients
         $recipients = $serviceRequest[ 'recipients'];
         if ( $docOid && $emailServiceParams /* && $recipients*/) {
            // For each recipient
            // If model or copy, create instance in Diffused doc directory
            $docData = LF_fetchNode( $docOid);
            if ( $docData[1][ 'stype'] == UD_model) {
                // Doc is a model so create an instance
                // Find temp directory
                $ud = LF_getClassId( 'UniversalDocElement');
                // Temp directory is part of standard user config
                $dirData = LF_fetchNode( "UniversalDocElement--{$ud}--nname|*_Temp");
                if ( LF_count( $dirData) < 2) {
                    $docOid = "";
                } else {
                    $tempId = $dirData[1][ 'id'];
                    $tempOid = $dirData[1][ 'oid'];
                        //echo "Temp dir = $tempId $tempOid $ud ".LF_count( $w); die();                           
                        // Create new document and copy model into it
                    $newDocData = [ [ "nname", "stype", "nstyle", "tcontent"]];
                        $newDocData[1][ 'stype'] = UD_document;
                    $newDocData[1][ 'nstyle'] = $model = $docData[1][ 'nname'];
                        $newDocData[1][ 'nname'] = UD_utilities::getContainerName();
                        $newDocData[1][ 'tcontent'] = $serviceRequest[ 'docName'];
                        // echo "$tempOid $ud ".print_r( $newDocData[ 0], true); die();
                    $docId = LF_createNode( $tempOid, (int) $ud, $newDocData);
                    $docOID = "UniversalDocElement--{$ud}-{$tempId}-{$ud}-{$docId}";
                        //echo "$model $docOID"; die();
                    $res = UD_utilities::copyModelIntoUD( $model, $docOID, null);
                    // if ($res) echo "OK"; die();
                    $docOid = $docOID;
                }
            }
            if ( $docOid) {
                // Create link to instance
                $poid = LF_stringToOid( $docOid);
                // array_pop( $poid);
                $poid = "UniversalDocElement--".implode( "-", $poid);
                LF_env( 'public_id', 31); // 2DO don't cheat
                $email = $serviceRequest[ 'to'];
                $lifeInDays = $serviceRequest[ 'lifeInDays'];
                if (!$lifeInDays) $lifeInDays = 7;
                // 2DO if known email then send normal link or signal
                $token = LF_createSingleFileAccess( $poid, $lifeInDays*24*60*60, 999, $email, 7);
                // 2DO SMS or email                           
                $invite = $serviceRequest[ 'subject'];
                $link = "https://www.sd-bee.com/webdesk/{$token}/";
                //$templateOid = $params[ 'template'];
                // $template = LF_fetchNode( $templateOid)[1]['tcontent'];
                $template = $serviceRequest[ 'body'];
                $msg = LF_substitute( $template, [ 'to'=> $to, 'link'=> $link]);
                // 2DO use email service
                // Prepare email service call
                $paramsEmail= array(
                    'from'=> 'sitemaster@rfolks.com', //LF_env('sitemaster_email'),
                    'fromName' => "SD bee",
                    'to'=> $email,
                    'subject'=> $invite,
                    'body' => $msg 
                );                                                       
                // Call email service
                // $LF->out( "Un email a été envoyé à {$email} par ".LF_env('sitemaster_email')." with $link to $poid<br>");                 
                 $r = $LF->callExt('EMAIL_send', $paramsEmail);
                 //2DO success / failure
                 $this->lastResponseRaw =$rep;
                 return true;
            } else { 
                $this->lastResponseRaw = "no doc or temp directory";
                return false;
            }
         } else { 
             $this->lastResponseRaw = "ERR - no email service or no document OID or no recipients";
             return false;
         }
    }

 }