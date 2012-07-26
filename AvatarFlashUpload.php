<?php
/**
 * Inter框架核心文件之错误处理
 * 本组件依赖性：可独立使用。
 * 本文件的错误处理思路参考过以下程序，在此一并致谢！
 *     - 论坛程序Mybb{@link http://www.mybbchina.net/}
 *     - PHP框架Slightphp{@link http://phpchina.com/bbs/thread-150396-1-1.html}
 *     - PHP框架DooPHP{@link http://doophp.com/blog/article/diagnostic-debug-view-in-doophp}
 *
 * @author Horse Luke<horseluke@126.com>
 * @copyright Horse Luke, 2009
 * @license the Apache License, Version 2.0 (the "License"). {@link http://www.apache.org/licenses/LICENSE-2.0}
 * @version $Id: Error.php 128 2010-07-05 13:45:44Z horseluke@126.com $
 * @package Inter_PHP_Framework
 */

class Inter_Error
{

    /**
	 * 本类的数组配置。依据索引，从上到下为：
	 * debugMode：
     * 是否开启debug模式？若为true，将在在浏览器显示详细信息。否则将不显示。
     * 参数选择：默认为false，可选参数true或者false。
     * 参数类型：bool
     * 
     * friendlyExceptionPage：
     * 系统遇到exception的时候抛出的友好网页文件完整路径
     * 参数选择：默认为空。本参数只有当debugMode为false时才有效
     * 请指定为网页文件的完整路径（但不需要是绝对路径）。若文件不存在或者为空，则不进行任何操作。
     * 存在时则采取require模式。所以请自行保证防止xss的攻击！
     * 参数类型：string
     * 
     * logType：
     * 对错误进行包括错误追踪在内的详细记录（detail）、还是只需要简单记录（simple）【均使用PHP函数error_log进行记录】？
     * false将不进行任何记录。
     * 参数选择：默认为false。可选择'detail'或者'simple'或者布尔值false。
     * 不建议在生产环境进行详细记录（detail），否则在高访问量的情况下，日志的条目将非常混乱！
     * 参数类型：bool|string
	 * 
	 * logDir：
     * 记录日志的文件夹（目录路径），结尾不要含有斜杠。
     * 参数选择： 默认为空。本参数只有当logType不为false时才有效；
     * 并且当为若为空、或者不是目录、或者目录不存在，则按照php.ini的设置进行错误记录处理。
     * 参数类型：string
     * 
     * suffix：
     * 记录日志的文件后缀。
     * 参数类型：string
     * 
     * variables：
     * 指定要检测和输出的变量名。
     * 参数类型：array
     * 
	 * @var array
	 */
    public static $conf = array(
                                'debugMode' => false,
                                'friendlyExceptionPage' => '',
                                'logType' => false,
                                'logDir' => '',
                                'suffix' => '-Inter-ErrorLog.log',
                                'variables' => array("_GET", "_POST", "_SESSION", "_COOKIE")
                                );

    /**
	 * (私有)所有错误、异常的信息存储数组
	 * @var array
	 */
    private static $_allError = array();

    /**
	 * (私有)是否借助PHP的register_shutdown_function注册了本类静态方法error_display和error_display
	 *
	 * @var unknown_type
	 */
    private static $_registered = false;


    /**
     * 处理PHP抛出的exception
     *
     * @param Exception $e
     */
    public static function exception_handler(Exception $e){

        self::init();

        $errorInfo = array();
        $errorInfo['time'] = time();
        $errorInfo['type'] = 'EXCEPTION';
        $errorInfo['name'] = get_class($e);
        $errorInfo['code'] = $e->getCode();
        $errorInfo['message'] = $e->getMessage();
        $errorInfo['file'] = $e->getFile();
        $errorInfo['line'] = $e->getLine();
        $errorInfo['trace'] = self::_format_trace($e->getTrace());

        self::$_allError[] = $errorInfo;

        //$debugMode为false时候，根据self::$friendlyExceptionPage进行友好错误操作
        if(false == self::$conf['debugMode']){
            if(is_file(self::$conf['friendlyExceptionPage'])){
                require(self::$conf['friendlyExceptionPage']);
            }
        }

    }


