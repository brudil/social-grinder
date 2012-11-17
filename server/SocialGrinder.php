<?php
//Social Grinder - A flat-file social activity aggregator.
//Made by Brudil

//Make PHP show us all the errors
error_reporting(E_ALL);
ini_set("display_errors", 1);

//Start the timer
ElapsedTime::start_timer();

//Set our current server location
date_default_timezone_set('Europe/London');

//When a class that isn't found is initialised, we look in the services folder
function __autoload($class_name) {
    include 'services/' . ucfirst($class_name) . '.php';
}

$view = new View();

class SocialGrinder{

	//Stores the config as an array.
	public $config = null;
	//Settings array for the selected stream taken from $config
	public $stream_settings = null;
	//The selected stream
	public $selected_stream = null;
	//The added accounts, taken from $config
	public $accounts = null;
	//Array of the cache/manifest.json file. Storing when services where updated for streams
	public $cache_manifest = null;


	public function __construct(){

		//We load up the config
		$this->load_config();

		//Find the selected stream
		$this->set_stream();

		//Load the cache manifest from the file
		$this->load_cache_manifest();
	}

	//Load the config file from config/config.json and it to $config
	public function load_config(){
		//Location of the cache manifest file
		$location = 'config/config.json';

		$config_file_handle = fopen($location, 'r');
		$config_file = fread($config_file_handle, filesize($location));
		$this->config = json_decode($config_file, true);
		if ($this->config === null
		    && json_last_error() !== JSON_ERROR_NONE) {
		    View::show_error("Config can't parse", "Check for missing commas, or too many braces");
		}
		$this->accounts = $this->config['accounts'];
	}

	public function load_cache_manifest(){
		$location = 'cache/manifest.json';
		//If the cache folder doesn't exit, attempt to create it
		if(!is_dir('cache')){
			if(!mkdir("cache", 0700)){
				View::show_error('Can\'t create \'cache\' folder', 'You add extra permissions, or create it yourself.');
			}
		}
		//If the files does exit, decode it and set it to $cache_manifest
		if(file_exists($location)){
			$manifest_file_handle = fopen($location, 'r');
			$manifest_file = fread($manifest_file_handle, filesize($location));
			$this->cache_manifest = json_decode($manifest_file, true);
		//If not, set $cache_manifest to a blank array
		}else{
			$this->cache_manifest = array();
		}
	}

	//Save $cache_manifest to a file.
	private function save_cache_manifest(){

		//Location of the cache manifest file
		$location = 'cache/manifest.json';
		//Open it
		$manifest_file_handle = fopen($location, 'w');

		//Write the $cache_manifest array to the file, encoding it to json.
		fwrite($manifest_file_handle, json_encode($this->cache_manifest));
	}

	//Sets the current stream based on the 'stream' URL query pram
	private function set_stream(){

		if(!isset($_GET['stream'])){
			View::show_error('No stream given!', 'No stream name was passed.');
		}else{
			$stream = $_GET['stream'];
		}
		$this->selected_stream = $stream;

		//Set $stream_settings to the settings for the selected stream
		if(!isset($this->config['streams'][$this->selected_stream])){
			View::show_error('Stream does not exist!', 'There is no stream called \'' . $stream . '\' in your config.');
		}
		$this->stream_settings = $this->config['streams'][$this->selected_stream];
		
	}

	public function get_stream_activity(){
		//Fill the $cache_times array with the cache time of each service used in the selected stream
		$cache_times = array();
		foreach ($this->stream_settings['accounts'] as $service) {
			$cache_times[] = $this->accounts[$service]['cache'];
		}

		//If the selected stream doesn't exist in the manifest
		if(!isset($this->cache_manifest[$this->selected_stream])){
			//Add it to the manifest
			$this->cache_manifest[$this->selected_stream] = array('updated'=> 0, 'accounts'=> array());
			//Update the stream, and display it's result
			return $this->update_social_stream();
		//If the stream does exit in the manifest
		}else{
			//Check if there's been enough time between the last update and the shortest cache time of the used accounts
			if(time() > $this->cache_manifest[$this->selected_stream]['updated'] + (min($cache_times)*60)){
				//If there has, we need to run the update!
				return $this->update_social_stream();
			}else{
				//If not, we can just display the pre-cached file.
				return $this->get_account_cache('');
			}
		}
	}

