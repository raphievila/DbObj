<?php
namespace raphievila\Connect;
use \xTags\xTags;
use raphievila\Directory\DirectoryTree;
/**
 * Description of KiDbObj original dbObj by https://github.com/raphievila
 * 
 * The intension of this class is to facilitate developers to connect to MySQL
 * Service. Developers are responsible to filter values sent to database to
 * prevent injection. If method DirectoryTree::processValues() is used, the values
 * are filtered using MySQLi::real_escape_string() function.
 * 
 * @author Rafael Vila
 * Version 1.0.0 RC
 * 
 * Log:
 * 2018-01-17   Creating generic version -- NOT READY FOR DOWNLOAD
 * 2016-09-20   Fixed an error where MySQLi object was been disconnected inside
 *              the dbObj::paginator() method, which consequently throws a Warning
 *              when dbObj::disconnect method was called outsite the class.
 *              | Catch and fixed by Rafael Vila
 * 2016-02-05 	Removing auto filtering of none breakable spaces | Rafael Vila
 * 2015-11-09   Need to add option to custom filter values line #468, the bug was
 *              causing unwanted characters modification from entries that do not
 *              requires html tag intact. | Rafael Vila
 */

/*===========================ENVIRONMENT VARIABLES============================*/
$dbservername = filter_input(INPUT_SERVER,'SERVER_NAME',FILTER_SANITIZE_STRING);
$dbroot = filter_input(INPUT_SERVER,'DOCUMENT_ROOT',FILTER_SANITIZE_STRING);
$dbscript = filter_input(INPUT_SERVER,'SCRIPT_NAME',FILTER_SANITIZE_STRING);
$dbrequesturi = filter_input(INPUT_SERVER,'REQUEST_URI',FILTER_SANITIZE_STRING);
/*============================================================================*/

/*=============================ADMIN/LOG SETUP================================*/
$rootuser = 'adminuser'; //MAIN DEVELOPER USER
$rootaki = '777'; //AKI IS BASED ON APACHE GRANT NUMBERING
$user = (isset($_SESSION['logged_user']))? $_SESSION['logged_user'] : NULL;
$uaki = (isset($_SESSION['logged_auth']))? $_SESSION['logged_auth'] : NULL;
/*============================================================================*/

/*============================SETTING UP GLOBALS==============================*/
DEFINE('DBSERVERNAME',$dbservername);
DEFINE('DBROOT',$dbroot);
DEFINE('DBSCRIPT',$dbscript);
DEFINE('DBURI',$dbrequesturi);
DEFINE('ROOTUSER',$rootuser);
DEFINE('ROOTAKI',$rootaki);
DEFINE('CURRENTUSER',$user);
DEFINE('CURRENTKEY',$uaki);
/*============================================================================*/

class KiDbObj{
    static private $mainschema = 'MAIN SCHEMA NAME';
    static private $mainuser = 'SET USER NAME';
    static private $mainpass = 'SET PASSWORD';
    static private $h = 'localhost';
    static private $s;
    static private $u;
    static private $p;
    static private $bin = '/location/to/mysql/bin/'; //Mysql Bin Directory

    //FOR EXTERNAL HOST ONLY
    static private $forcePort = FALSE;
    static private $ssh = FALSE; //Never tested
    static private $plink = FALSE;
    // =================================== //
    
    public $message;
    protected $xt;
    protected $ml;
    protected $dbo;
    protected $sqltype = 'mysql';
    protected $server; //Need to be set if MSSQL --- NOT CONFIGURED
    
    public function __construct($s='', $ssh=NULL){
        self::changeStatic('s', self::$mainschema);
        self::changeStatic('u', self::$mainuser);
        self::changeStatic('p', self::$mainpass);
        if(preg_match('/localhost/', DBSERVERNAME)){ self::changeStatic('h', '127.0.0.1'); }
        $this->xt = new xTags();
		
        #debug
        //echo $s;
        if(!empty($s) && $s !== self::$s){ self::changeStatic('s', $s); }
        if(!is_null($ssh) && is_bool($ssh)) { self::changeStatic('ssh', $ssh); }
		
        $this->connect();
    }
    
    private static function changeStatic($name,$newVal){
        self::${$name} = $newVal;
    }
    
    public function selectDB($s=''){
        self::changeStatic('s',htmlspecialchars($s));
    }
    
    public function connect($ignore=FALSE){
        $port = NULL;
        if($this->sqltype == 'mysql'){
            $s = self::$s;
            if($s !== self::$mainschema && !$ignore){
                $this->checkDatabase($s);
            }
            if(self::$ssh) {
                $port = (!isset(self::$forcePort)) ? "3307" : self::$forcePort;
                if(self::$plink){
                    $exeCode = sprintf("plink -L %s:127.0.0.1:3306 %s@%s", $this->mres($port), $this->mres(self::$u), $this->mres(self::$h) );
                } else {
                    $exeCode = sprintf("ssh -f -L %s:127.0.0.1:3306 %s@%s sleep 60 >> logfile", $this->mres($port), $this->mres(self::$u), $this->mres(self::$h) );
                }
                shell_exec($exeCode);
            }
            $this->dbo = new mysqli(self::$h,self::$u,self::$p,$s);
            if($this->dbo->connect_error){
                $test = '';
                die($test.$this->dbo->connect_error);
            }
            if(!$this->dbo->set_charset("utf8")){
                printf("<h3 class=\"gen_warning\">There was an error setting character as UTF-8: %s\n</h3>",$this->dbo->error);
            }
        }
        
        //DO NOT USE -- NEVER CONFIGURED, NEVER NEEDED
        if($this->sqltype == 'mssql'){
            $this->dbo = mssql_connect($this->server, self::$u, self::$p, $port);
            if(!$this->dbo){
                die('<div class="generror2">Unable to connect to the SQL server!</div>');
            }
        }
        
        return $this->dbo;
    }
    