    /**
     * 处理PHP发现的错误
     *
     * @param integer $errno 错误代号
     * @param string $errstr 错误信息
     * @param string $errfile 错误所在文件
     * @param string $errline 错误所在行
     */
    public static function error_handler($errno, $errstr, $errfile, $errline) {

        self::init();

        $errorInfo = array();
        $errorInfo['time'] = time();
        $errorInfo['type'] = 'ERROR';

        //对error类型进行直观化处理~
        $errorText = array("1"=>"E_ERROR",
                            "2"=>"E_WARNING",
                            "4"=>"E_PARSE",
                            "8"=>"E_NOTICE",
                            "16"=>"E_CORE_ERROR",
                            "32"=>"E_CORE_WARNING",
                            "64"=>"E_COMPILE_ERROR",
                            "128"=>"E_COMPILE_WARNING",
                            "256"=>"E_USER_ERROR",
                            "512"=>"E_USER_WARNING",
                            "1024"=>"E_USER_NOTICE",
                            "2047"=>"E_ALL",
                            "2048"=>"E_STRICT"
                          );
        if(!empty($errorText[$errno])){
            $errorInfo['name'] = $errorText[$errno];
        }else{
            $errorInfo['name'] = '__UNKNOWN';
        }

        $errorInfo['code'] = $errno;
        $errorInfo['message'] = $errstr;
        $errorInfo['file'] = $errfile;
        $errorInfo['line'] = $errline;
        $trace = debug_backtrace();
        unset($trace[0]);    //调用该类自身的error_handler方法所产生的trace，故删除
        $errorInfo['trace'] = self::_format_trace($trace);

        self::$_allError[] = $errorInfo;
    }

    /**
     * 初始化本类
     */
    public static function init(){
        if( false == self::$_registered ){
            register_shutdown_function(array('Inter_Error', 'write_errorlog'));
            register_shutdown_function(array('Inter_Error', 'error_display'));
            self::$_registered = true;
        }
    }


    /**
     * (私有)对错误回溯追踪信息进行格式化输出处理。
     *
     * @param array $trace 错误回溯追踪信息数组
     * @return array $trace 错误回溯追踪信息数组
     */
    private static function _format_trace($trace){
        $return = array();
        //逐条追踪记录处理
        foreach ($trace as $stack => $detail){
            if(!empty($detail['args'])){
                $args_string = self::_args_to_string($detail['args']);
            }else{
                $args_string = '';
            }

            //规范追踪记录（感慨PHP太过自由，连trace记录也是不尽相同-_-||）
            $return[$stack]['class'] = isset($trace[$stack]['class']) ? $trace[$stack]['class'] : '';
            $return[$stack]['type'] = isset($trace[$stack]['type']) ? $trace[$stack]['type'] : '';
            //只有存在function的时候，才可能存在args，故以此合并之
            $return[$stack]['function'] = isset($trace[$stack]['function']) ? $trace[$stack]['function'].'('.$args_string.')' : '';
            $return[$stack]['file']=isset($trace[$stack]['file']) ? $trace[$stack]['file'] :'' ;
            $return[$stack]['line']=isset($trace[$stack]['line']) ? $trace[$stack]['line'] :'' ;
        }
        return $return;
    }


