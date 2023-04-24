<?php
/**
 *  keywords.php -- service to get keywords
 *
 */
/* Composer
{
  "requires" : { "google/cloud-language"}
}
*/
/* UD
{
  "require" : [],
  "publish" : [ "nlp-keywords-service"]
}
*/
/** 
 * Example of use client-side in JS 
 *
 *function KEYWORDS_sample() {
 *   let params = {
 *       service:"keywords",
 *       provider: "default",
 *       action : "get",
 *       stems : {
 *       }
 *       sites : {
 *       }
 *       // Single target data recuperation
 *       dataSource : source in response
 *       dataTarget : elementName where to place data
 *       // Multiple data recuperation to a single JSON data holder
 *       dataMap : {
 *       }
 *   };
*    $$$.service( "keywords", params);
*}
*/
require_once __DIR__."/../../ud-view-model/udconstants.php"; //2DO put in a udservice.php
require_once( "udservices.php");
require VENDOR_AUTOLOAD;
use Google\Cloud\Language\LanguageClient;
use Google\Cloud\Language\V1\Document;
use Google\Cloud\Language\V1\Document\Type;
use Google\Cloud\Language\V1\LanguageServiceClient;
use Google\Cloud\Language\V1\PartOfSpeech\Tag;

class UDS_keywords extends UD_service {
    public $sites = [
        // stem: sites
    ];
    private $standardSites = [];
    public $lastError = "";
    private $languageClient="";    
    private $gcs = [];
    
    function call( $data) {            
        $r = false;
        $action = $data[ 'action'];
        $this->gcs = $data[ 'google-custom-search'];
        $this->lastResponse = "Unknown $action";
        switch ( $action) {
            case 'get' :                                           
                $r = $this->get( $data);
                break;         
            case 'analyse' :
                $r = $this->analyse( $data[ 'query']);
                break;
            case 'extract' :
                $r = $this->extract( $data[ 'query']);
                break;
        }        
        return $r;
    }

    function extract( $data) {
        $text = $data[ 'text'];
        $lang = $data[ 'lang'];
        $n = $data[ 'n'];
        $nbResults=$data[ 'nbResults'];
        $PYSERVICE="YOUR PY FUNCTION";
        $url = "{$PYSERVICE}&act=extract&text=".urlencode($text)."&lang=fr&n=".$n."&nb=".$nbResults;
        if ( TEST_ENVIRONMENT) echo $url."\n";
        $appResponse = file_get_contents( $url);
        $keywords = JSON_decode( $appResponse, true);
        //var_dump( $results);
        // Fill public attributes with results
        $this->lastResponse = LF_count( $keywords);
        $this->lastResponseRaw = [ 'keywords' => $keywords]; 
        $this->creditsConsumed = 0;
        $this->creditComment = "Global search credits";
        $this->cacheable=true;
        return true;
    }

    function analyse( $data) {
        $language = new LanguageClient( $this->getCredentials());
        /* LanguageServiceCLient// Create a new Document, add text as content and set type to PLAIN_TEXT
        $doc = (new Document())
            ->setContent($data)
            ->setType(Type::UTF_8);
            */
        $response = $language->analyzeSyntax( $data, [ 'language' => "fr-FR"]);
        $posWords = [
            'VERB' => [],
            'NOUN' => [],
            'ADJ' => [],
            'ADV' => []
        ];
        $ignore = [ "être"];
        //var_dump( $response); die();
        $tokens = $response->tokens();
        foreach ( $tokens as $token) {
            //var_dump( $token); die();
            $word = $token[ 'text'][ 'content']; //lemma'];            
            $pos = $token[ 'partOfSpeech'][ 'tag'];
            if ( in_array( $word, $ignore)) continue;
            if ( isset( $posWords[ $pos])) {
                if ( isset( $posWords[ $pos][ $word])) $posWords[ $pos][ $word]++;
                else $posWords[ $pos][ $word] = 1;
            }
        }
        // Score each category by frequency
        $wordCount = 0;
        foreach ( $posWords as $pos=>$words)  {
            arsort( $words, SORT_NUMERIC);
            $justWords = [];
            foreach( $words as $word=>$nb) $justWords[] = $word;
            $posWords[ $pos] = $justWords;
            $wordCount += LF_count( $words);
        }
        // Fill public attributes with results
        $this->lastResponse = $wordCount;
        $this->lastResponseRaw = [ 'posWords' => $posWords]; 
        $this->creditsConsumed = 0;
        $this->creditComment = "Global search credits";
        $this->cacheable=true;
        return true;
    }