    public function externalHost($params=FALSE){
        $ut = new Utils();
        $host = NULL; $user = NULL; $password = NULL; $schema = self::$s;
        $sqltype = $this->sqltype; $server = NULL; $error = FALSE; $plink = FALSE;
        $ssh = FALSE; $port = "3307";
        //TO SET AN REMOTE CONNECTION YOU NEED TO CONFIGURE AN SPECIFIC USER
        //CONFIGURED IN YOUR REMOTE SERVER FOR THIS PURPOSE ONLY, ALSO YOU NEED
        //TO SPECIFY THE SERVER THE STATIC IP ADDRESS THE REQUEST IS ORIGINATED
        //FROM FOR SECURITY REASONS.
        $arr = (is_string($params))? json_decode($params) : $params;
        #$ut->echo_array($arr);
        if(is_array($arr) || is_object($arr)){
            $this->disconnect();
            foreach($arr as $k=>$v){
                ${$k} = $ut->processInputData($v);
            }
            if(isset($host) && isset($user) && isset($password) && isset($schema)){
                self::changeStatic('h', $host);
                self::changeStatic('u', $user);
                self::changeStatic('p', $password);
                self::changeStatic('s', $schema);
                if(isset($sqltype)){ $this->sqltype = $sqltype; }
                //SET TUNNELING
                if(isset($plink)){ self::$plink = $plink; }
                if(isset($ssh)){ self::$ssh = $ssh; }
                if(self::$ssh){ self::$forcePort = $port; }
                //=============
                if($this->sqltype !== 'mysql' && isset($server)){
                    $this->server = $server;
                } elseif($this->sqltype !== 'mysql') {
                    $this->message = "FOR NON MYSQL CONNECTIONS YOU NEED TO PROVIDE SERVER";
                    $error = TRUE;
                }
            } else {
                $this->message = "You need basic information for external connection";
                $error = TRUE;
            }
        } else {
            $this->message = "PARAMETERS ARE NOT SET PROPERLY";
            $error = TRUE;
        }
        
        if(!$error){ $this->connect(TRUE);}
        else {throw new Exception($this->message,'400');}
    }
    /*
     * Compare fields of two intances of same objects in different locations
     * main use to synch identical intances, one remote / one local.
     * (ARRAY) $a result of query as an array (example Local Intance Object)
     * (ARRAY) $b result of query object as an array (example Remote Intance Object)
     * (ARRAY) $fieldList a list of fields for table requested
     * 
     * To update local intance of a table, switch values of $a and $b, 
     * $a value is always the one that always dominates
     * 
     * Example
     * $a = Remote Storage Intance Result as Array
     * $b = Local Storage Intance Results as Array
     * 
     * Returns
     * array(
     *  "update"=>array(SQL Result),
     *  "delete"=>array(SQL Result),
     *  "insert"=>array(SQL Result)
     * )
     * 
     * IMPORTANT NOTE:
     * Second Intance ($b) dominates. IF $b value is found $b replaces/remove $a;
     */
    function compareFields($a,$b,$fieldList,$nextInA,$nextInB){
        $field = array();
        $diff = array();
        foreach($fieldList as $fields){
            $field[] = $fields;
        }
        $totals = ($nextInA > $nextInB) ? $nextInA : $nextInB;

        for($i=1; $i<$totals; $i++){
            $onFirstIntance = (isset($a[$i])) ? TRUE : FALSE;
            $onSecondIntance = (isset($b[$i])) ? TRUE : FALSE;
           
            if($onSecondIntance && $onFirstIntance){
                //ACTION TO TAKE IF BOTH ARE EXISTING
                $differenceFound = FALSE;
                foreach($field as $fld){
                    $xValue = (isset($a[$i]))? (string) trim($a[$i][$fld]) : NULL;
                    $yValue  = (isset($b[$i]))? (string) trim($b[$i][$fld]) : NULL;
                    if($xValue !== $yValue){ $differenceFound = TRUE; }
                }
                if($differenceFound){ $diff['update'][] = $a[$i]; }
            } elseif($onSecondIntance && !$onFirstIntance) {
                //ACTION TO TAKE IF THEY ARE IN REMOTE BUT NOT LOCAL
                $diff['delete'][] = $b[$i];
            } elseif(!$onSecondIntance && $onFirstIntance) {
                //ACTION TO TAKE IF IN LOCAL BUT NOT IN REMOTE
                $diff['insert'][] = $a[$i];
            }
        }
        return $diff;
    }
    
    /* Verify if connection is still active */
    public function verifyConnection($s){
        $dbo = $this->dbo;
        if($dbo->select_db($s)){
            $this->message = 'Connection Successful!';
            return true;
        } else {
            $this->message = 'Connection Failed!';
            return false;
        }
    }
    
    public function reconnect($s=''){
        if($this->sqltype == 'mysql'){
            $news = ($s != '')? $s : self::$s;
            self::changeStatic('s', $news);
        }
        if($this->sqltype == 'mssql'){
            $this->server = ($s != '')? $s : $this->server;
        }
        $this->connect();
    }
    
