<?php
namespace raphievila\Connect;
use raphievila\Connect\DbObj;
use raphievila\System\FileTypeData;

/**
 * Description of DocumentTree
 * 
 * Easy file explorer for web environments. DocumentTree can retrieve list
 * of files as an array or HTML output. Support recursive support.
 * 
 * @author Rafael Vila
 * Version 1.0.0 Beta
 * 
 * Log:
 * 2018-01-17   Creating generic version -- NOT READY FOR DOWNLOAD
 * 
 */

class DirectoryTree extends \raphievila\System\FileTypeData{

    public function __construct () {
        parent::__construct();

    }

    //This is the function you need to trigger to get a directory content.
    public function getDir($asArray=FALSE){
        try{
            if(!$asArray){ return $this->searchDir(); } else { return $this->returnArray(); }
        } catch(Exception $e) {
            throw new Exception('Unable to retrieve directory! - '.$e->getMessage());
        }
    }
    
    private function processHandle($handle,$full=FALSE){
        $files = array();
        while ( false !== ( $file = readdir( $handle )) ) {
            if ( !preg_match('/^\./',$file) ) { $files[] = $file; }
            if($full && is_dir($file)){ $files[] = $file; }
        }
        return $files;
    }

    //Thanks to Gino Carlo Cortez by his blog
    //Check folder if empty in PHP
    //http://blog.ginocortez.com/check-folder-if-empty-using-php/
    //I just made a few changes to the script to fit this class.
    public function checkDir($folder){
        $files = '';
        if(is_dir($folder)){
            $handle = opendir($folder);
            if ($handle) {
                $files = $this->processHandle($handle);
                closedir($handle);
                clearstatcache();
            }
            $r = ( count($files) > 0 ) ? true : false; $this->msg = 'Directory is empty';
        } else {
            $r = false; $this->msg = 'This is not a folder!';
        }
        return $r;
    }

    //This will close the full class
    public function closeDir(){
        try{ $this->resetVars(); }
        catch(Exception $e){
            throw new Exception('Exception Occur: '.$e);
        }
    }

    public function resetVars(){
        $this->class = 'holder cborder shadow4_black';
        $this->root = WEBROOT;
        $this->url = self::$ut->site_url();
        $this->tfolder = 'images/2010/icons';
        $this->folder = '';
        $this->title = 'Files On System';
        $this->tt = 0; $this->sa = 0; $this->ad = 0; $this->strl = '12'; $this->fs = 'Mb';
    }

    public function diskStorage($filesize,$s){
        $kb = 1024;
        switch($s){
            case 'b': $cs = 8; $ab = $s; break; case 'Kb': $cs = $kb; $ab = $s; break; case 'Mb': $cs = $kb * 1000; $ab = $s; break; case 'Gb': $cs = $kb * 1000000; $ab = $s; break; case 'Tb': $cs = $kb * 1000000000; $ab = $s; break; case 'Pb': $cs = $kb * 1000000000000; $ab = $s; break; case 'Eb': $cs = $kb * 1000000000000000; $ab = $s; break; case 'Zb': $cs = $kb * 1000000000000000000; $ab = $s; break; case 'Yb': $cs = $kb * 1000000000000000000000; $ab = $s; break; case 'Bb': $cs = $kb * 1000000000000000000000000; $ab = $s; break; default: $cs = 1; $ab = ' bits';
        } return round((abs($filesize)/$cs),2).$ab;
    }