    function get( $data) {
        /*
        // Trials 221031
        $cacheFile = "serviceCache/" . str_replace( '_object', '', $data[ 'target']) . ".json";
        $cache = FILE_read( 'tmp', $cacheFile);                
        if ( $cache && ( $cacheJSON = JSON_decode( $cache))) {
            $this->lastResponse = "CSV of keywords is here normally";
            $this->lastResponseRaw = [ 'keywords'=>$cacheJSON];
            $this->creditsConsumed = 1;
            $this->creditComment = "Global search credits";
            return true;
        }*/     
        $stems = $data[ 'stems'];
        $sites = $data[ 'sites'];
        $sites = ($sites) ? array_merge( $sites, $this->standardSites) : $this->standardSites;
        $nbResults = ($data[ 'nbResults']) ? $data[ 'nbResults'] : 10;
        $ngram = $data[ 'n-gram'];
        $keywordsData = [];
        $this->lastRequest = implode( ",", $stems);        
        // Loop through stems to fill keyword data
        $site = "";
        foreach ( $stems as $stemType => $stem) {
            // Find sites for stem type
            if ( isset( $sites[ $stemType])) {
                $site = $sites[ $stemType];
            }
            // Search stem (Site !used)
            //2DO array_merge
            $keywordsData = $this->searchForKeywords( $site, $stem, $nbResults, $ngram, $keywordsData);            
            if ( !$keywordsData) {
                $this->lastResponse = "ERR: keyword extraction failed";
                return false;
            }
        }
        // Process keyword data to list of keywords and keyword table format (keyword, score, source)
        $keywords = [];
        $keywordsTable = [[ 'keyword'=>"", 'score'=>"", 'source'=>""]];
        // Build work table with scores
        $work = [];
        for ($keywi=0; $keywi < LF_count( $keywordsData); $keywi++) {
            $kwData = $keywordsData[ $keywi];
            $keywords[] = $kwData[ 'keyword'];
            $score = $this->scoreKeyword( $kwData);
            $work[ $score] = [
                'keyword' => $kwData[ 'keyword'],
                'score' => $score,
                'source' => $kwData[ 'type']. " on ".$kwData[ 'source']
            ];            
        }
        // Sort and build response table
        krsort( $work);
        foreach ( $work as $wscore=>$entry) $keywordsTable[] = $entry;
        // Fill public attributes with results
        $this->lastResponse = implode(',', $keywords);
        $this->lastResponseRaw = [ "keywords"=>$keywordsTable];
        $this->creditsConsumed = 1;
        $this->creditComment = "Global search credits";
        $this->cacheable=true;
        return true;
    } // get()

    function scoreKeyword( $kwData) {
        $kwLength = LF_count( explode( ' ', $kwData[ 'keyword']));
        $score = ($kwLength -1)*100+$kwData['mentions']+(1 - $kwData[ 'score']);
        return $score;
    }
    
    function searchForKeywords( $site, $stem, $nbResults, $ngram, &$keywords) {
        $searchQuery = ($site) ? "site:{$site} {$stem}" : $stem;
        // 2DO add CSEthrottle to limit nb of CSE requests
        if ( false && TEST_ENVIRONMENT) {
            $python = "py services\\NLP\\udsgooglekeywords.py get \"{$searchQuery}\" fr 200";        
            exec( $python, $response);
            $results = JSON_decode( $response[0], true);
            $this->lastResponseRaw = $response;
            //var_dump( $results);
            return $results;
        } else {
            // $python = "python3 services/NLP/udsgooglekeywords.py get \"{$searchQuery}\" fr 200";
            /*
            * PATCH 221114 using GCP app
            */
            $PYSERVICE="YOUR PY FUNCTION";
            $url = "{$PYSERVICE}".urlencode($searchQuery)."&c=".$nbResults;
            if ( TEST_ENVIRONMENT) echo $url."\n";
            $appResponse = file_get_contents( $url);
            $results = JSON_decode( $appResponse, true);
            //var_dump( $results);
            return $results;
        }
        /*
        $this->lastError = "";
        $pages = $this->getPages( $site, $stem);
        if ( $this->lastError) return;      
        for ( $pagei=0; $pagei < LF_count( $pages); $pagei++) {
            // For each page   
            $filePath = $pages[ $pagei][ 'url'];
            if ( !$filePath) continue;
            $page = $this->getContents( $filePath);
            if ( !$page || strlen( $page) > 200000) continue;
            $this->extractKeywordsFromHTMLpage( $page, $stem, $keywords);
        }
        */
    }