    public function set($var,$str){
        if(!preg_match('/(u|p|h|s)/i',$var)){
            $this->{$var} = $str;
        } else {
            $this->message = 'This variable cannot be set!';
        }
    }
    
    private function checkDatabase($s){
        //Add schemas with credentials
        /*
         * case '[SCHEMA NAME string]':
         *      self::changeStatic('u', [USER string]);
         *      self::changeStatic('p', [PASSWORD string]);
         *      self::changeStatic('s', $s);
         *      break;
         *
         */
        switch($s){
            case self::$mainschema:
                self::changeStatic('u',self::$mainuser);
                self::changeStatic('p',self::$mainpass);
                self::changeStatic('s',$s);
                break;
        }
    }

    public function throw_error($e,$cNum = FALSE,$dev = FALSE) {
        echo "<div class=\"generror\" style=\"text-align:left; display:block;\">";
        $eNum = ($cNum)? $cNum : $e->getCode();
        echo "<h2>Error No: ".$eNum. " - ". $e->getMessage() . "</h2>";
        if(substr_count($e->getFile(),'\\') > 0){
            $file = explode('\\',$e->getFile());
        } elseif(substr_count($e->getFile(),'/') > 0) {
            $file = explode('/',$e->getFile());
        } else {
            $file = $e->getFile();
        }
        $fileLine = $e->getLine();
        if(is_array($file)){
            $fcount = count($file);
            $lastcount = $fcount - 1;
            $actualfile = $file[$lastcount];
            //echo '<pre>'; print_r($file); echo '</pre>';
        } else {
            $actualfile = $file;
        }
        $root = (substr_count(DBSERVERNAME,'localhost') > 0)? str_replace('/',DIRECTORY_SEPARATOR,DBROOT) : '/var/www/html';
        $droot = $root;
        $details =  str_replace( $droot, '', nl2br($e->getTraceAsString()));
        if($dev || (CURRENTKEY === ROOTAKI && CURRENTUSER === ROOTUSER)){ echo "<div>Thrown by: $actualfile on line #$fileLine<br />$details</div>"; }
        echo "</div>";
        //exit;
    }
    
    public function tdetails($pre = ''){
        $nointro = false;
        if(isset($pre) && !empty($pre)){
            $sql = ($this->sqltype === 'mysql')? "SHOW TABLES LIKE '%$pre%';" : "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE '%$pre%'";
            $nointro = true;
        } else {
            $sql = ($this->sqltype === 'mysql')? "SHOW TABLES;" : "Select * From INFORMATION_SCHEMA.TABLES;";
        }
        
        $list = "";

        try { $qry = $this->query($sql); } catch(Exception $e) { $this->throw_error($e); }
        //return $qry;
        if(is_object($qry)){
            while($a = $this->darray($qry)){
                $intro = ($nointro)? " (%$pre%)" : '';
                $list[] = $a['Tables_in_'.self::$s.$intro];
            }
        }
        
        if(is_object($qry)){ $this->free($qry);}
        
        if(is_array($list)){
            return $list;
        } else {
            return false;
        }
        
    }
    
    public function tstatus($obj = array(),$infoSchema = FALSE){
        $nointro = false; $data = NULL; $table = NULL;
        if(count($obj) > 0){
            foreach($obj as $k=>$v){ ${$k} = $this->mres($v); }
        }
        $where = (isset($table)) ? array($table,"WHERE `Name` = '$table'") : "";
        $fwhere = (isset($where[1]))? $where[1] : '';
        $tname = (isset($where[0]))? "`table_name` = '".$where[0]."' AND" : '';
        if(isset($data) && !empty($data)){
            $data = $this->mres($data);
            $sql = ($this->sqltype === 'mysql' && !$infoSchema)? "SHOW TABLE STATUS FROM `$data` $fwhere;" : "Select `table_name`,`table_rows` as `total_items`,`auto_increment` AS `next_id` FROM INFORMATION_SCHEMA.TABLES WHERE $tname `table_schema` = '$data';";
            $nointro = true;
        } else {
            $sql = ($this->sqltype === 'mysql' && !$infoSchema)? "SHOW TABLE STATUS FROM `".self::$s."` $fwhere;" : "Select `table_name`,`table_rows` as `total_items`,`auto_increment` AS `next_id` FROM INFORMATION_SCHEMA.TABLES WHERE $tname `table_schema` = '".self::$s."';";
        }
        $list = array();
        try { $qry = $this->query($sql,NULL,1); } catch(Exception $e) { $this->throw_error($e); }
        //return $qry;
        if(is_object($qry)){
            while($a = $this->assoc($qry)){
                $list[] = $a;
            }
        }
        $count = count($list);
        if(is_object($qry)){ $this->free($qry);}
        if($count > 0){ return $list; }
        return false;
    }
    
    public function flushReset($flush = TRUE, $option = 'TABLES', $variant = NULL){
        $d = $this->dbo; $sql = NULL;
        $optionsAvailable = array('TABLES','LOGS','MASTER','PRIVILEGES','QUERY CACHE','SLAVE','STATUS','USER_RESOURCES');
        $options = explode(',',$option);
        $optList = array();
        foreach($options as $opt) {
            if(in_array($opt,$optionsAvailable)) {
                $optList[] = $opt;
            }
        }
        if(count($optList) > 0) { $option = join(', ',$optList); }
        if (in_array($option,$optionsAvailable)) {
            switch($flush) {
                case FALSE:
                    $sql = "RESET $option $variant;";
                    break;
                default:
                    $sql = (preg_match('/MASTER|SLAVE/',$option)) ? "RESET $option $variant;" : "FLUSH $option $variant";
            }
        }
        
        $d->query($sql);
    }
    
