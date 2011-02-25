<?php define('WATCHY_VERSION', '1.0 Beta 1');
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

*/

define('WATCHY_BOTH', 0);
define('WATCHY_EMAIL', 1);
define('WATCHY_DATABASE', 2);

class Watchy{
		
	protected $project;
	protected $emails;
	protected $dispatch;
	protected $log_queries;
	protected $from_email;
	protected $auto_sanitize;
	protected $queries = NULL;
	
	function __construct($name = 'Watchy' , $emails = array() , $from_email = 'OgilvyLabs <ogilvit@gmail.com>', $dispatch = WATCHY_BOTH , $log_queries = false , $auto_sanitize = true){
		$this->project = $name;
		$this->dispatch = $dispatch;
		$this->log_queries = $log_queries;
		$this->from_email = $from_email;
		$this->auto_sanitize = $auto_sanitize;
		
		if(is_array($emails)){
			$this->emails = $emails;
		}else{
			$this->emails = array($emails);
		}
	}
	
	public function log($log){
		if($this->dispatch == WATCHY_DATABASE || $this->dispatch == WATCHY_BOTH){
			$sql = sprintf("INSERT INTO watchy (id , log , created ) VALUES ( NULL, '%s' , CURRENT_TIMESTAMP);", @mysql_real_escape_string(print_r($log,TRUE)));
			$result = @mysql_query($sql);

			if(!$result){
				$this->email(var_export($log,TRUE)."<br /><br />".mysql_error());
			}
		}
		
		if($this->dispatch == WATCHY_EMAIL || $this->dispatch == WATCHY_BOTH){
			$this->email(print_r($log,TRUE));
		}

		return $log;
	}
	
	public function alert($alert){
		
		$this->log($alert);
		
		if($this->dispatch == WATCHY_DATABASE){
			$this->email($alert);
		}
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
		
		$result = @mysql_query($sql);
		if (!$result){
			$this->log(mysql_error()." \n ".$sql);
		}
		return $result;
	}
	
	public function q(){
		$args = func_get_args();
		return call_user_func_array(array($this, 'query') , $args);
	}
	
	public function sanitize($value){
		return mysql_real_escape_string($value);
	}
	
	public function s($value){
		return $this->sanitize($value);
	}
	
	public function email($content){
		$headers = "MIME-Version: 1.0\n";
		$headers .= "Content-type: text/html; charset=utf-8\n";
		$headers .= "X-mailer: php\n"; 
		$headers .= "From: ".$this->from_email."\n";
		$subject = 'Watchy - '.$this->project.' - '.date('l jS \of F Y h:i:s A');
		
		foreach($this->emails as $email){
			@mail($email , $subject , $content , $headers);
		}
	}
		
	function __destruct(){
		if($this->log_queries){
			if($this->queries != NULL){
				$this->log($this->queries);
			}
		}
	}

}
