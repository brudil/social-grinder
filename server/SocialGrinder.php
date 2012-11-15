<?php
//Social Grinder - A flat-file social activity aggregator.
//Made by Brudil
error_reporting(E_ALL);
ini_set("display_errors", 1);

$time_start = microtime(true);

function __autoload($class_name) {
    include 'modules/' . ucfirst($class_name) . '.php';
}
class SocialGrinder{

	public $config = null;
	public $stream_settings = null;
	public $selected_stream = null;
	public $accounts = null;


	public function __construct(){
		$this->set_stream();
		$this->load_config('config/config.json');
		$this->get_stream_activity();
	}

	public function load_config($location){
		$config_file_handle = fopen($location, 'r');
		$config_file = fread($config_file_handle, filesize($location));
		$this->config = json_decode($config_file, true);
		$this->stream_settings = $this->config['streams'][$this->selected_stream];
		$this->accounts = $this->config['accounts'];
	}

	private function set_stream(){
		$this->selected_stream = $_GET['stream'];
		
	}

	private function get_stream_activity(){
		$module_objects = array();
		foreach ($this->stream_settings['accounts'] as $module) {
			$module_objects[$module] = new $this->accounts[$module]['module']($this->accounts[$module]['settings']);
		
		}
	}



}

interface Module{

	private $settings;

	public function __construct($settings);

}

$SG = new SocialGrinder();


print_r($SG->config['streams'][$SG->selected_stream]);



$time_end = microtime(true);
$time = $time_end - $time_start;
echo $time;