    public function checkSpecials($text){
        $s = array(chr(130), chr(136), chr(155), chr(139), chr(145), chr(146), chr(147), chr(148), chr(150), chr(151), chr(133), chr(241), chr(209), chr(188), chr(189), chr(190), chr(064), chr(174), chr(169), chr(193), chr(255), chr(201), chr(233), chr(205), chr(237), chr(211), chr(243), chr(218), chr(250), chr(161), chr(191));
        foreach($s as $sc){
            if(strpos($text,$sc) > -1){
                return true;
            }
        }
        return false;
    }
    
    public function csq($text) {
        if((!is_null($text) && !empty($text)) && (is_object($text) || is_array($text))){
            throw new Exception("Cannot process none strings as strings. Array or object submitted.",500);            
        }
        $sp = array(
            91 => "[", 92 => "\\", 93 => "]", 94 => "^", 95 => "_", 96 => "`", 123 => "{", 124 => "|", 125 => "}", 126 => "~", 128 => "&euro;", 161 => "&iexcl;", 162 => "&cent;", 163 => "&pound;", 164 => "&curren;", 165 => "&yen;", 166 => "&brvbar;", 167 => "&sect;", 168 => "&uml;", 169 => "&copy;", 170 => "&ordf;", 171 => "&laquo;", 172 => "&not;", 174 => "&reg;", 175 => "&macr;", 176 => "&deg;", 177 => "&plusmn;", 178 => "&sup2;", 179 => "&sup3;", 180 => "&acute;", 181 => "&micro;", 182 => "&para;", 183 => "&middot;", 184 => "&cedil;", 185 => "&sup1;", 186 => "&ordm;", 187 => "&raquo;", 188 => "&frac14;", 189 => "&frac12;", 190 => "&frac34;", 191 => "&iquest;", 192 => "&Agrave;", 193 => "&Aacute;", 194 => "&Acirc;", 195 => "&Atilde;", 196 => "&Auml;", 197 => "&Aring;", 198 => "&AElig;", 199 => "&Ccedil;", 200 => "&Egrave;", 201 => "&Eacute;", 202 => "&Ecirc;", 203 => "&Euml;", 204 => "&Igrave;", 205 => "&Iacute;", 206 => "&Icirc;", 207 => "&Iuml;", 208 => "&ETH;", 209 => "&Ntilde;", 210 => "&Ograve;", 211 => "&Oacute;", 212 => "&Ocirc;", 213 => "&Otilde;", 214 => "&Ouml;", 215 => "&times;", 216 => "&Oslash;", 217 => "&Ugrave;", 218 => "&Uacute;", 219 => "&Ucirc;", 220 => "&Uuml;", 221 => "&Yacute;", 222 => "&THORN;", 223 => "&szlig;", 224 => "&agrave;", 225 => "&aacute;", 226 => "&acirc;", 227 => "&atilde;", 228 => "&auml;", 229 => "&aring;", 230 => "&aelig;", 231 => "&ccedil;", 232 => "&egrave;", 233 => "&eacute;", 234 => "&ecirc;", 235 => "&euml;", 236 => "&igrave;", 237 => "&iacute;", 238 => "&icirc;", 239 => "&iuml;", 240 => "&eth;", 241 => "&ntilde;", 242 => "&ograve;", 243 => "&oacute;", 244 => "&ocirc;", 245 => "&otilde;", 246 => "&ouml;", 247 => "&divide;", 248 => "&oslash;", 249 => "&ugrave;", 250 => "&uacute;", 251 => "&ucirc;", 252 => "&uuml;", 253 => "&yacute;", 254 => "&thorn;", 255 => "&yuml;"
        );
        foreach($sp as $k => $v){ $text = str_replace(chr($k), $v, $text); }
        return $text;
    }
    
    public function injection($txt){
        $filter = $this->dbo->real_escape_string($txt);
        return $filter;
    }
    
    public function getXSSFilter(){
        $pattern = '/((\%3C)|<)((\%2F)|\/)*[a-z0-9\%]+((\%3E)|>) | '
                . '((\%3C)|<)((\%69)|i|(\%49))((\%6D)|m|(\%4D))((\%67)|g|(\%47))[^\n]+((\%3E)|>) | '
                . '((\%3C)|<)[^\n]+((\%3E)|>)/ix';
        return $pattern;
    }
    
    public function clearXSS($txt){
        $filter = preg_replace($this->getXSSFilter(),'',$txt);
        return $filter;
    }
    