    /**
     * Main fct is searchForKeywords which uses python
     * Functions below are deprecated, could ne kepy in a sperate file
     */
    
    function getContents( $filePath) {
        //if ( TEST_ENVIRONMENT) return file_get_contents( $filePath);
        //else return FILE_getContents( $filePath);
        return FILE_getContents( $filePath);
    }
    function extractKeywordsFromHTMLpage( $html, $stem, &$keywords) {
        $r = [];
        $keepTypes = [
            'PERSON',
            'ORGANISATION',
            'EVENT',
            'LOCATION',
            'ARTWORK',
            'CONSUMER PRODUCT'
        ];
        // Get page's textual content
        $pageText = HTML_stripTags( $html);
        // Keep signifcant entities
        // 'keyFile' => json_decode(file_get_contents('/path/to/keyfile.json'), true)
        // $this->languageClient
        // if ( !$this->languageClient)
        $language = new LanguageClient( $this->getCredentials());
        $response = $language->analyzeEntities( $pageText);
        foreach ($response->entities() as $entity) {
            // Extract useful data from response for this entity
            $keyword = $entity[ 'name'];
            // 2DO split & trim entity on , for keywordCandidates
            $entityType = $entity[ 'type'];
            $mentions = $entity[ 'mentions'];
            $mentionsCount = LF_count( $mentions);
            // Decide if this is a keyword
            $keep = in_array( $entityType, $keepTypes); 
            // 2DO keep each keywordCandidate
            if ($keep && !in_array( $keyword, $keywords) ) $keywords[] = $keyword;
        }
        return LF_count( $response->entities);
    } // KeywordsService->extractKeywordsFromHTMLpage()
    
    function getPages( $site, $stem) {
        /* Test calling apython library 
        $python = "py services\\NLP\\keywordsservice.py search \"$stem\"";
        exec( $python, $response);
        var_dump( $response, $python);
        $results = JSON_decode( $response[1], true);
        return $results;
        */
        // $motorId = "003109243301007698933:5fmpmbgiczy";
        $stem = urlencode( $stem);
        if ( $this->gcs) {
            $motorId = $this->gcs[ 'motor-id'];
            $APIkey = $this->gcs[ 'API-key'];
        } else {
            $motorId = "<motorID>";
            $APIkey =  "<API key>";
        }
        $url = "https://www.googleapis.com/customsearch/v1?";
        $url .= "key={$APIkey}&cx={$motorId}&q={$stem}";
        // $url = urlencode( $url);
        // echo $url."\n";
        //$searchResult = file_get_contents( $url);
        if ( !$searchResult) {
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url);
            // curl_setopt( $ch, CURLOPT_IPRESOLVE, CURLOPT_IPRESOLVE_V4);
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Accept'=>"application/json"]);
            $searchResult = curl_exec( $ch);
            $resultInfo = curl_getinfo( $ch);
            curl_close( $ch);
        }
        // var_dump( substr( $searchResult, 0, 2000));
        if ( !$searchResult) {
            var_dump( $resultInfo);
            return [];
        }    
        $searchResult = json_decode( $searchResult, true);
        if ( isset( $searchResult[ 'error'])) {
            $this->lastError = $searchResult[ 'error'][ 'message'];
            return [];
        }
        $foundItems = $searchResult[ 'items'];
        // var_dump( $foundItems);
        $results = [];
        for ( $itemi=0; $itemi < LF_count( $foundItems); $itemi++) {
            $item = $foundItems[ $itemi];
            // var_dump( $item); return [];
            // 2DO check title for pertinence
            // 2DO check $item[ 'link'] for site
            // 2DO htmlsnippet
            $results[] = [
                'title' => $item[ 'title'],
                'url'=>$item[ 'pagemap'][ 'metatags'][0][ 'og:url']
            ];
        }
        return $results;

    }
    
    function getCredentials() {
        // 
        if ( file_exists( __DIR__."/../../../../core/gctest211130-567804cfadc6.json"))
            $credentials = [
                'projectId' => "gctest211130",
                'keyFilePath' => __DIR__."/../../../../core/gctest211130-567804cfadc6.json"
            ];
        else // if ( $TEST_ENVIRONMENT) {
            $credentials = [
                'projectId' => "gctest211130",
                'keyFilePath' => 'D:\GitHub\GCP\gctest211130-567804cfadc6.json' // 'C:\Users\Quentin\Documents'
            ];
 
        /*
        if ( file_exists( "core/sdbee_gcp.json"))
            $credentials = JSON_decode(
                file_get_contents( "core/gctest211130-567804cfadc6.json"),
                true
            );
        else // if ( $TEST_ENVIRONMENT) {
            $credentials = JSON_decode(
                file_get_contents( 'C:\Users\Quentin\Documents\GitHub\GCP\gctest211130-567804cfadc6.json'),
                true
            );
        */   
        return $credentials;
    }
} // PHP class keywordsService

