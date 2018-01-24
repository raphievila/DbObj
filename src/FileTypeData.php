<?php
namespace raphievila\System;
use \xTags\xTags;
use \Utils\Utils;

class FileTypeData{
    static protected $x;
    static protected $usr;
    static protected $nme;
    static protected $aki;
    static protected $ut;
    protected $cls = 'holder cborder shadow4_black'; //main class
    protected $root;
    protected $url;
    protected $tfolder = 'images/icons';
    protected $folder = '';
    protected $title = '';
    protected $sa = 0;
    protected $tt = 0;
    protected $ad = 0;
    protected $strl = '12';
    protected $fs = 'Mb';
    protected $xt;
    protected $util;
    var $msg;
    protected $docTypes = array(
        'adv'=>'Advertisement',
        'bro'=>'Brochure',
        'cat'=>'Catalog',
        'dash'=>'Dashboard',
        'fly'=>'Flyer',
        'frm'=>'Agreement',
        'ord'=>'Order Form',
        'prl'=>'Price List',
        'usr'=>'User Manual'
    );

    public function __construct($arr=''){
        self::$x = new xTags();
        self::$ut = new Utils();
        self::$usr = isset($_SESSION['logged_user'])? $_SESSION['logged_user']: '';
        self::$nme = isset($_SESSION['logged_name'])? $_SESSION['logged_name'] : '';
        self::$aki = isset($_SESSION['logged_aki'])? $_SESSION['logged_aki'] : '';
        $this->set('root',WEBROOT);
        $this->set('url',self::$ut->site_url());
        $this->msg = 'No files found!';
        if(is_array($arr)){
            foreach($arr as $k=>$v){
                $this->set($k,$v);
            }
        }
        $this->xt = self::$x;
        $this->util = self::$ut;
    }
    
    public function docTypeArray(){
        return $this->docTypes;
    }

    public function setUrl(){
        return self::$ut->site_url();
    }

    public function set($what=FALSE,$str=''){
        $labels = array('class'=>'cls','tdir'=>'tfolder','download'=>'ad','tooltip'=>'tt','same'=>'sa','length'=>'strl');
        $mask = (isset($labels[$what]))? $labels[$what] : $what;
        if(!$what){
            throw new Exception('What do you want to change!');
        } else {
            $this->{$mask} = self::$ut->processInputData($str);
        }
    }
}