    /**
     * (私有)将参数转变为可读的字符串
     * 力求做到$e->getTraceAsString()的效果
     *
     * @param array $args
     * @return string
     */
    private static function _args_to_string($args){
        $string = '';
        $argsAll = array();
        foreach ($args as $key => $value){
            if(true == is_object($value)){
                $argsAll[$key] = 'Object('.get_class($value).')';
            }elseif(true == is_numeric($value)){
                $argsAll[$key] = $value;
            }elseif(true == is_string($value)){
                $temp = $value;
                if(!extension_loaded('mbstring')){
                    if(strlen($temp) > 300){
                        $temp = substr($temp, 0 ,300).'...';
                    }
                }else{
                    if(mb_strlen($temp) > 300){
                        $temp = mb_substr($temp, 0 ,300).'...';
                    }
                }
                $argsAll[$key] = "'{$temp}'";
                $temp = null;
            }elseif(true == is_bool($value)){
                if(true == $value){
                    $argsAll[$key] = 'true';
                }else{
                    $argsAll[$key] = 'false';
                }
            }else{
                $argsAll[$key] = gettype($value);
            }
        }
        $string = implode(',', $argsAll);
        return $string;
    }


    /**
     * 写入错误日志
     */
    public static function write_errorlog(){
        if( (false != (bool)self::$conf['logType']) && !empty(self::$_allError) ){
            $logText = '';

            foreach (self::$_allError as $key => $errorInfo){
                //为避免PHP5.1.0及以上版本关于时区的STRICT ERROR，在运行前请使用date_default_timezone_set设置之~
                $logText .= date("Y-m-d H:i:s", $errorInfo['time']). "\t".
                $_SERVER["REQUEST_URI"]."\t".
                $errorInfo['type']. "\t".
                $errorInfo['name']. "\t".
                'Code '. $errorInfo['code']. "\t".
                $errorInfo['message']. "\t".
                $errorInfo['file']. "\t".
                'Line '. $errorInfo['line']. "\n";

                if('detail' == self::$conf['logType'] && !empty($errorInfo['trace'])){
                    $prefix = "TRACE\t#";
                    foreach ( $errorInfo['trace'] as $stack => $trace ){
                        $logText .= $prefix. $stack. "\t". $trace['file']. "\t". $trace['line']. "\t". $trace['class']. $trace['type']. $trace['function']. "\n";
                    }
                }

            }

            if(empty(self::$conf['logDir']) || false == is_dir(self::$conf['logDir'])){
                error_log($logText);
            }else{
                $logFilename= date("Y-m-d",time()). self::$conf['suffix'];
                error_log($logText, 3, self::$conf['logDir']. DIRECTORY_SEPARATOR. $logFilename);
            }
        }
    }

    /**
     * 显示错误
     */
    public static function error_display(){
        if(false != self::$conf['debugMode'] && !empty(self::$_allError) ){
            $htmlText = '';
            foreach (self::$_allError as $key => $errorInfo){


                //错误div头
                $htmlText .= '<div class="intererrorblock">
    							<div class="intererrortitle">['.$errorInfo['name'].'][Code '.$errorInfo['code'].'] '.$errorInfo['message'].'</div>
    							<div class="intererrorsubtitle">Line '.$errorInfo['line'].' On <a href="'.$errorInfo['file'].'">'.$errorInfo['file'].'</a></div>
    							<div class="intererrorcontent">
							';

                //trace显示区
                if(empty($errorInfo['trace'])){
                    $htmlText .= 'No Traceable Information.';
                }else{
                    $htmlText .= '<table width="100%" border="1" cellpadding="1" cellspacing="1" rules="rows">
									<tr>
										<th scope="col">#</th>
										<th scope="col">File</th>
										<th scope="col">Line</th>
										<th scope="col">Class::Method(Args)</th>
									</tr>';
                    foreach ($errorInfo['trace'] as $stack => $trace){
                        $htmlText .= '<tr>
										<td>'.$stack.'</td>
										<td><a href="'.$trace['file'].'">'.$trace['file'].'</a></td>
										<td>'.$trace['line'].'</td>
										<td>'.$trace['class']. $trace['type']. htmlspecialchars($trace['function']) .'</td>
									</tr>';
                    }
                    $htmlText .= '</table>';
                }

                //错误div尾
                $htmlText .= '	</div>
    						</div>
							';
            }

            //输出
            echo <<<END
<style type="text/css">
<!--
.intererrorblock {
	font-size: 12pt;
	background-color: #FFC;
	text-align: left;
	vertical-align: middle;
	display: inline-block;
	border-collapse: collapse;
	word-break: break-all;
	padding: 3px;
	width: 100%;
}

.intererrorblock a:link {
	color: #00F;
	text-decoration: none;
}
.intererrorblock a:visited {
	text-decoration: none;
	color: #00F;
}
.intererrorblock a:hover {
	text-decoration: underline;
	color: #00F;
}
.intererrorblock a:active {
	text-decoration: none;
	color: #00F;
}

.intererrortitle {
	color: #FFF;
	background-color: #963;
	padding: 3px;
	font-weight: bold;
}

.intererrorsubtitle {
	padding: 3px;
	font-weight: bold;
	color: #F00;
}

.intererrorcontent {
	font-size: 11pt;
	color: #000;
	background-color: #FFF;
	padding: 3px;
}

.intererrorcontent table{
	font-size:14px;
	word-break: break-all;
	background-color:#D4D0C8;
	border-color:#000000;
}

.intererrorblock table a:link {
	color: #00F;
	text-decoration: none;
}
.intererrorblock table a:visited {
	text-decoration: none;
	color: #00F;
}
.intererrorblock table a:hover {
	text-decoration: underline;
	color: #00F;
}
.intererrorblock table a:active {
	text-decoration: none;
	color: #00F;
}

-->
</style>
{$htmlText}
END;

        self::show_variables();
        }
    }
    