	private function update_social_stream(){
		$activity = array();
		//For every account we use in the current stream
		foreach ($this->stream_settings['accounts'] as $account) {
			//See if the account has been referenced in the manifest
			if(!isset($this->cache_manifest[$this->selected_stream]['accounts'][$account])){
				//If not, add it.
				$this->cache_manifest[$this->selected_stream]['accounts'][$account] = 0;
			}
			//Check if enough time has past since we last updated it to when we need to.
			if(time() > $this->cache_manifest[$this->selected_stream]['accounts'][$account] + ($this->accounts[$account]['cache']*60)){
				//If long enough has past, update this account.
				$activity = array_merge($activity, $this->update_account_cache($account));
			}else{
				//If not, just return the cached version
				$activity = array_merge($activity, $this->get_account_cache($account));
			}
		}
		//We now have (accounts*count) items in our $activity array.
		//Sort them by time, using our _SG-date Unix time-stamp. Services should add this to each item.
		usort($activity, function($a, $b) {
		    return $b['_SG-date'] - $a['_SG-date'];
		});

		//Now we limit to the needed count
		$limted_activity = array_slice($activity, 0, $this->stream_settings['count']);

		//Cache this new stream activity to a file
		$this->save_stream_cache($limted_activity);

		//Save the manifest
		$this->save_cache_manifest();

		//Return the required, and now updated, activity
		return $limted_activity;
	}

	//Saves stream cache to file.
	private function save_stream_cache($json){
		//The file naming format for stream cache files is /caches/_{stream-name}.json
		$location = 'cache/' . '_' . $this->selected_stream . '.json';

		//Open the stream cache
		$stream_cache_handle = fopen($location, 'w');

		//Write the new cache, encoding the array to JSON
		fwrite($stream_cache_handle, json_encode($json));

		//Update the last updated time to now in the manifest
		$this->cache_manifest[$this->selected_stream]['updated'] = time();
	}

	//Updates a account and caches it.
	private function update_account_cache($account){

		//Get the name of the service, capitalizing the first character to match the class
		$name = (ucfirst($this->accounts[$account]['service']));

		//Initialise the object, passing in the settings array for the account
		$account_service = new $name($this->accounts[$account]['settings']);

		//Call the get_items method, asking for max count number of items
		$account_json = $account_service->get_items($this->stream_settings['count']);

		//Save the account items to a cache file
		$this->save_account_cache($account, $account_json);

		//Return the account items
		return $account_json;
	}

	//Saves an account items to a cache
	private function save_account_cache($account, $json){

		//The file naming format for account caches files is /caches/{account_name}_{stream-name}.json
		$location = 'cache/' . $account . '_' . $this->selected_stream . '.json';

		//Open the account cache
		$account_cache_handle = fopen($location, 'w');

		//Write the new cache, encoding the array to JSON
		fwrite($account_cache_handle, json_encode($json));

		//Update the last updated time to now in the manifest
		$this->cache_manifest[$this->selected_stream]['accounts'][$account] = time();
	}

	//Gets a cached account activity file, returns a array of items
	private function get_account_cache($account){

		//The file naming format for account caches files is /caches/{account_name}_{stream-name}.json
		$location = 'cache/' . $account . '_' . $this->selected_stream . '.json';

		//Open the account cache
		$account_cache_handle = fopen($location, 'r');

		//Set contents to a string
		$account_cache = fread($account_cache_handle, filesize($location));

		//Return it as decoded JSON
		return json_decode($account_cache, true);

	}


}

//Simple interface enforcing the two methods services must have
interface Service{

	public function __construct($settings);
	public function get_items($count);
}

//Class that deals with displaying errors and JSON
class View{

	//HTML of an error page
	private static $html_error = "<!doctype html><html><head><meta charset='utf-8'><title>Social Grinder - Error!</title><style>body{background: #EFEFEF;padding-top:60px;font-family:sans-serif;}div{max-width:650px;padding:25px;width:80%%;margin:0 auto;box-shadow:0 0 8px 0 rgba(44, 44, 44, 0.8);border-radius:2px;border:1px solid #EEE;background:white;}</style></head><body><div><h1>%s</h1><p>%s</p></div></body></html>";
	
	//Static method for displaying script errors. Outputs JSON if requested via AJAX
	public static function show_error($title, $description){
		if($this->is_ajax()){
			echo json_encode(array('error'=> array($title, $description)));
		}else{
			printf(View::$html_error, $title, $description);
		}
		exit;
	}

	//Displays JSON. Outputs the correct headers, adding a few other bits of data including version and run time
	public function display_json($settings, $json){
		header('Content-type: application/json');
		if($settings['cors']){
			header("Access-Control-Allow-Origin: *");
		}
		if($settings['client-cache'] != 0){
			header("Cache-Control: private, max-age=" . ($settings['client-cache']*60));
    		header("Expires: " . gmdate('r', time() + ($settings['client-cache']*60)));
		}
		echo json_encode(array('items'=> $json, 'run-time'=> ElapsedTime::get_time(), 'generated-by'=> get_about()));
	}

	//Returns true if the request was made via AJAX
	function is_ajax() {
		return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
		($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest'));
	}

}



//A static class that measures the scripts running time
class ElapsedTime{
	private static $time_start;

	public static function start_timer(){
		ElapsedTime::$time_start = microtime(true);
	}

	public static function get_time(){
		return microtime(true) - ElapsedTime::$time_start;
	}

}

//Returns it's name and version
function get_about(){
	return "Social Grinder 1.0";
}


$SG = new SocialGrinder();

$view->display_json($SG->stream_settings, $SG->get_stream_activity());
