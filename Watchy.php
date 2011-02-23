<?php 
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

	USAGE:
	
	include 'watchy.php';

	$w = new Watchy('Project Name');

	$sql = "SELECT * FROM watchy";

	$result = $w->query($sql);

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
	
	function __construct($name = 'Watchy' , $emails = array() , $from_email = 'OgilvyLabs <ogilvit@gmail.com>', $dispatch = WATCHY_BOTH , $log_queries = false){
		$this->project = $name;
		$this->dispatch = $dispatch;
		$this->emails = $emails;
		$this->log_queries = $log_queries;
	}
	
	public function query($sql){
		if($this->log_queries){
			$this->queries .= '<br />'.$sql;
		}
		
		$result = @mysql_query($sql);
		if (!$result){
			$this->log(mysql_error()." \n ".$sql);
		}
		return $result;
	}
	
	public function q($sql){
		return $this->query($sql);
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
		$headers .= "From: ".$from_email."\n";
		$subject = 'Watchy - '.$this->project.' - '.date('l jS \of F Y h:i:s A');
		
		foreach($this->emails as $email){
			@mail($email , $subject , $content , $headers);
		}
	}
		
	function __destruct(){
		if($this->log_queries){
			$this->email($this->queries);
		}
	}

}
