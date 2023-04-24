// idea not used yet

class emailService {
    constructor () {
        API.addFunctions( this, [ 'serviceEmail']
    }
    
   /**
    * @api {JS} serviceEmail(action,campaignNameOrId,subject,contactListId,body) Call the email service
    * @apiParam {string} campaignNameOrId Name or Id of campaign
    * @apiParam {string} subject Subject field of campaign
    * @apiParam {string} action Send|Setup campaign|Stats| 
    * @apiParam {string} contactListId Id of contactl ist for campaign
    * @apiParam {string} body HTML of message
    * @apiGroup Services
    *
    */
   /**
    * Call API.requireService( 'email', 'emailService'); or in a manifest
    */
    serviceEmail( action, campaignNameOrId ) {
    }
}    