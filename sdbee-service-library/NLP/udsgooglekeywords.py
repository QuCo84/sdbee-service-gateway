# udsgooglecustomsearch.py -- Service to use Google Custom Search API
# search -- find pages with key words
# extract -- extract key words from text
# get -- get keywords from pages found with stem keywords

import os
import sys
from os.path import exists
import unidecode
#import tracemalloc
import json
from io import StringIO
import urllib.parse
import requests
from urllib.request import urlopen
from google.cloud import language_v1
from bs4 import BeautifulSoup
from rake_nltk import Rake
import stopwordsiso as stopwords
from yake import KeywordExtractor as Yake
#import spacy
#import pytextrank

# KeywordService class
class KeywordsService:
    sites = [ ""]
    verbose = True
    maxKeywordsPerPage = 50 #10000
    maxSearchResultPages = 1
    CSEthrottle = 4

    def __init__(self, maxKeywordsPerPage=10000, maxSearchResultPages=4, verbose = False):
        self.maxKeywordsPerPage = maxKeywordsPerPage
        self.maxSearchResultPages = maxSearchResultPages
        self.verbose = verbose
        
    def call(self, action, data) :
        switch = { "get": self.getKeywords, "search":self.findPages, "extract":self.extract}
        result = switch.get( action)(data[0], data[1], data[2], data[3])
        return result

    def extract( self, text, lang, n) :
        keywords = self.extractKeywordsWithYake( text, lang, text, source="yake", nYake=int(n), accents=True)   
        print (int(n))
        for i in range( 0, 5): 
            print( keywords[ i])
        return keywords
        
    def getKeywords(self, stem, lang, nbOfKeywords, n) :
        keywords = []
        pages = self.findPages( stem)
        stemA = stem.split( ' ')
        cert = os.path.dirname(os.path.abspath(__file__))+"/cert.pem"
        pageNum = 1
        for page in pages:
            #print( "page ", pageNum)
            pageNum += 1
            #print( keywords)
            try:
                url = page[ 'url']
                title = page[ 'title']
                if url and url.find( "edf.fr") == -1 and url.find( ".uniqlo") == -1:
                    if self.verbose: print( "Handling %s" % (url))
                    pageHTML = requests.get( url, verify=cert);
                    #pageHTML = urlopen( url).read() #cacert issue
                    helper = BeautifulSoup( pageHTML.text, features="html.parser")
                    # kill all script, style & a elements
                    for script in helper(["script", "style", "a"]):
                        #if self.verbose: print( "extracting") 
                        script.extract()    # rip it out
                    onclicks = helper.findAll('', onclick=True) 
                    if len(onclicks) > 0 and self.verbose: print( "%i onclicks" % (len( onclicks))) 
                    if ( len(onclicks) < 50):              
                        for onclickElement in onclicks: onclickElement.extract()   
                    # get text
                    pageText = helper.get_text()  
                    #if pageNum < 5: print( pageNum, pageText);    
                    source = "Yake on " + unidecode.unidecode( title).lower()             
                    keywords = self.extractKeywordsWithYake( title, lang, stemA, source=source, keep=keywords)
                    keywords = self.extractKeywordsWithYake( pageText, lang, stemA, source="yake on " + url, keep=keywords)
                    if self.verbose: print( "Handled %s" % (url))
            except ( urllib.error.HTTPError): #, ssl.SSLCertVerificationError):
                if self.verbose: print( "HTTP Error", url)
                #pass 
            except ValueError:
                if self.verbose: print( "Value Error", url)
                #pass   
            except:
                e = sys.exc_info()[1]
                if self.verbose: print( "Other Error", url, e)
                #pass 
        # Keep n keywords sorted by nb of documents where they are present and score
        results = sorted( keywords, key=lambda d: ((10/d['wordCount'])*100+100/d['mentions']+d['score'])) [:int(nbOfKeywords)] #mentions reverse=True
        return results
        
    def search(self, data) :
        print("Search was called with", data)
        
    def extractKeywords( self, text_content, language, source, keep=[]):
        #keep =  {} #[]
        keepTypes = [
            'PERSON',
            'ORGANISATION',
            'EVENT',
            'LOCATION',
            'ARTWORK',
            'CONSUMER PRODUCT'
        ]
        client = language_v1.LanguageServiceClient()
        # Available types: PLAIN_TEXT, HTML
        type_ = language_v1.Document.Type.PLAIN_TEXT

        # Optional. If not specified, the language is automatically detected.
        # For list of supported languages:
        # https://cloud.google.com/natural-language/docs/languages
        #language = "fr"
        document = {"content": text_content, "type_": type_, "language": language}

        # Available values: NONE, UTF8, UTF16, UTF32
        encoding_type = language_v1.EncodingType.UTF8

        response = client.analyze_entities(request = {'document': document, 'encoding_type': encoding_type})

        # Loop through entitites returned from the API
        safe = self.maxKeywordsPerPage
        for entity in response.entities:
            safe -= 1;
            if safe == 0: break
            #print( entity)
            keyword = unidecode.unidecode( entity.name).lower()
            entityType = format( language_v1.Entity.Type(entity.type_).name)
            mentions = entity.mentions
            mentionsCount = len( mentions)
            if entityType in keepTypes or True:
                entry = [el for el in keep if el[ 'keyword'] == keyword]
                if entry: 
                    entry[ 0][ 'mentions'] += mentionsCount
                    if ( entry[0]['type'].find( entityType) == -1) :  entry[0]['type'] += ' ' + entityType
                else : keep.append( { 'keyword':keyword, 'mentions':mentionsCount, 'source':source, 'type':entityType})
        return keep 

    def extractKeywordsWithRake( self, text_content, language, source, keep=[]):
        rake = Rake(stopwords=stopwords.stopwords(language) , max_length=4)
        rake.extract_keywords_from_text(text_content)
        rake_keyphrases = rake.get_ranked_phrases() #_with_scores() #[:TOP]
        # Loop through key phrases returned by RAKE
        keepCount = self.maxKeywordsPerPage
        for phrase in rake_keyphrases:
            keepCount -= 1;
            if keepCount == 0: break
            #print( phrase)            
            phrase = unidecode.unidecode( phrase).lower()
            entry = [el for el in keep if el[ 'keyword'] == phrase]
            if entry: 
                entry[ 0][ 'mentions'] += 1
            else : keep.append( { 'keyword':phrase, 'wordCount':len( phrase.split(' ')), 'mentions':1, 'source':source, 'type':"rake"})
        return keep 

    def extractKeywordsWithYake( self, text_content, language, stemA, source="", keep=[], nYake=15, accents=False):
        myyake = Yake(lan=language, stopwords=stopwords.stopwords(language), n=nYake) #top=100, 
        yake_keyphrases = myyake.extract_keywords(text_content)
        # Loop through key phrases returned by YAKE
        keepCount = self.maxKeywordsPerPage
        for phrase in yake_keyphrases:
            # phrase is a tuple with keyword, score
            keepCount -= 1;
            if keepCount == 0: break
            #print( phrase)
            if phrase[1] > 1 or True:
                if accents: keyword = phrase[0].lower()
                else: keyword = unidecode.unidecode( phrase[0]).lower()
                skip = False #True
                for stem in stemA: 
                    if stem in keyword: skip = False
                if skip == False: 
                    entry = [el for el in keep if el[ 'keyword'] == keyword]
                    if entry: 
                        entry[ 0][ 'mentions'] += 1
                        #update score if higher
                    else : keep.append( { 'keyword':keyword, 'wordCount':len( keyword.split(' ')), 'mentions':1, 'source':source, 'type':"yake", 'score':phrase[1]})
        return keep 
   
    def extractKeywordsWithRank( self, text_content, language, keep=[]):
        # load a french model
        nlp = spacy.load("fr_core_news_sm")
        # add pytextrank to the pipe
        nlp.add_pipe("textrank")
        doc = nlp(text_content)
        textrank_keyphrases = doc._.phrases
        # Loop through key phrases returned by RAKE
        keepCount = 1000
        for phrase in textrank_keyphrases:
            keepCount -= 1;
            if keepCount == 0: break
            if phrase.rank > 1 or True:
                keyword = unidecode.unidecode( phrase.text)
                entry = [el for el in keep if el[ 'keyword'] == keyword]
                if entry: 
                    entry[ 0][ 'mentions'] += 1 #phrase.count
                else : keep.append( { 'keyword':phrase.text, 'wordCount':len( keyword.split(' ')),'mentions':1, 'type':"textrank", 'score':phrase.rank})
        return keep

    def findPages( self, stem): 
        results = [];
        # Use Google Custom Search Engine
        motorId = "11409c8ccce8edc3d"
        APIkey =  "AIzaSyBP--VaXzYRX-4LSqdL6P_ZWpisOHuzMYk"
        cseURL = "https://www.googleapis.com/customsearch/v1?"
        cseURL += "key="+APIkey+"&cx="+motorId+"&q="+urllib.parse.quote_plus(stem)+"&num=10"  #10 is max value    
        for reqi in range( 0, self.maxSearchResultPages):
            urlStep = cseURL + "&start="+str(reqi*10+1)
            #print( urlStep)
            # 100 request per day are free, then 5$/1000
            resJSON = requests.get( urlStep)
            res = resJSON.json()
            res = res['items']
            for item in res:
                # 2DO check title for pertinence
                # 2DO check $item[ 'link'] for site
                # 2DO htmlsnippet
                # print( item)
                url = ""
                tags = item['pagemap']['metatags'][0]
                for tag in tags: 
                    if "url" in tag: url = tags[tag]
                if url: results.append( {
                    "title": item[ 'title'],
                    "url": url
                })
        return results;

        
