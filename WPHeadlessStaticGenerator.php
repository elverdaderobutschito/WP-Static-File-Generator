<?php

/**
 * USAGE:
 * $generator = new WPHeadlessStaticGenerator("http://localhost/wordpress/index.php/wp-json/wp/v2/", '/temp/WPHeadless/article_template2.html');
 * $generator->setDateFormat('d.m.Y');
 * $generator->setFileOwner('butsch'); (for local usage if you want to edit the static files)
 * $generator->setSavePath("/tmp/WPHeadless/Articles");
 * $generator->setTagWrap("<p>|</p>");
 * $generator->injectDataIntoTemplate('posts/', $markerArray);
 * 
 * For more info see: https://github.com/elverdaderobutschito/WP-Static-File-Generator/blob/main/README.md
 */

require('simple_html_dom.php');
/**
 * Simple WordPress Static Site Generator
 * Uses the WordPress API and generates static html files
 * 
 * Licensed under The MIT License
 *  
 * @author El Butschito
 */
class WPHeadlessStaticGenerator {
    private $apiUrl;
    private $apiEndpointTopRoute;
    private $templatePath;
    private $dateFormat;
    private $fileOwner;
    private $savePath;
    private $wrapArray;
    private $dateFormatPattern = '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/';
    private $urlPatternArray;
    private $urlReplaceArray;
    private $originalDomain;
    private $keepOriginalFileName = false;
    private $removeWPClasses = true;
    private $tidyHtmlRules;
    
    public function __construct($apiUrl, $apiEndpointTopRoute, $templatePath) {
        $this->setApiUrl($apiUrl);
        
        $this->setApiEndpointTopRoute($apiEndpointTopRoute);
        
        $this->setTemplatePath($templatePath);
                
        $this->originalDomain = parse_url($apiUrl)['host'];
    }
    
    public function setApiEndpointTopRoute($route) {
        $this->apiEndpointTopRoute = trim($this->checkTrailingSlash($route));
    }
    
    public function setApiUrl($apiUrl) {
        $this->apiUrl = trim($this->checkTrailingSlash($apiUrl));
    }
    
    public function getApiUrl() {
        return $this->apiUrl;
    }
    
    public function getTemplatePath() {
        return $this->templatePath;
    }
    
    public function setTemplatePath($path) {
        $this->templatePath = $path;
    }
    
    public function setDateFormat($dateFormat) {
        $this->dateFormat = $dateFormat;
    }
    
    public function setFileOwner($owner) {
        $this->fileOwner = $owner;
    }
    
    public function setSavePath($path) {
        if (substr($path,-1) != '/') {
            $this->savePath = $path;
        } else {
            $this->savePath = rtrim($path, '/');
        }
    }
    
    public function getPathToFile() {
        return $this->pathToFile;
    }
    
    public function setWrapArray($wrapArray) {
        if (!is_array($wrapArray)) {
            $this->wrapArray = array();
        } else {
            $this->wrapArray = $wrapArray;
        }
    }
    
    public function setDateFormatPattern($pattern) {
        $this->dateFormatPattern = '/' . $pattern . '/';
    }
    
    public function setKeepOriginalFilename($keep) {
        if (is_bool($keep)) {
            $this->keepOriginalFileName = $keep;
        }
    }
    
    public function getSavePath() {
        if (!empty($this->savePath)) {
            return $this->savePath;
        }
        
        if (!empty($this->templatePath)) {
            return dirname($this->templatePath);
        }
        
        return '';
    }
    
    public function setReplaceUrlParts($patternArray, $replaceArray) {
        $this->urlPatternArray = $patternArray;
        $this->urlReplaceArray = $replaceArray;
    }
    
    public function setTidyHtmlRules($tidyHtmlRules) {
        $this->tidyHtmlRules = $tidyHtmlRules;
    }
    