    private function typeDef($type){
        //thanks to Johnathan comment at http://php.net/manual/en/mysqli.field-count.php
        $mysqli_type = array();
        $mysqli_type[0]                     = "DECIMAL";
        $mysqli_type[1]                     = "TINYINT";
        $mysqli_type[2]                     = "SMALLINT";
        $mysqli_type[3]                     = "INTEGER";
        $mysqli_type[4]                     = "FLOAT";
        $mysqli_type[5]                     = "DOUBLE";

        $mysqli_type[7]                     = "TIMESTAMP";
        $mysqli_type[8]                     = "BIGINT";
        $mysqli_type[9]                     = "MEDIUMINT";
        $mysqli_type[10]                    = "DATE";
        $mysqli_type[11]                    = "TIME";
        $mysqli_type[12]                    = "DATETIME";
        $mysqli_type[13]                    = "YEAR";
        $mysqli_type[14]                    = "DATE";

        $mysqli_type[16]                    = "BIT";

        $mysqli_type[246]                   = "DECIMAL";
        $mysqli_type[247]                   = "ENUM";
        $mysqli_type[248]                   = "SET";
        $mysqli_type[249]                   = "TINYBLOB";
        $mysqli_type[250]                   = "MEDIUMBLOB";
        $mysqli_type[251]                   = "LONGBLOB";
        $mysqli_type[252]                   = "BLOB";
        $mysqli_type[253]                   = "VARCHAR";
        $mysqli_type[254]                   = "CHAR";
        $mysqli_type[255]                   = "GEOMETRY";
        
        //SQL
        
        #integers & numbers
        $mysqli_type['bigint']              = "BIGINT";
        $mysqli_type['int']                 = "INTEGER";
        $mysqli_type['smallint']            = "SMALLINT";
        $mysqli_type['tinyint']             = "TINYINT";
        $mysqli_type['bit']                 = "BIT";
        $mysqli_type['decimal']             = "DECIMAL";
        $mysqli_type['numeric']             = "DECIMAL";
        $mysqli_type['money']               = "DECIMAL";
        $mysqli_type['smallmoney']          = "DECIMAL";
        $mysqli_type['float']               = "FLOAT";
        $mysqli_type['real']                = "FLOAT";
        
        #datetime
        $mysqli_type['datetime']            = "DATETIME";
        $mysqli_type['smalldatetime']       = "DATETIME";
        $mysqli_type['timestamp']           = "TIMESTAMP";
        
        #char
        $mysqli_type['char']                = "CHAR";
        $mysqli_type['varchar']             = "VARCHAR";
        $mysqli_type['text']                = "LONGTEXT";
        $mysqli_type['nchar']               = "BLOB";
        $mysqli_type['nvarchar']            = "MEDIUMBLOB";
        $mysqli_type['ntext']               = "LONGBLOB";
        
        #binary
        $mysqli_type['binary']              = "BLOB";
        $mysqli_type['varbinary']           = "MEDIUMBLOB";
        $mysqli_type['image']               = "LONGBLOB";
        
        #other
        $mysqli_type['cursor']              = "CURSOR";
        $mysqli_type['sql_variant']         = "SQLVARIANT";
        $mysqli_type['uniqueidentifier']    = "UNIQUEID";
        
        
        return (isset($mysqli_type[$type]))? $mysqli_type[$type] : $type;
    }
    
    private function flags($flag){
        $mysqli_flag = array();
        
        $mysqli_flag[0]         = "NOT_SET";
        $mysqli_flag[1]         = "NOT_NULL_FLAG";
        $mysqli_flag[2]         = "PRI_KEY_FLAG";
        $mysqli_flag[4]         = "UNIQUE_KEY_FLAG";
        $mysqli_flag[8]         = "MULTIPLE_KEY_FLAG";
        $mysqli_flag[16]        = "BLOB_FLAG";
        $mysqli_flag[32]        = "UNSIGNED_FLAG";
        $mysqli_flag[64]        = "ZEROFILL_FLAG";
        $mysqli_flag[128]       = "BINARY_FLAG";
        $mysqli_flag[256]       = "ENUM_FLAG";
        $mysqli_flag[512]       = "AUTO_INCREMENT_FLAG";
        $mysqli_flag[1024]      = "TIMESTAMP_FLAG";
        $mysqli_flag[2048]      = "SET_FLAG";
        $mysqli_flag[16384]     = "PART_KEY_FLAG";
        $mysqli_flag[16388]     = "NULL_UNIQUE_KEY";
        $mysqli_flag[16389]     = "NOT_NULL_UNIQUE_KEY";
        $mysqli_flag[32768]     = "NUM_FLAG";
        $mysqli_flag[49667]     = "PRI_AUTO_INCREMENT_KEY_FLAG";
        $mysqli_flag[49699]     = "PRI_ZEROFILL_AUTO_INCREMENT_FLAG";
        $mysqli_flag[32768]     = "GROUP_FLAG";
        $mysqli_flag[32769]     = "NOT_NULL_INTEGER_FLAG";
        $mysqli_flag[65536]     = "UNIQUE_FLAG";
        
        return (isset($mysqli_flag[$flag]))? $mysqli_flag[$flag] : $flag;
   }
    
   public function fdetails($tb='',$id = TRUE){
        $sq = "SELECT * FROM `$tb` LIMIT 0,1;";
        //$qry = mysqli_query($this->dbo, $sq);
        $dbo = $this->dbo;
        $info = '';
        $numberPattern = '/int|num|bool|date|time|blob|float|decimal/i';
        $return = FALSE;
        #debug
        //$s = self::$s;
        //echo $qry.'<br>'.$sq.'<br>'.$s;
        try{
            $qry = $dbo->query($sq);
            if($qry){
                $fields = $qry->fetch_fields();
                if(is_array($fields)){
                    foreach($fields as $fldCount){
                        $ignore = FALSE;
                        if(!$id && preg_match('/^id/',$fldCount->name)){
                            $ignore = TRUE;
                        }
                        if(!$ignore){
                            $type = $this->typeDef($fldCount->type);
                            $info[$fldCount->name] = array(
                                'name' => $fldCount->name,
                                'length' => (!preg_match($numberPattern,$type))? ($fldCount->length/3) : $fldCount->length,
                                'bit_length' => $fldCount->length,
                                'type' => $type,
                                'default' => $fldCount->def,
                                'flag' => $this->flags($fldCount->flags),
                                'bit_flag' => $fldCount->flags
                                    );
                            if(preg_match($numberPattern,$type)){
                                $info[$fldCount->name]['decimals'] = $fldCount->decimals;
                            }
                        }
                    }
                    if(is_array($info)){ $return = $info; }
                }
            }
            if(is_object($qry)){ $this->free($qry); }
            //var_dump($fields);
        } catch(Exception $e){
            //if(is_object($qry)){ $dbo->free($qry); }
            throw new Exception('Caught exception: '.$e->getMessage(), '500');
        }
        return $return;
    }
    
