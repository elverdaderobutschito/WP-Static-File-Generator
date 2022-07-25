<?php

/**
 * USAGE:
 * $generator = new WPHeadlessStaticGenerator("http://localhost/wordpress/index.php/wp-json/wp/v2/", '/temp/WPHeadless/article_template2.html');
 * $generator->setDateFormat('d.m.Y');
 * $generator->setFileOwner('butsch');
 * $generator->setSavePath("/tmp/WPHeadless/Articles");
 * $generator->setTagWrap("<p>|</p>");
 * $generator->createSinglePost('39');
 * $generator->createAllPosts();
 */

/**
 * Simple WordPress Static Site Generator
 * Uses the WordPress API and generates static html files
 *  
 * @author El Butschito
 */
class WPHeadlessStaticGenerator {
    //put your code here
    private $apiUrl;
    private $templatePath;
    private $dateFormat;
    private $fileOwner;
    private $savePath;
    private $pathToFile = array();
    private $tagWrap;
    private $removeWPClasses = false;
    
    public function setWPUrl($url) {
        $this->wpUrl = $url;
    }
    
    public function setApiUrl($apiUrl) {
        if (substr($apiUrl, -1) == '/') { 
            $this->apiUrl = $apiUrl;
        } else {
            $this->apiUrl = $apiUrl . '/';
        }
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
    
    public function setTagWrap($tagWrap) {
        $this->tagWrap = $tagWrap;
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
    
    public function setRemoveWPClasses($remove) {
        if (is_bool($remove)) {
            $this->removeWPClasses = $remove;
        }
    }
    
    public function __construct($apiUrl, $templatePath) {
        $this->setApiUrl($apiUrl);
        
        $this->setTemplatePath($templatePath);
    }
    
    public function createSinglePost($id) {
        $arrPost = $this->callWPApi($this->apiUrl . 'posts/' . $id);
        
        $this->renderSinglePost($arrPost);
    }
    
    public function createAllPosts() {
        $arrPosts = $this->callWPApi($this->apiUrl . 'posts/');
        
        foreach ($arrPosts as $currPost) {
            $this->renderSinglePost($currPost);
        }
    }
    
    private function renderSinglePost($arrPost) {
        $singlePost = file_get_contents($this->templatePath);
        
        $pathToFile = $this->getSavePath() . '/' . $arrPost->slug . '.html';
        
        $this->pathToFile[] = $pathToFile;
        
        $singlePost = str_replace('###yoast_head###', $arrPost->yoast_head, $singlePost);
        $singlePost = str_replace('###title###', $arrPost->title->rendered, $singlePost);  
        $singlePost = str_replace('###content###', $arrPost->content->rendered, $singlePost);
        $singlePost = str_replace('###date###', $this->convertDate($arrPost->date), $singlePost);
        $singlePost = str_replace('###author###', $this->getAuthorName($arrPost->author), $singlePost);
        $singlePost = str_replace('###tags###', $this->getTagNames($arrPost->tags), $singlePost);
        
        if ($this->removeWPClasses == true) {
            $singlePost = preg_replace('/class=".*\b"/', '', $singlePost);
        }
       
        file_put_contents($pathToFile, $singlePost);
        
        if (!empty($this->fileOwner)) {
             chown($pathToFile, $this->fileOwner);
        }
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
    
    private function getAuthorName($id) {
        $author = $this->callWPApi($this->apiUrl . '/users/' . $id);
        
        return $author->name;
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
}
    