if ( $argv[0] && strpos( $argv[0], "keywordsservice.php") !== false)
{
    // CLI launched for tests
    echo "Syntax OK\n";
    // Create an UD
    // define( "LINKS_DIR", __DIR__."/../../../../core/dev/");
    require_once( __DIR__."/../../tests/testenv.php");
    require_once( __DIR__."/../../tests/testsoilapi.php");    
    //$LFF = new Test_dataModel();    
    // var_dump( file_get_contents( 'https://www.sd-bee.com/')); // Check access works
    $keyworder = new UDS_keywords();
    // Search test
    $test = "Test 1 - search";    
    $r = $keyworder->getPages( "", "enterprise transition ecologique");
    // var_dump( LF_count( $r)); die();
    if ( LF_count( $r)) echo "$test: OK\n";
    else { 
        echo "$test: KO ";
        echo $keyworder->lastError."\n";
    }   
    if ( true) { 
        $test = "Test 2 - Find keywords";
        $keywords  =[];
        $stem = "enterprise transition ecologique";
        $stem = "numérlogie"; 
        $stem = "recyclage des vetements de sports";    
        $stem = "coach digital webmarketing";
        //$keywords = $keyworder->searchForKeywords( "", $stem, $keywords);
        if ( !$keyworder->get( [ 'stems'=>[ 'keyword'=>$stem], 'nbResults'=>20])) {
            echo "$test: KO ";
            echo $keyworder->lastError."\n";
        } else {
            //var_dump( $keyworder->lastResponseRaw);
            $keywords = explode( ",", $keyworder->lastResponse);
            if ( LF_count( $keywords)) echo "$test: OK\n";  
            else {
                echo "$test: KO ";
                echo $keyworder->lastError."\n";        
            }
        }    
    }
    $test = "Test 3 - top verb noun etc";
    $text = "Recycler vêtements. De nombreux consommateurs sont préoccupés par l'impacte environnemental associé aux déchets textiles conventionnels.
    Pour se montrer responsable et proche de leurs clients, les enseignes, comme tout fabricant , doivent s'occuper du cycle de vie total de leurs produits.
    La mise en place de la collecte des vêtements usés dans les points de vente et leur transfert à des recycleurs de textile. Chaque point de vente signale au recycleur lorque leur 
    esppace de stockage est trois quart plein. 70 magasins ont adopté la solution.";
    $keyworder->analyse( $text);
    if ( in_array( 'recycleur', $keyworder->lastResponseRaw[ 'posWords'][ 'NOUN'])) echo "$test: OK\n"; else echo "$text: KO".print_r( $keyworder->lastResponseRaw, true);
    $test = "Test 4 - extract with Yake";
    $text = "Comment une marque de vêtements contribue à la préservation de l'environnement. Les consommateurs sont préoccupés par l'impact environnemental associé aux déchets 
    textiles conventionnels et aux vêtements usés. Pour se montrer responsable et proche de leur clients, les enseignes, comme tout fabricant, doivent s'occuper du cycle de vie total de leurs vêtements. J'ai mis en place la collecte des vêtements usés dans les points de vente et leur transfert à des recycleurs de textile. Chaque point de vente 
    signale au recycleur lorsque leur espace de stockage des vêtements usés est trois-quarts plein.
    Mis en place dans 70 magasins, la solution est plébiscité par la clientèle.";    
    $text = file_get_contents( "https://www.sd-bee.com");
    $text = "vetements uses vetements uses recycles";
    $keyworder->extract( [ 'text'=>$text, 'lang'=>"fr", 'nbResults'=>5, 'n'=>3]);
    if ( isset( $keyworder->lastResponseRaw[ 'keywords']) && LF_count($keyworder->lastResponseRaw[ 'keywords'])) echo "$test: OK\n"; else echo "$text: KO".print_r( $keyworder->lastResponseRaw, true);
    // var_dump( $keyworder->lastResponseRaw);
    echo "Test completed\n";
    
}    