    public function check_entry_exists($table,$column,$value){
        $sql = sprintf("SELECT * FROM %s WHERE %s ='%s'",
                $this->mres($table),
                $this->mres($column),
                $this->mres($value));
        try { $qry = $this->query($sql,1,1); } catch(Exception $e){
            $this->throw_error($e);
        }
        return (is_numeric($qry) && $qry > 0)? true : false; 
    }
    
    public function process_as_post($f,$v){
	$field_array = explode('|',$f);
	$totalfields = count($field_array);
        
	$value_array = explode('|',$v);
	$totalvalues = count($value_array);
        
        if($totalfields != $totalvalues){
            return false;
        } else {
            $post = array();
            for($i=0;$i<$totalfields;$i++){
                $post[$field_array[$i]] = $value_array[$i];
            }
            return $post;
        }
    }
	
    public function processValues($post='',$type=FALSE,$control_filter=TRUE){
        $fields = ''; $values = '';
        if(is_array($post) || is_object($post)){
            foreach($post as $f=>$v){
                switch($control_filter) {
                    case 'raw':
                        $filtering = $v;
                        break;
                    case 'decode':
                        $filtering = html_entity_decode(urldecode(trim($v)), ENT_NOQUOTES | ENT_HTML5);
                        break;
                    case FALSE:
                        $filtering = htmlentities(urldecode($v), ENT_NOQUOTES, "UTF-8",false);
                        break;
                    default:
                        $filtering = $this->csq($v);
                }
                $v = $this->real_escape($filtering);
                if($type == 'insert'){
                    $fields[] = $f;
                    $values[] = $v;
                } else {
                    $values[] = (preg_match('/^(id)/',$f) && is_numeric($v))? "`$f` = $v" : "`$f` = '$v'";
                }
            }
            if(is_array($fields)){
                $flist = join('`, `',$fields);
                $vlist = str_replace("","NULL",str_replace("''","NULL",join("', '",$values)));
                $this->message = $this->xt->h("Values set to insert sql query!",2,'class:mqerror default_padding centertext green');
                return str_replace("''","NULL","(`".$flist."`) VALUE ('".$vlist."')");
            } elseif(!is_array($fields) && is_array($values)){
                $this->message = $this->xt->h("Values set to update sql query!",2,'class:mqerror default_padding centertext green');
                $values = str_replace("","NULL",str_replace("''","NULL",$values));
                return join(',',$values);
            } else {
                $this->message = $this->xt->h("This is an empty query!",2,'class:mqerror default_padding centertext red');
                return false;
            }
        } else {
            $this->message = $this->xt->h("This is an empty query!",2,'class:mqerror default_padding centertext red');
            return false;
        }
    }
    
