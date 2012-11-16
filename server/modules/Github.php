<?php
class Github implements Module{

	private $settings;

	public function __construct($settings){
		$this->settings = $settings;
	}

	public function get_items($count){

		$ch = curl_init("https://api.github.com/users/" . $this->settings['username'] . "/events/public");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		//curl_setopt ($ch, CURLOPT_CAINFO, dirname(__FILE__)."/cacert.pem");
		
		$response = json_decode(curl_exec($ch), true);
		curl_close($ch);

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