/**
 * udservicesecurity.js - -keep passwords safe
 */
 const crypto = require('crypto');
 
 class UD_serviceSecurity {

    algorithm;

    constructor() {
        this.algorithm = 'aes-256-cbc'; //Using AES encryption
        this.key = crypto.randomBytes(32);
    }

    encrypt( value, serviceTag, userTag) {
        let iv = serviceTag+userTag;
        let cipher = crypto.createCipheriv(this.algorithm, Buffer.from( this.key), iv);
        let encrypted = cipher.update(value);
        encrypted = Buffer.concat([encrypted, cipher.final()]);
        return { iv: iv.toString('hex'), encryptedData: encrypted.toString('hex') };     
    }

    decrypt( crypted, serviceTag, userTag) {
        let iv = Buffer.from( serviceTag+userTag); // Buffer.from(text.iv, 'hex');
        let encryptedText = Buffer.from(crypted, 'hex');
        let decipher = crypto.createDecipheriv(this.algorithm, Buffer.from( this.key), iv);
        let decrypted = decipher.update(encryptedText);
        decrypted = Buffer.concat([decrypted, decipher.final()]);
        return decrypted.toString();
    }

 } // JS class UD_serviceSecurity

 if ( typeof process == 'object') {    
    // Testing under node.js 
    module.exports = { UDJ_serviceSecurity:UD_serviceSecurity};
    if ( typeof global.JSDOM == "undefined" && typeof window == "undefined" && typeof ModuleUnderTest == "undefined") {
        let sec = new UD_serviceSecurity();
        if ( typeof process.argv[2] == "undefined") console.log( "Please provide 3 args : text to code, service tag and user tag");
        else {
            let enc = sec.encrypt( process.argv[2], process.argv[3], process.argv[4]);
            //console.log( enc, sec.algorithm);
            let dec = sec.decrypt( enc.encryptedData, process.argv[3], process.argv[4]);
            //console.log( dec);
            if ( dec == process.argv[2]) console.log( "Test : OK"); else "Test KO";
        }
        console.log( "Test completed"); 
    }    
}