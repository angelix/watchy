<?php define('WATCHY_VERSION', '1.9 Dev 2');
/*

	A Basic Watchdog for PHP Development.
	
	Angelos Veglektsis <angelix@vegle.gr> <angelos.veglektsis@ogilvy.com>
	
	SQL SCHEMA
	
	CREATE TABLE IF NOT EXISTS `watchy` (
		`id` int(11) NOT NULL AUTO_INCREMENT,
		`log` text CHARACTER SET utf8 NOT NULL,
		`created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;


	Methods:
	
	- log
	- alert
	- query
	- sanitize
	
	Alias:
	- q -> query
	- s -> sanitize
	
	Settings:
		- name -> The name of the Project Watchy is watching.
		- $emails -> Email or array of emails notifications will be send to
		- from_email -> The from email address , eg 'OgilvyLabs <ogilvit@gmail.com>'
		- dispatch -> Where to keep the log , default to both database and email
		- log_queries -> if you want to log the db queries , deufalt to false
		- auto_sanitize -> if you want to auto sanitize the sql parameters , default to true
	
	
	USAGE:
	
	include 'watchy.php';

	$w = new Watchy('Project Name');

	$sql = "SELECT * FROM watchy";

	$result = $w->query($sql);
	
	$sql = "SELECT * FROM watchy WHERE id = '%d'";
	$result = $w->query($sql , 4);

	$w->log('test');
	$w->log($result);

	PRECAUTIONS:
		Make sure PHP is running with Magic Quotes turned off.
		
		To do this add the following line to .htaccess. In case Magic Quotes is On, Watchy will try to auto revert the automatic process of Magic Quotes
		php_flag magic_quotes_gpc Off
*/

define('WATCHY_BOTH', 0);
define('WATCHY_EMAIL', 1);
define('WATCHY_DATABASE', 2);

// In case magic_quotes is ON.
if (get_magic_quotes_gpc()) {
	$process = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
	while (list($key, $val) = each($process)) {
		foreach ($val as $k => $v) {
			unset($process[$key][$k]);
			if (is_array($v)) {
				$process[$key][stripslashes($k)] = $v;
				$process[] = &$process[$key][stripslashes($k)];
			} else {
				$process[$key][stripslashes($k)] = stripslashes($v);
			}
		}
	}
	unset($process);
}

class Watchy{
		
	protected $project;
	protected $emails;
	protected $dispatch;
	protected $log_queries;
	protected $from_email;
	protected $auto_sanitize;
	protected $queries = NULL;
	protected $email_content = '';
	protected $flood = 0;
	protected $flood_control = 0;
	protected $last_query = '';
	protected $error_handler = true;
	protected $destroy = false;
	
	function __construct($name = 'Watchy' , $emails = array() , $from_email = 'OgilvyLabs <ogilvit@gmail.com>', $error_handler = true, $dispatch = WATCHY_BOTH , $log_queries = false , $auto_sanitize = true , $flood_control = 10){
		$this->project = $name;
		$this->dispatch = $dispatch;
		$this->log_queries = $log_queries;
		$this->from_email = $from_email;
		$this->auto_sanitize = $auto_sanitize;
		$this->flood_control = $flood_control;
		$this->error_handler = $error_handler;
		
		if($this->error_handler){
			$callback = array($this , 'error_handler');
			set_error_handler($callback);

			$callback = array($this , 'exception_handler');
			set_exception_handler($callback);
		}
		
		$callback = array($this , '__destruct');
		register_shutdown_function($callback); //use this because object constructors won't work in fatal errors
		
		if(is_array($emails)){
			$this->emails = $emails;
		}else{
			$this->emails = array($emails);
		}
	}
	
	public function error_handler($errno , $errstr , $errfile , $errline , $errcontext ){ //beuatify this
		$log = 'Error Number: '.$errno.' String '.$errstr.' - '.$errfile.' - '.$errline.' - '.$errcontext;
		$this->log($log , 'PHP ERROR' , true);

		return null;
	}
	