def main():
    args = sys.argv[1:]
    os.environ["GOOGLE_APPLICATION_CREDENTIALS"] = "D:\\GitHub\\GCP\\gctest211130-567804cfadc6.json"
    if len( args) >= 3:
        # Production run
        action = args.pop(0)
        # Other args are for get: stem, lang, nb of keywords, CSEthrottle(2D0)
        service = KeywordsService()
        io = StringIO()
        json.dump(service.call( action, args),io)
        #print( io.getvalue())
    else: 
        # Autotest run
        #tracemalloc.start()
        # EDF page !working why ?
        #service = KeywordsService()   
        #cert = "D:\cert\cacert.pem"
        #url = "https://www.edf.fr/entreprises/parcours-d-orientation-de-la-transition-energetique"
        #print( "Handling %s" % (url))
        #pageHTML = requests.get( url, verify=cert);
        #print( "got page")
        #pageHTML = urlopen( url).read() #cacert issue
        #helper = BeautifulSoup( pageHTML.text, features="html.parser")
        # kill all script and style elements
        #for script in helper(["script", "style"]):
        #    script.extract()    # rip it out
        #print( "scripts removed")
        # get text
        #pageText = helper.get_text()                   
        #keywords = service.extractKeywords( pageText, 'fr', keywords)
        #print( "Handled %s" % (url))
        #exit()
        service = KeywordsService( 50, 1, False)
        text = "Les entreprises preparent la transition ecologique comme decide aux accords du COP21 a Paris en 2021 avec 136 pays"
        text = """Sources tell us that Google is acquiring Kaggle, a platform that hosts data science and machine learning 
competitions. Details about the transaction remain somewhat vague, but given that Google is hosting its Cloud 
Next conference in San Francisco this week, the official announcement could come as early as tomorrow. 
Reached by phone, Kaggle co-founder CEO Anthony Goldbloom declined to deny that the acquisition is happening. 
Google itself declined 'to comment on rumors'. Kaggle, which has about half a million data scientists on its platform, 
was founded by Goldbloom  and Ben Hamner in 2010. 
The service got an early start and even though it has a few competitors like DrivenData, TopCoder and HackerRank, 
it has managed to stay well ahead of them by focusing on its specific niche. 
The service is basically the de facto home for running data science and machine learning competitions. 
With Kaggle, Google is buying one of the largest and most active communities for data scientists - and with that, 
it will get increased mindshare in this community, too (though it already has plenty of that thanks to Tensorflow 
and other projects). Kaggle has a bit of a history with Google, too, but that's pretty recent. Earlier this month, 
Google and Kaggle teamed up to host a $100,000 machine learning competition around classifying YouTube videos. 
That competition had some deep integrations with the Google Cloud Platform, too. Our understanding is that Google 
will keep the service running - likely under its current name. While the acquisition is probably more about 
Kaggle's community than technology, Kaggle did build some interesting tools for hosting its competition 
and 'kernels', too. On Kaggle, kernels are basically the source code for analyzing data sets and developers can 
share this code on the platform (the company previously called them 'scripts'). 
Like similar competition-centric sites, Kaggle also runs a job board, too. It's unclear what Google will do with 
that part of the service. According to Crunchbase, Kaggle raised $12.5 million (though PitchBook says it's $12.75) 
since its   launch in 2010. Investors in Kaggle include Index Ventures, SV Angel, Max Levchin, Naval Ravikant,
Google chief economist Hal Varian, Khosla Ventures and Yuri Milner """
        keywords = service.extractKeywordsWithYake( text, 'en', [ 'Google', 'Goldbloom', 'Kaggle'], 'test')
        print( "%s keywords" % (len(keywords)))
        if len(keywords): print ("Test 1 OK")
        else:  print ("Test 1 KO")
        #for keyword in keywords:
        #    print( keyword)
        stem = "recycler vetements uses"
        nb = 10
        if  len( args) >= 1: stem = args[0]   
        if  len( args) >= 2: nb = args[1]
        #Check cache file
        cacheFile = os.path.dirname(os.path.abspath(__file__)) + '/../../../tmp/serviceCache/keywords_' + stem.replace( ' ', '') + '.json'
        # + '_keywordsPro.json'
        #cacheFile = cacheFile.replace( '/', '\\')
        if False and exists( cacheFile): 
            with open( cacheFile) as f:
                print( f.read())
        else:     
            print( cacheFile)
            #sites = service.findPages( stem)
            #for site in sites:
            #    print( site)      
            #keywords = []
            keywords = service.getKeywords( stem, 'fr', nb)
            if len(keywords): print ("Test 2 OK")
            else:  print ("Test 2 KO")
            #for keywi in range( 0, len( keywords)):
            #    print( keywords[ keywi]) 
            # Output JSON for Caching keywords
            jsonRep = '{ "keywords":[';
            for keywi in range( 0, len( keywords)):
                keyword = keywords[ keywi]
                #print( keyword, str( keyword[ 'score']))
                jsonRep += '{'
                jsonRep += '"keyword":"'+ keyword[ 'keyword'] +'",'
                jsonRep += '"score":"' + str( keyword['score']) + '",'
                jsonRep += '"source":"' + keyword['source'] + '"'
                jsonRep += '},'
            jsonRep = json[:-1]
            jsonRep +=']}'
            print( jsonRep)
            with open( cacheFile, 'w') as f:
                f.write( jsonRep)
            # remove credentials from array    
            os.environ.pop("GOOGLE_APPLICATION_CREDENTIALS")
            #print( unidecode.unidecode( 'Vieux Vêtements').lower())
        print( "Test completed")
        
if __name__ == "__main__":
    main()
    exit()