    public function query($q,$w=false,$ex=false){
        if(empty($q)){ throw new Exception("Scripting Error, empty query sent!","356");}
        try{
            $qry = mysqli_query($this->dbo,$q);
        } catch(mysqli_sql_exception $e){
            throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
        $r = false;
        if(!$this->dbo->error){
            $sch = (preg_match('/(^truncate|^update|^delete|^insert|^replace|^alter|^create)/i', $q)) ? TRUE : FALSE;
            if(preg_match('/(^create|^drop)/i', $q)){
                if($qry){ return TRUE; } else { return FALSE; }
            } else {
                if(is_object($qry)){ $res = ($sch) ? $this->affected() : $qry->num_rows; }
                elseif(is_bool($qry)) { $res = ($qry)? 1 : 0; }
                else { $res = $qry; }
            }
            if($w){
                if(is_object($qry)){ $this->free($qry);}
                return $res;
            } else {
                if($res > 0){ $r = ($sch)? $this->xt->h('Execution Successful',2,'class:generror,style:color..green') : $qry; }
                else { $r = ($sch)? $this->xt->h('No changes found!',2,'class:generror') : false; }
            }
        } else {
            try {
                $fulluri = (strripos(DBSCRIPT , '/') > 0)? explode('/',DBSCRIPT) : DBSCRIPT;
                if(is_array($fulluri)){
                    $urilen = count($fulluri);
                    $lgroup = $urilen-1;
                    $uri = $fulluri[$lgroup];
                } else {
                    $uri = $fulluri;
                }
                $err = ($ex)? $this->dbo->error.' - ' : 'Sorry - Error #'.$this->dbo->errno.' at '.$uri.' - ';
                throw new Exception($err.'Encounter problems with the request!',$this->dbo->errno);
            } catch(Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
            }
        }
        return $r;
    }
    
    public function query_paginator($q,$w=false,$e=false){
        $nr = false;
        try { $sql = $this->query($q,$w,$e); } catch(Exception $e) { $this->throw_error($e); }
        if(is_object($sql)){
            $qry = $sql;
            
            //top tray
            $top = $this->paginator($q,false);
            if(is_array($top)){
                $top_tray = (isset($top['buttons']))? $top['buttons'] : '';
                $nr = (isset($top['nr']))? $top['nr'] : false;
            }
            
            //bottom tray
            $bottom= $this->paginator($q,true,$nr);
            if(is_array($bottom)){
                $bottom_tray = (isset($bottom['buttons']))? $bottom['buttons'] : '';
            }
            
            //returning in array
            return array("query"=>$qry,"top"=>$top_tray,"bottom"=>$bottom_tray,"total"=>$nr);
        } else {
            return false;
        }
    }
    
    private function paginator($q,$pos=true,$nr=false){
        $x = new xTags();
        $v = new DirectoryTree();
        $requests = filter_input_array(INPUT_GET);
        if(preg_match('/LIMIT/',$q)){
            
            if(!$nr){
                $allqry = preg_replace('(LIMIT.*?;)',';',$q);
                try { $aqry = $this->query($allqry); } catch(Exception $e) { $this->throw_error($e); }
                $totalRows = (is_object($aqry))? $this->numrows($aqry) : 0;
                if(is_object($aqry)){ $this->free($aqry);}
            } else {
                $totalRows = $nr;
            }
            
            $actualpage = str_replace(';','',preg_replace('/(.*?LIMIT.*?)([0-9]{1,5}),([0-9]{1,5}.*?;)/','$2',$q));
            $limit = str_replace(';','',preg_replace('/(.*?LIMIT.*?[0-9]{1,5},)([0-9]{1,5})(.*?;)/','$2',$q));
            $totalpages = ''; $buttons = ''; $r = ''; $extra = '?'; $class = 'metroButton';
            if(is_array($requests)){
                $req = array();
                foreach($requests as $get=>$data){
                    $dt = (empty($data))? '' : '='.$data;
                    $get = trim($get);
                    if(!preg_match('/^start$|^limit$/',$get) && (!is_null($get) || $get !== ' ')){ $req[] = "$get$dt"; }
                }
                if(count($req) > 0){ $extra = '?'.join('&',$req).'&'; }
            }
            $href = $x->processText($v->setUrl()).DBSCRIPT.$extra."start=[start]&limit=$limit";

            if($totalRows > 0 && $totalRows > abs($limit)){
                $totalpages = ceil($totalRows / abs($limit));
                $buttons = ($actualpage > 0)? $x->a("&laquo;","class:$class,href:".str_replace('[start]',0,$href)).$x->a('<','class:'.$class.',href:'.str_replace('[start]',$actualpage - $limit,$href)) : '';
                
                for($i=0;$i<$totalpages;$i++){
                    $on = ($i * $limit == $actualpage)? '_select' : '';
                    $link = ($i * $limit == $actualpage)? ',style:user-select..none' : ",href:".str_replace('[start]',$i*$limit,$href);
                    $buttons .= ($i > ($actualpage/$limit - 3) && $i < ($actualpage/$limit + 3))? $x->a($i+1,"class:$class$on$link") : "";
                }
                
                $buttons .= ( abs(abs($actualpage) / abs($limit) + 1) < abs($totalpages))? $x->a('>','class:'.$class.',href:'.str_replace('[start]',$actualpage + $limit,$href)).$x->a("&raquo;","class:$class,href:".str_replace('[start]',($totalpages*$limit) - $limit,$href)) : '';
            }

            if($pos){
                //display bottom paginator
                $r = $x->div($buttons,'id:pagesBottom,class:centertext inline_block width100');
            } else {
                //display top paginator
                $showtotalpages = (empty($totalpages))? 1 : $totalpages;
                $left = $x->span("Page ".(($actualpage/$limit)+1)." of ".$showtotalpages,"id:pagesTop, class:left inline_block width20 midalign");
                $firstItem = ($actualpage == 0)? 1 : $actualpage + 1;
                $lastItem = (($firstItem +($limit)) > $totalRows)? $totalRows : ($firstItem + $limit) - 1;
                $right = $x->span("$firstItem to $lastItem of $totalRows","id:pagesContent, class:right righttext inline_block width20 midalign");
                $r = $x->frm($left.$right.$x->span($buttons,'class:centertext inline_block width60 midalign'),'name:paginator,class:pagesTop',DBSCRIPT,'post');
            }
            
        } else {
            return false;
        }
        
        return array('buttons'=>$r,'nr'=>$totalRows);
    }
    
    public function numrows($qry){
        if(!is_object($qry)){ throw new Exception('Not an object! Sorry.','300'); }
        return $qry->num_rows;
    }
    
    public function affected(){
        return $this->dbo->affected_rows;
    }
    
    public function mres($txt){
        if(is_array($txt) || is_object($txt)){
            $nVal = array();
            foreach($txt as $k=>$v){
                if(is_array($v) || is_object($v)){
                    $nVal[$k] = $this->mres($v);
                } else {
                    $nVal[$k] = $this->dbo->real_escape_string($this->csq($v));
                }
            }
            return (is_object($txt))? (object) $nVal : $nVal;
        } else {
            return $this->dbo->real_escape_string($this->csq($txt));
        }
    }
    
    public function real_escape($txt) {
        if(is_array($txt) || is_object($txt)){
            $nVal = array();
            foreach($txt as $k=>$v){
                if(is_array($v) || is_object($v)){
                    $nVal[$k] = $this->real_escape($v);
                } else {
                    $nVal[$k] = $this->real_escape($v);
                }
            }
            return (is_object($txt))? (object) $nVal : $nVal;
        } else {
            return $this->dbo->real_escape_string($txt);
        }
    }
    
    public function newid(){
        return $this->dbo->insert_id;
    }
    
    public function object($qry){
        if(!is_object($qry)){ throw new Exception('Not an object! Sorry.','300');}
        return $qry->fetch_object();
    }
    
    public function assoc($qry){
        if(!is_object($qry)){ throw new Exception('Not an object! Sorry.','300');}
        return $qry->fetch_assoc();
    }
    
    public function darray($qry){
        if(!is_object($qry)){ throw new Exception('Not an object! Sorry.','300');}
        return $qry->fetch_array(MYSQLI_BOTH);
    }
    
    public function strip($txt){
        if(is_array($txt)){
            foreach($txt as $key=>$val){
                if(is_array($val)){
                    $txt[$key] = $this->strip($val);
                } else {
                    $txt[$key] = $this->clearXSS(str_replace('\\','',stripslashes($this->csq($val))));
                }
            }
            return $txt;
        } else {
            return $this->clearXSS(str_replace('\\','',stripslashes($this->csq($txt))));
        }        
    }
    
    public function free($qry){
        if(!is_object($qry)){ throw new Exception('Not an object! Sorry.','300');}
        $qry->free_result();
    }
    
    /* backup the db OR just a table */
    function backup_tables($tables = '*', $name = '', $dataonly = 0){
        //$st = new Structure();
        $return = '';
        $do = (abs($dataonly) !== 1)? false : true;
        //get all of the tables
        if($tables == '*'){
            $tables = $this->tdetails();
        } else {
            $tables = (is_array($tables)) ? $tables : explode(',',$tables);
        }

        //cycle through
        foreach($tables as $table){
            $id = ($do)? true : false;
            $fields = $this->fdetails($table,$id);
            $num_fields = count($fields);
            $fieldNames = array();
            foreach($fields as $f){
                $fieldNames[] = $f['name'];
            }
            try{ $result = $this->query("SELECT * FROM $table;"); } catch(Exception $e){ $this->throw_error($e); }
            $res = (is_object($result))? $this->numrows($result) : 0;
            
            $return .= "\n\n/*============================= $table =============================*/\n\n";
            
            if(!$do){
                $row2 = $this->assoc($this->query("SHOW CREATE TABLE $table"));
                //$st->viewArray($row2);
                $return.= "\n\nDROP TABLE IF EXISTS $table;"."\n\n";

                if(isset($row2['Create Table'])){
                    $return.= "\n\n".$row2['Create Table'].";\n\n";
                }
            }
            
            if($res > 0){
                $fieldlist = ($do)? "" : " (`".join('`,`',$fieldNames)."`) ";
                for ($i = 0; $i < $num_fields; $i++){
                    while($row = $this->darray($result)){
                        $return.= ($do)? "REPLACE INTO $table VALUES (" : "INSERT INTO $table$fieldlist VALUES (";
                        for($j=0; $j<$num_fields; $j++){
                            $row[$j] = $this->real_escape(stripslashes($row[$j]));
                            $row[$j] = preg_replace("/\\n/","\\n",$row[$j]);                            
                            if (isset($row[$j]) && !empty($row[$j])) { $return.= '"'.$row[$j].'"' ; } else { $return.= 'NULL'; }
                            if ($j<($num_fields-1)) { $return.= ','; }
                        }
                        $return.= ");\n";
                    }
                }
            } 
            if(is_object($result)){ $this->free($result); }
            $return .= "\n\n\n/*==================================================================================*/\n";
        }

        //save file
        $docroot = (preg_match('/localhost/', DBSERVERNAME))? DBROOT.'/temp_back' : "/var/www/temp";
        $filename = (empty($name))
                ? "$docroot/" . self::$s . "-backup-" . time() . '-' . (md5(implode(',',$tables))) 
                : "$docroot/$name" . "-" . date("Y-m-d");
        $handle = fopen($filename . '.sql', 'w+');
        $fname = (empty($name))
                ? self::$s . "-" . join(',', $tables) . "-" . date("Y-m-d-his") 
                : $name;
        fwrite($handle,$return);
        fclose($handle);

        Header("Content-type: application/octet-stream");
        Header("Content-Disposition: attachment; filename=$fname.sql");
    }
    
    public static function dump() {
        $ut = new Utils();
        if(!$ut->is_super()) { throw new Exception("You do not have enough clearance to execute a back up on this system.", 500); }
        if(!is_dir(DBROOT . "/backup")) { mkdir(DBROOT . "/backup"); chmod(DBROOT . "/backup", 755); }
        $exe = sprintf(self::$bin . "mysqldump -u " . self::$u //user
                . " -p" . self::$p //password
                . " --add-drop-database --add-drop-table" //options
                . " " . self::$s . " > " 
                . DBROOT . "/backup/" . date("Y-m-d_H-i-s") . "_" . self::$s . ".sql"); //FILENAME
        
        $output = shell_exec($exe);
        
        $ut->echo_array($output);
    }
    
    public function disconnect(){
        $d = $this->dbo;
        try {
            if($d->thread_id){ 
                $id = $d->thread_id;
                $d->kill($id);
                $d->close();
            }
        } catch(Exception $e) {
            $this->message = $e->getMessage();
        }
        self::changeStatic('s', self::$mainschema);
        self::changeStatic('u', self::$mainuser);
        self::changeStatic('p', self::$mainpass);
        self::changeStatic('h', self::$mainhost);
    }
}