    /**
     * 指定变量名检测和显示
     */
    public static function show_variables(){
        $variables_link = '';
        $variables_content = '';
        foreach( self::$conf['variables'] as $key ){
            $variables_link .= '<a href="#variables'.$key.'">$'.$key.'</a>&nbsp;';
            $variables_content .= '<div class="variablessubtitle"><a name="variables'.$key.'" id="variables'.$key.'"></a>$'.$key.'</strong></div>
						  <div class="variablescontent">';
            if(!isset($GLOBALS[$key])){
                $variables_content .= '$'. $key .' IS NOT SET.';
            }else{
                $variables_content .= nl2br(htmlspecialchars(var_export($GLOBALS[$key], true)));
            }
             $variables_content .= '</div>';
        }
        
            //输出
            echo <<<END
<style type="text/css">
<!--
.variablesblock {
	font-size: 12pt;
	background-color: #CCC;
	text-align: left;
	vertical-align: middle;
	display: inline-block;
	border-collapse: collapse;
	word-break: break-all;
	padding: 3px;
	width: 100%;
	color: #000;
}

.variablesblock a:link {
	color: #000;
	text-decoration: none;
}
.variablesblock a:visited {
	text-decoration: none;
	color: #000;
}
.variablesblock a:hover {
	text-decoration: underline;
	color: #000;
}
.variablesblock a:active {
	text-decoration: none;
	color: #000;
}

.variablessubtitle {
	padding: 3px;
	font-weight: bold;
	border: 1px solid #FFF;
}

.variablescontent {
	font-size: 11pt;
	color: #000;
	background-color: #FFF;
	padding: 3px;
}
-->
</style>
<div class="variablesblock">
    <div class="variablessubtitle">Variables: {$variables_link}</div>
    {$variables_content}
</div>
END;

    }

}

class config extends ArrayObject{

    /**
     * 构建函数
     *
     */
    public function __construct(){
    }

    /**
     * 从dz6.1f同步参数值
     */
    /*
    public function syncFromDZ(){
        
    }
    */
    
    /**
     * 对参数进行设置(ok)
     *
     * @param array $newConfig 新的参数数组
     */
    public function set( $newConfig = array() ){
        foreach ($newConfig as $key => $value){
            $this->$key = $value;
        }
    }
    
    public function __get($name){
        $this->$name = null;
        return null;
    }
}


class Controller_AvatarFlashUpload{

	public $input = array();
    public $config;
    //存储对象实例
    protected static $_objectInstance = array();
    /**
     * 构造函数。(ok)
     * 
     */
    public function __construct(){
         $this->config = $this->getInstanceOf('config');
    }