    public function injectDataIntoTemplate($wpApiEndpoint, $dataMarkerArray) {
        $wpApiEndpoint = $this->checkTrailingSlash($wpApiEndpoint);
        $arrData = $this->callWPApi($this->apiUrl . $this->apiEndpointTopRoute . $wpApiEndpoint);
        
        if (!is_array($arrData)) {
            $this->injectSingle($arrData, $wpApiEndpoint, $dataMarkerArray);
        } else {
            foreach ($arrData as $currData) {
                $this->injectSingle($currData, $wpApiEndpoint, $dataMarkerArray);
            }
        }
    }
    
    public function setRemoveWPClasses($remove) {
        $this->removeWPClasses = $remove;
    } 
    
    private function injectSingle($arrData, $endpoint, $dataMarkerArray) {
        $template = file_get_contents($this->templatePath);
        
        if (property_exists($arrData, 'slug')) {
            $pathToFile = $this->getSavePath() . $this->createFilename($arrData);
        } else {
            $pathToFile = $this->getSavePath() . '/' . $endpoint . '_' . $arrData->id . '.html'; //-- assuming all API-Data has at least an ID
        }
        
        foreach ($dataMarkerArray as $pathToEndpoint => $marker) {
            if (!preg_match('/\|/', $pathToEndpoint)) {
                $data = $this->getData($arrData, $pathToEndpoint);
                
                if (preg_match($this->dateFormatPattern, $data) && $pathToEndpoint != 'yoast_head') {
                    $data = $this->convertDate($data); 
                }
            } else { //-- alternative procedure if we ask for data of another object
                $split = explode('|', $pathToEndpoint);
                 
                $id = $this->getData($arrData, $split[0]);
               
                if (!is_array($id)) {
                    $data = $this->getDataFromId($id, $split[1], $split[2]);
                    
                    if (preg_match($this->dateFormatPattern, $data) && $pathToEndpoint != 'yoast_head') {
                        $data = $this->convertDate($data); 
                    }
                } else {
                    $data = '';
                    foreach ($id as $currId){
                        $idData = $this->getDataFromId($currId, $split[1], $split[2]);
                        
                        if (preg_match($this->dateFormatPattern, $idData) && $pathToEndpoint != 'yoast_head') {
                            $idData = $this->convertDate($idData); 
                        }
                        
                        if (array_key_exists($marker, $this->wrapArray)) {
                            $data .= $this->wrap($idData, $this->wrapArray[$marker]);
                        } else {
                            $data .= $idData;
                        }
                    }
                }
            }
            
            $template = str_replace($marker, $data, $template);
        }
        
        //-- Remove unwanted stuff from HTML
        $template = $this->tidyHtml($template);
                
        file_put_contents($pathToFile, $template);
        
        if (!empty($this->fileOwner)) {
             chown($pathToFile, $this->fileOwner);
        }
    }
    
    private function createFilename($arrData) {
        if (!$this->keepOriginalFileName) {
            return '/' . $arrData->slug . '.html';
        }
        
        $parseUrl = parse_url($arrData->link);
        $pathInfo = pathinfo($arrData->link);
        
        if (!array_key_exists('extension', $pathInfo)) {
            if (!file_exists($this->savePath . $parseUrl['path'])) {
                mkdir($this->savePath . $parseUrl['path']);
            }
            
            return $parseUrl['path'] . 'index.html';
        }
  
        $path = str_replace('/' . $pathInfo['basename'], '', $parseUrl['path']);
        
        if (!file_exists($this->savePath . $path)) {     
            mkdir($this->savePath . $path, 0777, true); 
        }
            
        return $parseUrl['path'];
    }
    
    private function changeUrls() {
        return count($this->urlPatternArray) > 0 && count($this->urlReplaceArray) > 0;
    }
    
