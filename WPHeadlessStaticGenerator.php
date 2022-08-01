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
    
    public function __construct($apiUrl, $templatePath) {
        $this->setApiUrl($apiUrl);
        
        $this->setTemplatePath($templatePath);
                
        $this->originalDomain = parse_url($apiUrl)['host'];
    }
    
    public function setWPUrl($url) {
        $this->wpUrl = $url;
    }
    
    public function setApiUrl($apiUrl) {
        $this->apiUrl = $this->checkTrailingSlash($apiUrl);
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
    
    public function injectDataIntoTemplate($wpApiEndpoint, $dataMarkerArray) {
        $wpApiEndpoint = $this->checkTrailingSlash($wpApiEndpoint);
        $arrData = $this->callWPApi($this->apiUrl . $wpApiEndpoint);
        
        if (!is_array($arrData)) {
            $this->injectSingle($arrData, $wpApiEndpoint, $dataMarkerArray);
        } else {
            foreach ($arrData as $currData) {
                $this->injectSingle($currData, $wpApiEndpoint, $dataMarkerArray);
            }
        }
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
                if (preg_match($this->dateFormatPattern, $data)) {
                    $data = $this->convertDate($data); 
                }
            } else { //-- alternative procedure if we ask for data of another object
                $split = explode('|', $pathToEndpoint);
                $id = $this->getData($arrData, $split[0]);
                
                if (!is_array($id)) {
                    $data = $this->getDataFromId($id, $split[1], $split[2]);
                    if (preg_match($this->dateFormatPattern, $data)) {
                        $data = $this->convertDate($data); 
                    }
                } else {
                    $data = '';
                    foreach ($id as $currId){
                        $idData = $this->getDataFromId($currId, $split[1], $split[2]);
                        
                        if (preg_match($this->dateFormatPattern, $idData)) {
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
        
        $template = $this->removeUrlPatterns($template);
                
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
        
        if (!empty($parseUrl['path'])) {
            if (!file_exists($this->savePath . $parseUrl['path'])) {
                mkdir($this->savePath . $parseUrl['path']);
            }
            
            return $parseUrl['path'] . $pathInfo['basename'];
        }
    }
    
    private function removeUrlPatterns($template) {
        $html = str_get_html($template);
        
        foreach($html->find('a') as $tag) {
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
    
    private function getTagNames($arrTags) {
        $tagNames = '';
       
        foreach ($arrTags as $currTag) {
            $tag = $this->callWPApi($this->apiUrl . '/tags/' . $currTag); 
            $tagNames .= $this->wrap($tag->name, $this->tagWrap);
        }
        
        return $tagNames;
    }
    
    public function wrap($content, $wrap='') {
        $ex = explode('|', $wrap);
        
        if (count($ex) == 1 && empty($ex[0])) {
            return $content;
        }
        
        return $ex[0] . $content . $ex[1];
    }
    
    private function getDataFromId($id, $endpoint, $dataPoint) {
        $data = $this->callWPApi($this->apiUrl . $endpoint . '/' . $id);
        
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
    