	/**
     * 初始化输入（ok）
     *
     * @param string $getagent 指定的agent
     */
    public function init_input($getagent = '') {
        $input = $this->getgpc('input', 'R');
        if($input) {
            $input = $this->authcode($input, 'DECODE', $this->config->authkey);
            parse_str($input, $this->input);
            $this->input = $this->addslashes($this->input, 1, TRUE);
            $agent = $getagent ? $getagent : $this->input['agent'];

            if(($getagent && $getagent != $this->input['agent']) || (!$getagent && md5($_SERVER['HTTP_USER_AGENT']) != $agent)) {
                exit('Access denied for agent changed');
            } elseif(time() - $this->input('time') > 3600) {
                exit('Authorization has expired');
            }
        }
        if(empty($this->input)) {
            exit('Invalid input');
        }
    }
    
    /**
     * 查找$this->input是否存在指定索引的变量？（ok）
     *
     * @param string $k 要查找的索引
     * @return mixed
     */
	public function input($k) {
		return isset($this->input[$k]) ? (is_array($this->input[$k]) ? $this->input[$k] : trim($this->input[$k])) : NULL;
	}

	
    /**
     * 获取显示上传flash的代码(ok)
     * 来源：Ucenter的uc_avatar函数
     * 依赖性：
     *     逻辑代码上为依赖本类和common类；实际操作中还须配合如下文件/组件：
     *         - Ucenter的头像上传flash文件（swf文件）
     */
    public function showuploadAction() {
        $uid = abs((int)$this->getgpc('uid', 'G'));
        if( $uid === null || $uid == 0 ){
            return -1;
        }
        $returnhtml = $this->getgpc('returnhtml', 'G');
        if( $returnhtml === null  ){
            $returnhtml =  1;
        }
        
        $uc_input = urlencode($this->authcode('uid='.$uid.
                                               '&agent='.md5($_SERVER['HTTP_USER_AGENT']).
                                               "&time=".time(), 
                                                   'ENCODE', $this->config->authkey)
                             );
        
        $uc_avatarflash = $this->config->uc_api.'/images/camera.swf?nt=1&inajax=1&input='.$uc_input.'&agent='.md5($_SERVER['HTTP_USER_AGENT']).'&ucapi='.urlencode($this->config->uc_api. substr( $_SERVER['PHP_SELF'], strrpos($_SERVER['PHP_SELF'], '/') ) ).'&uploadSize='.$this->config->uploadsize;
        if( $returnhtml == 1 ) {
            $result = '<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,0,0" width="450" height="253" id="mycamera" align="middle">
			<param name="allowScriptAccess" value="always" />
			<param name="scale" value="exactfit" />
			<param name="wmode" value="transparent" />
			<param name="quality" value="high" />
			<param name="bgcolor" value="#ffffff" />
			<param name="movie" value="'.$uc_avatarflash.'" />
			<param name="menu" value="false" />
			<embed src="'.$uc_avatarflash.'" quality="high" bgcolor="#ffffff" width="450" height="253" name="mycamera" align="middle" allowScriptAccess="always" allowFullScreen="false" scale="exactfit"  wmode="transparent" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer" />
		</object>';
            return $result;
        } else {
            return array(
            'width', '450',
            'height', '253',
            'scale', 'exactfit',
            'src', $uc_avatarflash,
            'id', 'mycamera',
            'name', 'mycamera',
            'quality','high',
            'bgcolor','#ffffff',
            'wmode','transparent',
            'menu', 'false',
            'swLiveConnect', 'true',
            'allowScriptAccess', 'always'
            );
        }
    }