    private function tidyHtml($template) {
        $html = str_get_html($template);
        
        if ($this->changeUrls()) {
            foreach($html->find('a') as $tag) {
                if (preg_match('/' . $this->originalDomain . '/', $tag->href)) {
                    $tag->href = preg_replace($this->urlPatternArray, $this->urlReplaceArray, $tag->href);
                }
            }

            //-- especially for yoast_head
            foreach($html->find('[content]') as $tag) {            
                if (preg_match('/' . $this->originalDomain . '/', $tag->content)) {                
                    $tag->content = preg_replace($this->urlPatternArray, $this->urlReplaceArray, $tag->content);
                }
            }

            //-- especially for yoast_head
            foreach($html->find('[href]') as $tag) {            
                if (preg_match('/' . $this->originalDomain . '/', $tag->href)) {                
                    $tag->href = preg_replace($this->urlPatternArray, $this->urlReplaceArray, $tag->href);
                }
            }

            foreach($html->find('img') as $tag) {
                if (preg_match('/' . $this->originalDomain . '/', $tag->src)) {
                    $newSrc = preg_replace($this->urlPatternArray, $this->urlReplaceArray, $tag->src);
                    $tag->srcset = preg_replace($this->urlPatternArray, $this->urlReplaceArray, $tag->srcset); 

                    //--save img files to folder 
                    $this->saveImgFiles($tag->src, $newSrc);

                    $tag->src = $newSrc;
                }
            }
            
             //-- yoast_head specific
            foreach ($html->find('script.yoast-schema-graph') as $tag) {
                $newTag = preg_replace('/<script [a-z,=,",\/\+\s,-]*>/', '', $tag->innertext);

                $newTag = preg_replace($this->urlPatternArray, $this->urlReplaceArray, $newTag);

                $newTag = str_replace('</script>', '', $newTag);

                $tag->innertext = $newTag;
            }
        }
        
        if ($this->removeWPClasses) {
            foreach ($html->find('[class|=wp]') as $tag) {                
                $tag->removeAttribute('class');
            }
        }
        
        if (is_array($this->tidyHtmlRules)) {
            foreach ($this->tidyHtmlRules as $rule) {
                foreach ($html->find($rule['search']) as $tag) {                
                    if ($rule['action'][0] == 'remove') {
                        $tag->removeAttribute($rule['action'][1]);
                    } elseif ($rule['action'][0] == 'change') {
                        $tag->setAttribute($rule['action'][1], $rule['action'][2]); 
                    }
                }
            }
        }
         
        $template = $html->__toString();
                
        $html->clear();
        unset($html);
        
        return $template;
    }
    
    private function saveImgFiles($src, $newFolder) {        
        $parseUrl = parse_url($newFolder);
        $pathInfo = pathinfo($parseUrl['path']);
        $filename = pathinfo($newFolder)['basename'];

        $createPath = $this->savePath . $pathInfo['dirname'];
        
        if (!file_exists($createPath)) {
            mkdir($createPath, 0777, true);
        }
        
        copy($src, $createPath . '/' . $filename);
    }
    
    private function getData($arrResult, $pathToEndpoint) {
        $ex = explode('->', $pathToEndpoint);

        $result = $arrResult;
        foreach ($ex as $attribute) {
           if (property_exists($result, $attribute)) {
                $result = $result->$attribute;
           }
        }

        return $result;
    }
    
    public function wrap($content, $wrap='') {
        $ex = explode('|', $wrap);
        
        if (count($ex) == 1 && empty($ex[0])) {
            return $content;
        }
        
        return $ex[0] . $content . $ex[1];
    }
    
    private function getDataFromId($id, $endpoint, $dataPoint) {
        $data = $this->callWPApi($this->apiUrl . $this->apiEndpointTopRoute . $endpoint . '/' . $id);

        if (!property_exists($data, $dataPoint)) {
            return 'No such property ' . $endpoint . '->' . $dataPoint;
        }
        
        return $data->$dataPoint;
    }
    
    private function callWPApi($url) {
        $curl = curl_init();
        
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);
        
        curl_close($curl);
        
        return json_decode($result);
    }
    
    private function convertDate($originalDate) { 
        if (empty($this->dateFormat)) {
             return $originalDate;
         }
        
        return date($this->dateFormat, strtotime($originalDate));
    }
    
    private function checkTrailingSlash($path) {
        if (substr($path, -1) == '/') { 
            return $path;
        } else {
            return $path . '/';
        }
    }
}
    
