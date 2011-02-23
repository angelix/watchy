<?php
defined('ACCESS') or die('No direct script access.');
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
		
	protected $project = 'Watchy';
	protected $emails = array();
	protected $dispatch = WATCHY_BOTH;
	protected $log_queries = 0;
	
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
	
	function __construct($name , $dispatch = WATCHY_BOTH){
		$this->project = $name;
		$this->dispatch = $dispatch;
	}
	
	public function query($sql){
		if($this->log_queries){
			$this->queries .= '<br />'.$sql;
		}
		
		$result = mysql_query($sql);
		if (!$result){
			$this->log(mysql_error()." \n ".$sql);
		}
		return $result;
	}
	
	public function q($sql){
		return $this->query($sql);
	}
	
	public function email($content){
		$headers = "MIME-Version: 1.0\n";
		$headers .= "Content-type: text/html; charset=utf-8\n";
		$headers .= "X-mailer: php\n"; 
		$headers .= "From: OgilvyLabs <ogilvit@gmail.com>\n";
		$subject = 'Watchy - '.$this->project.' - '.date('l jS \of F Y h:i:s A');

		$to = 'angelix+watchy@vegle.gr';
		@mail($to,$subject,$content,$headers);
	}
		
	function __destruct(){
		if($this->log_queries){
			$this->email($this->queries);
		}
	}

}