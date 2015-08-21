<?php

class FriendsDatabase
{

	const DATA_FILE = "friends.json";
	const DATA_DIR  = "data";

	private $snapchat = null;
	private $_friends = array();

	private $status = [
		0 => "confirmed",
		1 => "unconfirmed",
		2 => "blocked",
		3 => "deleted"
	];

	public function __construct($snapchat)
	{
		$this->snapchat = $snapchat;
		$friendList = $this->snapchat->getFriends(false);
		$friendData = $this->snapchat->getFriends(true);

		if (is_file(self::DATA_FILE))
			$this->_friends = json_decode(file_get_contents(self::DATA_FILE), true);
	}

	public function update()
	{
		$snapchatData 		= $this->snapchat->getUpdates();
		$friendData 		= $this->snapchat->getFriends(true);
		$addedFriendsData 	= $this->snapchat->getAddedFriends(true);

		foreach ($friendData as $friend) {
			if (!$this->isFriend($friend->name)) {
				$this->_friends[$friend->name] = [
					"name" => $friend->name,
					"score" => $this->snapchat->getFriendScores($friend->name)[$friend->name]
				];
			}
			if (array_key_exists($friend->name, $addedFriendsData)) {
				$this->_friends[$friend->name]["added_timestamp"] = round($addedFriendsData[$friend->name]->ts / 1000);
				$this->_friends[$friend->name]["added_timestamp_r"] = date("d.m.Y H:i:s", $this->_friends[$friend->name]["added_timestamp"]);
			}
			$this->_friends[$friend->name]["display"] = $friend->display;
			$this->_friends[$friend->name]["type"] = $friend->type;
			$this->_friends[$friend->name]["type_r"] = $this->status[$friend->type];

			ksort($this->_friends[$friend->name]);
		}

		foreach ($this->_friends as $friend) {
			if (!array_key_exists($friend['name'], $friendData)) {
				unset($this->_friends[$friend['name']]);
			}
		}

		ksort($this->_friends);
		$this->saveFile($this->_friends, self::DATA_FILE);
	}

	public function updateScores()
	{
		$friendList = array_keys($this->_friends);
		$friendScores = $this->snapchat->getFriendScores($friendList);

		foreach ($friendList as $friend) {
			if (array_key_exists($friend, $friendScores))
				$this->_friends[$friend]["score"] = $friendScores[$friend];

			ksort($this->_friends[$friend]);
		}

		$this->saveFile($this->_friends, self::DATA_FILE);
	}

	public function updateActiveFriends()
	{
		$activeFriends = $this->getActiveFriends();

		foreach ($this->_friends as $friend) {
			$this->_friends[$friend['name']]["active"] = in_array($friend['name'], $activeFriends);
			ksort($this->_friends[$friend['name']]);
		}

		$this->saveFile($this->_friends, self::DATA_FILE);
	}

	public function addFriend($friend, $time = 0, $display = null, $age = null, $location = null)
	{
		$data = [
			"name" => $friend,
			"added_timestamp" => (($time == 0) ? time() : $time),
		];

		if (!is_null($display))
			$data["display"] = $display;

		if (!is_null($age))
			$data["age"] = $age;

		if (!is_null($location))
			$data["location"] = $location;

		$this->_friends[$friend] = $data;
		$this->saveFile($this->_friends, self::DATA_FILE);
	}

	public function removeFriend($friend)
	{
		if ($this->isFriend($friend))
		{
			unset($this->_friends[$friend]);
			$this->saveFile($this->_friends, self::DATA_FILE);
		}
	}

	public function isFriend($friend)
	{
		return array_key_exists($friend, $this->_friends);
	}

	public function set($friend, $data, $value)
	{
		if (is_array($data))
		{
			$this->_friends[$friend] = $data;
			$this->saveFile($this->_friends, self::DATA_FILE);
		}
		else
		{
			$this->_friends[$friend][$data] = $value;
			ksort($this->_friends[$friend]);
			$this->saveFile($this->_friends, self::DATA_FILE);
		}
	}

	public function get($friend, $key = NULL)
	{
		if ($this->isFriend($friend))
		{
			if (is_null($key))
			{
				return $this->_friends[$friend];
			}
			elseif (array_key_exists($key, $this->_friends[$friend]))
			{
				return $this->_friends[$friend][$key];
			}
			else
			{
				return NULL;
			}
		}
		else
		{
			return NULL;
		}
	}

	public function getFriends()
	{
		return array_keys($this->_friends);
	}

	public function getActiveFriends()
	{
		$activeFriends		= array();
		$dataFile 			= self::DATA_DIR . DIRECTORY_SEPARATOR . "active_friends.json";

		if (is_file($dataFile))
			$activeFriends = json_decode(file_get_contents($dataFile), true);

		$conversations 		= $this->snapchat->getConversations();
		$friendStories 		= $this->snapchat->getFriendStories();
		$myStories 			= $this->snapchat->getMyStories();

		foreach ($conversations as $conversation) {
			foreach ($conversation->participants as $participant) {
				if (!in_array($participant, $activeFriends)) {
					$activeFriends[] = $participant;
				}
			}
		}

		foreach ($friendStories as $story) {
			if (!in_array($story->username, $activeFriends)) {
				$activeFriends[] = $story->username;
			}
		}

		foreach ($myStories as $story) {
			foreach ($story->story_notes as $notes) {
				if (!in_array($notes->viewer, $activeFriends)) {
					$activeFriends[] = $notes->viewer;
				}
			}
		}

		sort($activeFriends);
		$this->saveFile($activeFriends, $dataFile);
		return $activeFriends;
	}

	private function saveFile($data, $filename)
	{
		$this->saveJSON($data, $filename);
	}

	public function saveJSON($data, $filename)
	{
		file_put_contents($filename, JSON::prettify($data));
	}
}