    /**
     * 头像上传第一步，上传原文件到临时文件夹（ok）
     *
     * @return string
     */
    function uploadavatarAction() {
        header("Expires: 0");
        header("Cache-Control: private, post-check=0, pre-check=0, max-age=0", FALSE);
        header("Pragma: no-cache");
        //header("Content-type: application/xml; charset=utf-8");
        $this->init_input($this->getgpc('agent', 'G'));
        $uid = $this->input('uid');
        if(empty($uid)) {
            return -1;
        }
        if(empty($_FILES['Filedata'])) {
            return -3;
        }
        
        $imgext = strtolower('.'. $this->fileext($_FILES['Filedata']['name']));
        if(!in_array($imgext, $this->config->imgtype)) {
            unlink($_FILES['Filedata']['tmp_name']);
            return -2;
        }
        
        if( $_FILES['Filedata']['size'] > ($this->config->uploadsize * 1024) ){
            unlink($_FILES['Filedata']['tmp_name']);
            return 'Inage is TOO BIG, PLEASE UPLOAD NO MORE THAN '. $this->config->uploadsize .'KB';
        }
        
        list($width, $height, $type, $attr) = getimagesize($_FILES['Filedata']['tmp_name']);
        
        $filetype = $this->config->imgtype[$type];
        $tmpavatar = realpath($this->config->tmpdir).'/upload'.$uid.$filetype;
        file_exists($tmpavatar) && unlink($tmpavatar);
        if(is_uploaded_file($_FILES['Filedata']['tmp_name']) && move_uploaded_file($_FILES['Filedata']['tmp_name'], $tmpavatar)) {
            list($width, $height, $type, $attr) = getimagesize($tmpavatar);
            if($width < 10 || $height < 10 || $type == 4) {
                unlink($tmpavatar);
                return -2;
            }
        } else {
            unlink($_FILES['Filedata']['tmp_name']);
            return -4;
        }

        $avatarurl = $this->config->uc_api. '/'. $this->config->tmpdir. '/upload'.$uid.$filetype;

        return $avatarurl;
    }
    
    /**
     * 头像上传第二步，上传到头像存储位置
     *
     * @return string
     */
    function rectavatarAction() {
        header("Expires: 0");
        header("Cache-Control: private, post-check=0, pre-check=0, max-age=0", FALSE);
        header("Pragma: no-cache");
        header("Content-type: application/xml; charset=utf-8");
        $this->init_input($this->getgpc('agent'));
        $uid = abs((int)$this->input('uid'));
        if( empty($uid) || 0 == $uid ) {
            return '<root><message type="error" value="-1" /></root>';
        }

        $avatarpath = $this->get_avatar_path($uid) ;
        $avatarrealdir  = realpath( $this->config->avatardir. DIRECTORY_SEPARATOR . $avatarpath );
        if(!is_dir( $avatarrealdir )) {
            $this->make_avatar_path( $uid, realpath($this->config->avatardir) );
        }
        $avatartype = $this->getgpc('avatartype', 'G') == 'real' ? 'real' : 'virtual';
        
        $avatarsize = array( 1 => 'big', 2 => 'middle', 3 => 'small');
        
        $success = 1;
        
        foreach( $avatarsize as $key => $size ){
            $avatarrealpath = realpath( $this->config->avatardir) . DIRECTORY_SEPARATOR. $this->get_avatar_filepath($uid, $size, $avatartype);
            $avatarcontent = $this->_flashdata_decode($this->getgpc('avatar'.$key, 'P'));
            if(!$avatarcontent){
                $success = 0;
                return '<root><message type="error" value="-2" /></root>';
                break;
            }
            $writebyte = file_put_contents( $avatarrealpath, $avatarcontent, LOCK_EX );
            if( $writebyte <= 0 ){
                $success = 0;
                return '<root><message type="error" value="-2" /></root>';
                break;
            }
            $avatarinfo = getimagesize($avatarrealpath);
            if(!$avatarinfo || $avatarinfo[2] == 4 ){
                $this->clear_avatar_file( $uid, $avatartype );
                $success = 0;
                break;
            }
        }

        //原uc bugfix  gif/png上传之后不能删除
        foreach ( $this->config->imgtype as $key => $imgtype ){
            $tmpavatar = realpath($this->config->tmpdir.'/upload'. $uid. $imgtype);
            file_exists($tmpavatar) && unlink($tmpavatar);
        }
        
        if($success) {
            return '<?xml version="1.0" ?><root><face success="1"/></root>';
        } else {
            return '<?xml version="1.0" ?><root><face success="0"/></root>';
        }
    }
    
