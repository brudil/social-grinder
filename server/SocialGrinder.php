<?php
//Social Grinder - A flat-file social activity aggregator.
//Made by Brudil
error_reporting(E_ALL);
ini_set("display_errors", 1);
$time_start = microtime(true);

date_default_timezone_set('Europe/London');
function __autoload($class_name) {
    include 'modules/' . ucfirst($class_name) . '.php';
}
class SocialGrinder{

	public $config = null;
	public $stream_settings = null;
	public $selected_stream = null;
	public $accounts = null;
	public $cache_manifest = null;


	public function __construct(){
		$this->load_config('config/config.json');
		$this->set_stream();
		$this->load_cache_manifest();
		$this->get_stream_activity();
		$this->save_cache_manifest();
	}

	public function load_config($location){
		$config_file_handle = fopen($location, 'r');
		$config_file = fread($config_file_handle, filesize($location));
		$this->config = json_decode($config_file, true);
		$this->accounts = $this->config['accounts'];
	}

	public function load_cache_manifest(){
		$location = 'cache/manifest.json';
		if(!is_dir('cache')){
			mkdir("cache", 0700);
		}
		if(file_exists($location)){
			$manifest_file_handle = fopen($location, 'r');
			$manifest_file = fread($manifest_file_handle, filesize($location));
			$this->cache_manifest = json_decode($manifest_file, true);
		}else{
			$this->cache_manifest = array();
		}
	}

	private function save_cache_manifest(){
		$location = 'cache/manifest.json';
		$manifest_file_handle = fopen($location, 'w');
		fwrite($manifest_file_handle, json_encode($this->cache_manifest));
	}

	private function set_stream(){
		if(isset($_GET['stream'])){
			$stream = $_GET['stream'];
		}else{
			$stream = $this->config['default-stream'];
		}
		$this->selected_stream = $stream;
		$this->stream_settings = $this->config['streams'][$this->selected_stream];
		
	}

	private function get_stream_activity(){
		$cache_times = array();
		foreach ($this->stream_settings['accounts'] as $module) {
			$cache_times[] = $this->accounts[$module]['cache'];
		}

		if(!isset($this->cache_manifest[$this->selected_stream])){
			$this->cache_manifest[$this->selected_stream] = array('updated'=> 0, 'accounts'=> array());
			$this->display_json($this->update_social_stream());
		}else{
			if(time() > $this->cache_manifest[$this->selected_stream]['updated'] + (min($cache_times)*60)){
				$this->display_json($this->update_social_stream());
			}else{
				$this->display_json($this->get_account_cache(''));
			}
		}
	}

	private function display_json($json){
		header('Content-type: application/json');
		if($this->stream_settings['cors']){
			header("Access-Control-Allow-Origin: *");
		}
		if($this->stream_settings['client-cache'] != 0){
			header("Cache-Control: private, max-age=" . ($this->stream_settings['client-cache']*60));
    		header("Expires: " . gmdate('r', time() + ($this->stream_settings['client-cache']*60)));
		}
		echo json_encode($json);

	}

	private function update_social_stream(){
		$module_objects = array();
		$activity = array();
		foreach ($this->stream_settings['accounts'] as $account) {
			if(!isset($this->cache_manifest[$this->selected_stream]['accounts'][$account])){
				$this->cache_manifest[$this->selected_stream]['accounts'][$account] = 0;
			}
			if(time() > $this->cache_manifest[$this->selected_stream]['accounts'][$account] + ($this->accounts[$account]['cache']*60)){
				$activity = array_merge($activity, $this->update_account_cache($account));
			}else{
				$activity = array_merge($activity, $this->get_account_cache($account));
			}
		}
		usort($activity, function($a, $b) {
		    return $b['_SG-date'] - $a['_SG-date'];
		});

		$limted_activity = array_slice($activity, 0, $this->stream_settings['count']);

		$this->save_stream_cache($limted_activity);
		$this->cache_manifest[$this->selected_stream]['updated'] = time();

		return $limted_activity;
	}


	private function save_stream_cache($json){
		$location = 'cache/' . '_' . $this->selected_stream . '.json';
		$stream_cache_handle = fopen($location, 'w');
		fwrite($stream_cache_handle, json_encode($json));
	}

	private function update_account_cache($account){
		$name = (ucfirst($this->accounts[$account]['module']));
		$account_module = new $name($this->accounts[$account]['settings']);
		$account_json = $account_module->get_items($this->stream_settings['count']);
		$this->save_account_cache($account, $account_json);
		return $account_json;
	}
	private function save_account_cache($account, $json){
		$location = 'cache/' . $account . '_' . $this->selected_stream . '.json';
		$account_cache_handle = fopen($location, 'w');
		fwrite($account_cache_handle, json_encode($json));
		$this->cache_manifest[$this->selected_stream]['accounts'][$account] = time();
	}
	private function get_account_cache($account){
		$location = 'cache/' . $account . '_' . $this->selected_stream . '.json';
		$account_cache_handle = fopen($location, 'r');
		$account_cache = fread($account_cache_handle, filesize($location));
		return json_decode($account_cache, true);

	}


}

interface Module{

	public function __construct($settings);
	public function get_items($count);

}

$SG = new SocialGrinder();




$time_end = microtime(true);
$time = $time_end - $time_start;
//echo $time;
