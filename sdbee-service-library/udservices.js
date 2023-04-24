/**
 * The UD_services JS class adds generic API functions for access to web services available to the document
 * and loads services modules for specific API functions when the requireService() method is called
 * NOT USED YET,To be loaded in initial require and remove API.serviceCall from udapi
 */
class UD_services {

    // 2DO add servicePromise
    constructor () {
        API.addFunctions( this, [ 'serviceCall', 'requireService']);
    }
   
   /**
    * Invoke a web service and place content as instructed
    * @param {string} service Name of the service 
    * @param {string} params_json JSON-coded parameters 
    * @param {object} responseMap Descibes how to map service response to DOM elements
    * @return {boolean} True if request sent
    */
    service( service, params_json, responseMap)
    {
        // 2DO Server should provide this as dependent on dataModel
        let serviceOidPattern = "SetOfValues--16--nname|{service}%20service";
        let serviceOid = serviceOidPattern.replace( "{service}", service);
        let params = params_json;
        if( typeof params_json != "object") params = JSON.parse( params_json);
        if ( !params) return false;
        params['service'] = service;
        params['token'] = "token";
        let uri = "/webdesk/"+serviceOid+"/AJAX_service/";
        let context = { dataTarget:params['dataTarget'], dataMap:params['dataMap']}; // 2DO map response field to element 
        let data = "form=INPUT_Service"+service+"&input_oid=SetOfValues--16-0&nServiceRequest="+encodeURIComponent( JSON.stringify( params));
        let me = this;
        this.ud.udajax.serverRequest( uri, "POST", data, context, me.serviceResponse);
        return true;
    } // UDapi.service()    
    serviceResponse( context, html, js) {
        // !!! No this !!!
        let me = window.ud.api;
        // 2DO Process as JSON
        let response = me.dom.udjson.parse( html);
        if ( !response) {
            me.pageBanner( "temp", html);
            return;
        }
        let banner =  "";
        if ( response.success) banner += '<span class="success">'+response.message+'</span>';
        else banner += '<span class="error">'+response.message+'</span>';
        let data = response.data;
        me.pageBanner( "temp", banner);
        let target = context.dataTarget;     
        if ( data) {
            if ( target) {
                // 2DO Place data directly in target element
                // ANd call a fct (initialise parent ?) = js
            } else if ( Object.keys( context.dataMap).length){
                let dataMap = context.dataMap;
                for ( let dataKey in dataMap) {
                    if ( typeof data[ dataKey] != "undefined") 
                        this.dom.domvalue.value( dataMap[ dataKey], data[ dataKey]);
                    else    
                        this.dom.domvalue.value( dataMap[ dataKey], this.translateTerm("No value"));               
                }                
            }
        } 
        // if js eval
    } // Uadpi.serviceResponse()
    
   /**
    * @api {JS} requireService(service,subservice) Load API calls for a particular service
    * @apiParam {string} service Name of service
    * @apiGroup Services
    *
    */
    requireService( serviceGroup, service ) {
        require()
    }

    encryptId( id, serviceName) {

    }
    decryptId( id) {
        
    }
}  