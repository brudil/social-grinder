<?php
class Twitter implements Service{

	private $settings;

	public function __construct($settings){
		$this->settings = $settings;
	}

	public function get_items($count){

		$response = json_decode(ServiceUtilities::get_URL_contents("http://api.twitter.com/1/statuses/user_timeline.json?screen_name=" . $this->settings['username'] . "&count=" . $count . "&include_rts=" . $this->settings['include-retweets'] . "&trim_user=true&exclude_replies=true"), true);
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