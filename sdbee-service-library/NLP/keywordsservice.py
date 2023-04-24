# keywordsservice.py

import os
import sys
import json
from io import StringIO
import urllib.parse
import requests
from urllib.request import urlopen
from google.cloud import language_v1
from bs4 import BeautifulSoup

# KeywordService class
class KeywordsService:
    sites = [ ""]
    verbose = False
        
    def call(self, action, data) :
        switch = { "get": self.getKeywords, "search":self.findPages}
        return switch.get( action)(data)

        
    def getKeywords(self, stem):
        keywords = []
        pages = self.findPages( stem)
        for page in pages:
            #print( page[ 'url'], keywords)
            url = page[ 'title']
            try:
                url = page[ 'url']
                if url:
                    pageHTML = urlopen( url).read()
                    helper = BeautifulSoup( pageHTML, features="html.parser")
                    pageText = helper.get_text()
                    keywords += self.extractKeywords( pageText, 'fr')
                    if self.verbose: print( "Handled %s", url)
            except urllib.error.HTTPError:
                if self.verbose: print( "HTTP Error", url)            
            except ValueError:
                if self.verbose: print( "Value Error", url)            
        return keywords
        
    def search(self, data) :
        print("Search was called with", data)
        
    def extractKeywords( self, text_content, language):
        keep = []
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
        for entity in response.entities:
            print( entity)
            keyword = entity.name
            entityType = format( language_v1.Entity.Type(entity.type_).name)
            mentions = entity.mentions
            mentionsCount = len( mentions)
            if entityType in keepTypes and keyword not in keep:
                keep.append(keyword)
        return keep 

    def findPages( self, data):  
        stem = data[0]    
        motorId = "11409c8ccce8edc3d"
        APIkey =  "AIzaSyBP--VaXzYRX-4LSqdL6P_ZWpisOHuzMYk"
        url = "https://www.googleapis.com/customsearch/v1?"
        url += "key="+APIkey+"&cx="+motorId+"&q="+urllib.parse.quote_plus(stem)
        resJSON = requests.get( url)
        print( resJSON)
        res = resJSON.json()
        res = res['items']
        results = []
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
    if len( args):
        # Production run
        action = args.pop(0)
        service = KeywordsService()
        io = StringIO()
        json.dump(service.call( action, args),io)
        print( io.getvalue())
    else: 
        # Autotest run   
        service = KeywordsService()
        text = "Les entreprises preparent la transition ecologique comme decide aux accords du COP 21 a Paris"
        keywords = service.extractKeywords( text,'fr')
        print( "%s has %s" % (text, len(keywords)))
        if len(keywords): print ("Test 1 OK")
        else:  print ("Test 1 KO")
        for keyword in keywords:
            print( keyword)
        os.environ.pop("GOOGLE_APPLICATION_CREDENTIALS")
        print( "Test completed")
        
if __name__ == "__main__":
    main()