    private function searchDir(){
        $dir = $this->root.'/'.$this->folder;
        $dirurl = $this->url.'/'.$this->folder;
        $thumbroot = ($this->sa == 0) ? $this->root.'/'.$this->tfolder.'/' : $dir.'thumbs/';
        $thumburl = ($this->sa == 0) ? $this->url.'/'.$this->tfolder.'/' : $dirurl.'thumbs/';
        $tag = $this->xt; $res = '';
        try{
            if (is_dir($dir)) {
                $res .= (!empty($this->title))? '<h2>'.$this->title.'</h2>' : '';
                $dh = opendir($dir);
                if ($dh) {
                    while (($file = readdir($dh)) !== false) {
                        $info = pathinfo($dir.$file);
                        $fileExtension = (isset($info['extension']))? $info['extension'] : '';
                        $filename = ($this->sa == 0)? strtolower($fileExtension).'.png' : str_replace($fileExtension,'png',$file);
                        if(file_exists($thumbroot.$filename)){
                            $thumb = $thumburl.$filename;
                        } else {
                            $thumb = $this->url.'/'.$this->tfolder.'/general.png';
                        }

                        if($file != '.' && $file != '..' && !is_dir($file) && is_file($dir.$file) && $fileExtension != 'db' && $info['extension'] != 'htaccess' ){
                            $onlyname = str_replace('.','',$file);
                            $flatname = ucwords(str_replace('_',' ',str_replace('.'.$fileExtension,'',$file)));
                            $res .= '<div id="'.$onlyname.'" class="'.$this->cls.'" onmousemove="$(\'#del'.$onlyname.'\').css(\'display\',\'inline-block\');$(this).mouseenter(function(){$(\'#del'.$onlyname.'\').css(\'display\',\'inline-block\')}).mouseleave(function(){$(\'#del'.$onlyname.'\').css(\'display\',\'none\')});" >'."\n";
                            $delBut = $tag->tag('span','','class:deleteButton midalign,title:Delete '.$file.',onclick:deleteFile(this~\''.$this->folder.'\'~\''.$file.'\')');
                            $res .= (self::$aki > 700)? $tag->tag('div',$delBut,'id:del'.$onlyname.',style:position..absolute;display..none;margin..-5px;') : '';
                            $res .= '<div class="thumb">'."\n";
                            $ttv = ($this->tt == 1 && preg_match('/(png|jpg|jpeg|tif|tiff)/i', $info['extension']))? ' onmouseover="tooltip.show(\''.$thumb.'\');" onmouseout="tooltip.hide();"' : '';
                            $tb = (preg_match('/(png|jpg|jpeg|tif|tiff)/i', $fileExtension))? ' class="thickbox"' : '';
                            $res .= '<a href="'.$dirurl.$file.'"'.$tb.' title="'.$flatname.'" name="'.$flatname.'" target="_blank"'.$ttv.'>'."\n";
                            $res .= '<img title="'.$file.'" class="thumb_img" src="'.$thumb.'" style="width:auto; height:auto; max-width:60px; max-height:60px;" alt="'.$file.'" />'."\n";
                            $res .= '</a>'."\n";
                            $res .= '</div>'."\n";
                            $fName = str_replace('.'.$info['extension'],'',$file);
                            $dots = (strlen($fName) > $this->strl)? '...' : '';
                            $res .= '<div><small title="'.$file.'"><b>'.substr($fName,0,12).$dots.'</b></small></div>';
                            if($this->ad == 1){
                                $res .= '<div style="padding:5px 0; cursor:pointer;">'."\n";
                                $res .= '<small title="'.$file.'">Format: '.strtoupper($fileExtension).'</small>'."\n";
                                $res .= '</div>'."\n";
                                $res .= '<div>'."\n";
                                $res .= '<a href="download.php?file='.$file.'&dir='.$dir.'">';
                                $res .= '<img src="'.$this->url.'/images/2010/buttons/download_20.png" title="Download '.strtoupper($fileExtension).' file" alt="Download '.strtoupper($fileExtension).' file" />';
                                $res .= '</a>'."\n";
                                $res .= '<a onclick="previewImg(\''.$dir.$file.'\',\''.$file.'\'); sh(\'previewer\'); sh(\'blocker\');" title="View high resolution for '.$file.'">';
                                $res .= '<img src="'.$this->url.'/images/2010/buttons/image_20.png" />';
                                $res .= '</a>'."\n";
                                $res .= '</div>'."\n";
                            }
                            $filesize = $this->diskStorage(filesize($dir.$file),$this->fs);
                            $res .= '<small>'.$filesize.'</small>'."\n";
                            $res .= '</div>'."\n";					
                        }

                    }
                    $r = (!empty($res)) ? $res : 'No files found!';
                    closedir($dh);
                    clearstatcache();
                    return $r;
                }
            }
        }
        catch(Exception $e){
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }
    
    private function cleanURL($url){
        return str_replace(':///','://',str_replace(':/','://',str_replace('//','/',str_replace('///','/',str_replace('\\\\','/',$url)))));
    }
    
    private function createDirectoryListArray($files,$dir,$homeFolder){
        $fileList = array();
        foreach($files as $file){
            if(is_dir($dir.'/'.$file) && !preg_match('/^\./',$file)){
                $fileList[$file] = $this->returnDirectoryArray($homeFolder.'/'.$file.'/');
            } else {
                $fileList[] = $file;
            }
        }
        return $fileList;
    }
    
    public function returnDirectoryArray($subFolder = FALSE){
        $folder = (isset($this->folder)) ? $this->folder : '';
        $dir = ($subFolder)? $this->root.'/'.$subFolder : $this->root.'/'.$folder;
        $homeFolder = ($subFolder)? $subFolder : $folder;
        $files = array();
        $this->msg = $dir;
        try{
            if (is_dir($dir) && $dh = opendir($dir)) {
                $files = $this->processHandle($dh, TRUE);
                closedir($dh);
                clearstatcache();
            } else {
                $this->msg = $dir . ' is not a directory';
            }
        }
        catch(Exception $e){
            throw new Exception($e);
        }
        $array = $this->createDirectoryListArray($files, rtrim($dir,'/'), rtrim($homeFolder,'/'));
        return $array;
    }
    
    public function removeExtension($string){
        //$extension = substr($string,  strpos($string, '.'), strlen($string));
        return $removedExtension = preg_replace('/(\..*?$)/','',$string); //str_replace(".$extension","",$string);
    }
    
    public function createTagsFromDocumentName($string){
        $extension = substr($string,  strpos($string, '.')+1, strlen($string));
        $fileName = $this->removeExtension($string);
        $tagList = explode('_',$fileName);
        $tagCount = count($tagList);
        if($tagCount > 0){
            foreach($tagList as $key=>$tags){
                if(strpos($tags,'--')){
                    $splitTags = explode('--',$tags);
                    foreach($splitTags as $newTags){
                        $tagList[]=$newTags;
                    }
                    $tagList[$key] = str_replace('--',' ',$tags);
                } elseif(preg_match('/([a-z]{1})([A-Z]{1})/s',$tags)){
                    $splitWords = explode('_',trim(preg_replace('/([A-Z]{1})/','_$1',$tags)));
                    foreach($splitWords as $newTags){
                        if(!empty($newTags)){ $tagList[]=$newTags; }
                    }                  
                }
                if(key_exists($tags, $this->docTypes)){ $tagList[$key] = $tags.','.$this->docTypes[$tags]; }
            }
        }
        $tagList[]=$extension;
        return strtoupper(join(',',$tagList));
    }
    
    public function processDocumentTitle($string){
        $removedExtension = $this->removeExtension($string);
        $text = preg_replace("/(\-\-)|(_)/s"," ",preg_replace('/([a-z]{1})([A-Z]{1})/','$1_$2',$removedExtension));
        foreach($this->docTypes as $k=>$v){
            $text = preg_replace('/(^'.$k.')/','',strtolower($text));
            $v = '';
        }
        return trim(ucwords($text));
    }
    
    /* 
     * This function will search for specific directory and register all documents as PDF
     * format and save it into a database named `documents`.
     * 
     * REQUIRES DATABASE copy code below to create, remember to select you main schema
     * to install there.
     * 
        CREATE TABLE IF NOT EXISTS `documents` (
        `iddocuments` INT NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(45) NULL,
        `type` VARCHAR(20) NULL,
        `description` LONGTEXT NULL,
        `image_url` LONGTEXT NULL,
        `thumb_url` LONGTEXT NULL,
        `document_url` LONGTEXT NULL,
        `tags` VARCHAR NULL,
        `upload_by` VARCHAR(25) CHARACTER SET 'utf8' COLLATE 'utf8_unicode_ci' NULL DEFAULT NULL,
        `date` DATETIME NULL DEFAULT NULL,
        PRIMARY KEY (`iddocuments`),
        FULLTEXT INDEX `tags` (`tags` ASC))
        ENGINE = MyISAM
     * 
     * 
     */
    public function docSynch($documentList,$url=FALSE){
        $db = new DbObj();
        $listSQL = array();
        if(is_array($documentList)){
            foreach($documentList as $root=>$files){
                $mainUrl = (!$url)? '/'.$this->folder : $url;
                $mainUrl .= $root.'/';
                if(is_array($files) && count($files) > 0){
                    foreach($files as $file=>$info){
                        if(!is_array($info)){
                            if(preg_match('/\.pdf$|\.docx$|\.doc$/',$info)){
                                $sql = array();
                                $sql['title'] = strtoupper($this->processDocumentTitle($info));
                                $sql['document_url'] = $mainUrl.$info;
                                //$sql->root_url = $this->root.$mainUrl."hi-res";
                                if(file_exists($this->root.$mainUrl.'hi-res/'.$this->removeExtension($info).'.png')){
                                    $sql['image_url'] = $mainUrl.'hi-res/'.$this->removeExtension($info).'.png';
                                } elseif(file_exists($this->root.$mainUrl.'hi-res/'.$this->removeExtension($info).'.jpg')) {
                                    $sql['image_url'] = $mainUrl.'hi-res/'.$this->removeExtension($info).'.jpg';
                                }
                                if(file_exists($this->root.$mainUrl.'thumbs/'.$this->removeExtension($info).'.png')){
                                    $sql['thumb_url'] = $mainUrl.'thumbs/'.$this->removeExtension($info).'.png';
                                } elseif(file_exists($this->root.$mainUrl.'thumbs/'.$this->removeExtension($info).'.jpg')){
                                    $sql['thumb_url'] = $mainUrl.'thumbs/'.$this->removeExtension($info).'.jpg';
                                }
                                $sql['tags'] = $this->createTagsFromDocumentName($info);
                                $sql['type'] = strtolower(substr($sql['tags'],0,strpos($sql['tags'],',')));
                            }
                            $listSQL[] = "INSERT INTO documents ".$db->processValues($sql,'insert').";";
                        } else {
                            if(!preg_match('/^hi-res$|^thumbs$|^_notes$/',$file)){ $this->docSynch($info); }
                        }
                    }
                }
            }
        }
        echo "<h1>Total of queries to run ".count($listSQL)."</h1>";
        if(count($listSQL)){
            $truncate = $db->query("TRUNCATE TABLE `documents`;");
            if(is_numeric($truncate) && $truncate > 0){
                echo "<h2>Table Documents Reset Successfull!</h2>";
            }
            foreach($listSQL as $sq){
                try{ $qry = $db->query($sq,1); } catch(Exception $e){ $db->throw_error($e); }
                $res = (is_numeric($qry))? $qry : 0;
                if($res > 0){
                    echo "Processed SQL:<br />$sq<br />";
                }
            }
        }
    }
    
    private function returnSpecifics($handle,$dir,$dirurl,$pattern,$exclude = TRUE){
        $res = array(); $files = array();
        while ($file = readdir($handle)) {
            $info = pathinfo($this->root.$file);
            $compResult = ($exclude)? !preg_match("/$pattern/i",$info['extension']) : preg_match("/$pattern/i",$info['extension']);
            if(is_file($dir."/".$file)){ $files[] = $file; }
            if(is_file($dir."/".$file) && $compResult){ $res[] = $this->cleanURL($dirurl).'/'.$file; }
        }
        return array('res'=>$res,'files'=>$files);
    }
	
    private function returnArray(){
        $folder = (isset($this->folder)) ? $this->folder : ''; $dir = $this->root."/".$folder; $dirurl = $this->url."/".$folder; $r = '';
        try{
            if (is_dir($dir) && $dh = opendir($dir)) {
                $list = $this->returnSpecifics($dh, $dir, $dirurl, 'htaccess|db');
                $r = (isset($list['res']) && is_array($list['res']) && count($list['res']) > 0)
                    ? (object) $list['res'] 
                    : array('No files found!',$dir,$dirurl,$list['files']);
                closedir($dh); clearstatcache();
            }
        } catch(Exception $e){ throw new Exception($e); }
        return $r;
    }

    public function errorPage($type){
        $xt = self::$x;
        $self = filter_input(INPUT_SERVER,'PHP_SELF');

        $errorArray = array(
            204 => 'No Content Found',
            301 => 'Moved Permanently',
            404 => 'Page Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            407 => 'You Don\'t Have Enough Priviledges',
            500 => 'Internal Server Error'
        );

        foreach($errorArray as $e=>$msg){
            if($e === $type){
                $title = $msg;
                break;
            }
        }

        $img = $xt->img('/images/buttons/warning_60.png','class:midalign');
        $r = $xt->h1($img . $title, 'class:generror,style:font-size..40px;');
        $r .= $xt->p('Try again your request or contact administration if you think is an error.');
        $navButtons = $xt->span('Back','class:btn btn-default,onclick:history.back();');
        $navButtons .= $xt->a('Send Error','class:btn btn-warning,href:mailto..rafael@stealthproducts.com?subject='.$self.' link is broken.');
        $r .= $xt->p($navButtons);

        return $r;
    }
}