    /**
     * flash data decode
     * 来源：Ucenter
     * 
     * @param string $s
     * @return unknown
     */
    protected function _flashdata_decode($s) {
        $r = '';
        $l = strlen($s);
        for($i=0; $i<$l; $i=$i+2) {
            $k1 = ord($s[$i]) - 48;
            $k1 -= $k1 > 9 ? 7 : 0;
            $k2 = ord($s[$i+1]) - 48;
            $k2 -= $k2 > 9 ? 7 : 0;
            $r .= chr($k1 << 4 | $k2);
        }
        return $r;
    }
    

    /**
     * 获取指定uid的头像规范存放目录格式
     * 来源：Ucenter base类的get_home方法
     * 
     * @param int $uid uid编号
     * @return string 头像规范存放目录格式
     */
    public function get_avatar_path($uid) {
        $uid = sprintf("%09d", $uid);
        $dir1 = substr($uid, 0, 3);
        $dir2 = substr($uid, 3, 2);
        $dir3 = substr($uid, 5, 2);
        return $dir1.'/'.$dir2.'/'.$dir3;
    }

    /**
     * 在指定目录内，依据uid创建指定的头像规范存放目录
     * 来源：Ucenter base类的set_home方法
     * 
     * @param int $uid uid编号
     * @param string $dir 需要在哪个目录创建？
     */
    public function make_avatar_path($uid, $dir = '.') {
        $uid = sprintf("%09d", $uid);
        $dir1 = substr($uid, 0, 3);
        $dir2 = substr($uid, 3, 2);
        $dir3 = substr($uid, 5, 2);
        !is_dir($dir.'/'.$dir1) && mkdir($dir.'/'.$dir1, 0777);
        !is_dir($dir.'/'.$dir1.'/'.$dir2) && mkdir($dir.'/'.$dir1.'/'.$dir2, 0777);
        !is_dir($dir.'/'.$dir1.'/'.$dir2.'/'.$dir3) && mkdir($dir.'/'.$dir1.'/'.$dir2.'/'.$dir3, 0777);
    }

    /**
     * 获取指定uid的头像文件规范路径
     * 来源：Ucenter base类的get_avatar方法
     *
     * @param int $uid
     * @param string $size 头像尺寸，可选为'big', 'middle', 'small'
     * @param string $type 类型，可选为real或者virtual
     * @return unknown
     */
	public function get_avatar_filepath($uid, $size = 'big', $type = '') {
		$size = in_array($size, array('big', 'middle', 'small')) ? $size : 'big';
		$uid = abs(intval($uid));
		$uid = sprintf("%09d", $uid);
		$dir1 = substr($uid, 0, 3);
		$dir2 = substr($uid, 3, 2);
		$dir3 = substr($uid, 5, 2);
		$typeadd = $type == 'real' ? '_real' : '';
		return  $dir1.'/'.$dir2.'/'.$dir3.'/'.substr($uid, -2).$typeadd."_avatar_$size.jpg";
	}
	