	public function exception_handler($e){ //beuatify this
		$this->log($e , 'PHP Exception' , true);

		return null;
	}
	
	public function log($log , $title = null , $alert = false){
		if($this->dispatch == WATCHY_DATABASE || $this->dispatch == WATCHY_BOTH){
		
			$log = '<pre>'.var_export($log,true).'</pre>';
			
			if($alert){
				$d = debug_backtrace();
				
				$trace = '';
				unset($d[0]);
				foreach($d as $jump){
					$file = isset($jump['file']) ? $jump['file'] : 'unknown';
					$line = isset($jump['line']) ? $jump['line'] : 'unknown';
					$trace .= $file.' ('.$line.'): '.$title.'<br />';
				}
				
				$log .= '<br /><strong>TRACE:</strong><br />'.$trace;
			}
		
			$sql = sprintf("INSERT INTO watchy (id , log , created ) VALUES ( NULL, '%s' , CURRENT_TIMESTAMP);", @mysql_real_escape_string(print_r($log,TRUE)));
			
			$result = @mysql_query($sql);

			if(!$result){
				$this->email(var_export($log,TRUE)."<br /><br />".mysql_error());
			}
		}
		
		if($this->dispatch == WATCHY_EMAIL || $this->dispatch == WATCHY_BOTH || $alert == true){
			$this->email(print_r($log,TRUE));
		}

		return $log;
	}
	
	public function alert($alert , $title = null){
		
		$this->log($alert , $title , true);
	}
	
	public function query(){
		if(func_num_args() > 1){
			$args = func_get_args();
			if($this->auto_sanitize){
				for($i = 1 ; $i < count($args) ; $i++){ //sanitize only the parameters, not the sql query
					$args[$i] = $this->sanitize($args[$i]);
				}
			}
			$sql = call_user_func_array('sprintf' , $args);
		}else{
			$sql = func_get_arg(0);
		}
		
		if($this->log_queries){
			$this->queries .= '<br />'.$sql;
		}
		
		$this->last_query = $sql;
		$result = mysql_query($sql);
		
		if (!$result){
			$mysql_error = mysql_error();
			$this->log('Error: '.$mysql_error." <br />SQL: ".$sql);
			if(ini_get('display_errors') == 'On'){
				echo '<b>SQL Error:</b> '.$mysql_error.'<br />';
			}
		}
		return $result;
	}
	
	public function q(){
		$args = func_get_args();
		return call_user_func_array(array($this, 'query') , $args);
	}
	
	public function sanitize($value){
		return mysql_real_escape_string(htmlspecialchars($value));
	}
	
	public function s($value){
		return $this->sanitize($value);
	}
	
	public function email($content){
		if($this->flood_control == 0 || $this->flood < $this->flood_control){
			$this->email_content .= $content;
			$this->email_content .= "<br /><br /><hr /><br /><br />";
			$this->flood++;
		}
	}
	
	public function get_last_query(){
		return $this->last_query;
	}

	public function sanitize_disable(){
		$this->auto_sanitize = false;
	}
	
	public function sanitize_enable(){
		$this->auto_sanitize = true;
	}
	
	public function send_email($content = NULL){
		$headers = "MIME-Version: 1.0\n";
		$headers .= "Content-type: text/html; charset=utf-8\n";
		$headers .= "X-mailer: php\n"; 
		$headers .= "From: ".$this->from_email."\n";
		$subject = 'Watchy - '.$this->project.' - '.date('l jS \of F Y h:i:s A');
		
		if($content == NULL){
			$content = $this->email_content;
		}
		
		foreach($this->emails as $email){
			@mail($email , $subject , $content , $headers);
		}
	}
		
	function __destruct(){
		if($this->destroy == false){
			$this->destroy = true;
			if($this->log_queries){
				if($this->queries != NULL){
					$this->log($this->queries);
				}
			}
		
			if($this->email_content != ''){
				$this->send_email();
			}
		}
	}
}