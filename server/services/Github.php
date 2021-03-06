<?php
class Github implements Service{

	private $settings;

	public function __construct($settings){
		$this->settings = $settings;
	}

	public function get_items($count){

		$response = json_decode(ServiceUtilities::get_URL_contents("https://api.github.com/users/" . $this->settings['username'] . "/events/public"), true);
		$response_array = array();
		foreach ($response as $key => $item) {
			$response_array[] = $this->parse_activty_item($item);
		}

		return $response_array;
	}	


	public function parse_activty_item($dirty_item){
		$item = $dirty_item;

		$item['_SG-date'] = strtotime($dirty_item['created_at']);

		return $item;
	}


}