	/**
	 * 一次性清空指定uid用户已经存储的头像
	 *
	 * @param int $uid
	 */
	public function clear_avatar_file( $uid ){
	    $avatarsize = array( 1 => 'big', 2 => 'middle', 3 => 'small');
	    $avatartype = array( 'real', 'virtual' );
	    foreach ( $avatarsize as $size ){
	        foreach ( $avatartype as $type ){
	            $avatarrealpath = realpath( $this->config->avatardir) . DIRECTORY_SEPARATOR. $this->get_avatar_filepath($uid, $size, $type);
	            file_exists($avatarrealpath) && unlink($avatarrealpath);
	        }
	    }
	    return true;
	}
	
	
	/**
     * dz经典加解密函数
     * 来源：Discuz! 7.0
     * 依赖性：可独立提取使用
     *
     * @param string $string 要加密/解密的字符串
     * @param string $operation 操作类型，可选为'DECODE'（默认）或者'ENCODE'
     * @param string $key 密钥，必须传入，否则将中断php脚本运行。
     * @param int $expiry 有效期
     * @return string
     */
    public static function authcode($string, $operation = 'DECODE', $key, $expiry = 0) {

        $ckey_length = 4;	// 随机密钥长度 取值 0-32;
        // 加入随机密钥，可以令密文无任何规律，即便是原文和密钥完全相同，加密结果也会每次不同，增大破解难度。
        // 取值越大，密文变动规律越大，密文变化 = 16 的 $ckey_length 次方
        // 当此值为 0 时，则不产生随机密钥

        //取消UC_KEY，改为必须传入$key才能运行
        if(empty($key)){
            exit('PARAM $key IS EMPTY! ENCODE/DECODE IS NOT WORK!');
        }else{
            $key = md5($key);
        }


        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';

        $cryptkey = $keya.md5($keya.$keyc);
        $key_length = strlen($cryptkey);

        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
        $string_length = strlen($string);

        $result = '';
        $box = range(0, 255);

        $rndkey = array();
        for($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        for($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if($operation == 'DECODE') {
            if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc.str_replace('=', '', base64_encode($result));
        }

    }
	
    /**
     * 获取$_GET/$_POST/$_COOKIE/$_REQUEST数组的指定索引变量(ok)
     * 来源：Ucenter
     * 依赖性：可独立提取使用
     *
     * @param string $k 指定索引
     * @param string $var 获取来源。默认为'R'（即$_REQUEST），可选值'G'/'P'/'C'（对应$_GET/$_POST/$_COOKIE）
     * @return mixed
     */
    public static function getgpc($k, $var='R') {
        switch($var) {
            case 'G': $var = &$_GET; break;
            case 'P': $var = &$_POST; break;
            case 'C': $var = &$_COOKIE; break;
            case 'R': $var = &$_REQUEST; break;
        }
        return isset($var[$k]) ? $var[$k] : NULL;
    }
    
    /**
     * 转义处理，改动自daddslashes函数(ok)
     * 来源：Ucenter
     * 依赖性：需要修改才能独立使用
     * 
     * @param string $string
     * @param int $force
     * @param bool $strip
     * @return mixed
     */
    public static function addslashes($string, $force = 0, $strip = FALSE) {

        if(!ini_get('magic_quotes_gpc') || $force) {
            if(is_array($string)) {
                $temp = array();
                foreach($string as $key => $val) {
                    $key = addslashes($strip ? stripslashes($key) : $key);
                    $temp[$key] = self::addslashes($val, $force, $strip);
                }
                $string = $temp;
                unset($temp);
            } else {
                $string = addslashes($strip ? stripslashes($string) : $string);
            }
        }
        return $string;
    }
    
    /**
     * 返回文件的扩展名
     * 来源：Discuz!
     * 依赖性：可独立提取使用
     * 
     * @param string $filename 文件名
     * @return string
     */
    public static function fileext($filename) {
        return trim(substr(strrchr($filename, '.'), 1, 10));
    }
    
    
    /**
     * 获取指定对象或者指定索引对象的实例。没有则新建一个并且存储起来。
     *
     * @param string $classname 类名
     * @param string $index 索引，默认等同于$classname
     */
    public static function getInstanceOf( $classname , $index = null ){
        if( null === $index ){
            $index = $classname;
        }
        if( isset( self::$_objectInstance[$index] ) ){
            $instance = self::$_objectInstance[$index];
            if( !($instance instanceof $classname) ){
                throw new Exception( "Key {$index} has been tied to other thing." );
            }
        }else{
            $instance = new $classname();
            self::$_objectInstance[$index] = $instance;
        }
        return $instance;
    }

}