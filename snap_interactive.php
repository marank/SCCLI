<?php

require_once("SC-API/src/snapchat.php");

//////////// CONFIG ////////////
$username 	= ""; // Required. Your snapchat username
$password 	= ""; // Your snapchat password. Not required if you log in to snapchat via an existing auth token.
$auth_token = ""; // The auth-token you want to use for logging in.
$gEmail   	= ""; // Gmail account
$gPasswd  	= ""; // Gmail account password
$debug	  	= false; // Set this to true if you want to see all outgoing requests and responses from server
$cli 	  	= true; // Set this to true to get progress information printed out via echo (only use when running in a terminal).

$offline 	= false;
////////////////////////////////

echo "Interactive Snap-API\n";

$keepRunning = true;
$stdin = fopen('php://stdin', 'r');
$availableCommands = array("close", "get", "write", "fetch", "send", "friend", "sync", "set", "login", "snaps", "stories", "output");
$simpleCommands	   = array("close", "login", "write");
$aliases 		   = array("friends" => "friend",
						   "exit"    => "close",
						   "snap"	 => "snaps",
						   "story"	 => "stories",
						   "print"   => "output");

if ($argc > 1) {
	if ($argv[1] == "offline") {
		$offline = true;
		echo "Snap-API is in offline mode. Restart the script to go back online.\n";
	}
}

if (empty($username)) {
	echo "No username specified. Please login manually using the login command.\n";
} else {
	$snapchat = new Snapchat($username, $gEmail, $gPasswd, $debug, $cli);
	$snapchat->login($password, $auth_token);
	$snapchat->getUpdates();
}

while ($keepRunning) {
	echo ">> ";
	$userInput = fgets($stdin, 1024);
	$userInput = trim($userInput);
	$params = explode(" ", $userInput);

	if (in_array($params[0], $availableCommands)) {
		if (in_array($params[0], $simpleCommands)) {
			$params[0]($params);
		} else {
			$params[0]($params[1], $params);
		}
	} elseif (array_key_exists($params[0], $aliases)) {
		if (in_array($aliases[$params[0]], $simpleCommands)) {
			$aliases[$params[0]]($params);
		} else {
			$aliases[$params[0]]($params[1], $params);
		}
	} else {
		echo "$params[0] is not a valid command.\n";
	}
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////////////////////////////////////


function close($params) {
	global $keepRunning;
	$keepRunning = false;
}

function login($params) {
	global $snapchat, $username, $gEmail, $gPasswd, $debug, $cli, $password, $auth_token;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	$username 	= $params[1];
	//$password 	= ((count($params) == 2) ? "" : (($params[2] == "password") ? $params[3] : ""));
	$auth_token	= ((count($params) == 2) ? "" : (($params[2] == "auth-token") ? $params[3] : ""));

	$snapchat = new Snapchat($username, $gEmail, $gPasswd, $debug, $cli);
	$snapchat->login($password, $auth_token);
	$snapchat->getUpdates();
}

function set($subcommand, $params) {
	global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($subcommand) {
		case 'location':
			if (count($params) < 3) {
				echo "Not enough arguments for '{$params[0]} {$params[1]}'.\n";
				return false;
			}
			$snapchat->setLocation($params[2], $params[3]);
			break;

		default:
			echo "'$subcommand' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function get($subcommand, $params) {
	global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($subcommand) {
		case 'snaps':
			if (count($params) < 3) {
				$snapchat->getSnaps(true);
			} else {
				foreach (compileList(array_slice($params, 2)) as $friend) {
					if (in_array($friend, $snapchat->getFriends())) {
						$snapchat->getSnapsByUsername($friend, true);
					} else {
						echo "$friend is not your friend.\n";
					}
				}
			}
			break;

		case 'snap':
			if (count($params) > 2) {
				foreach (array_slice($params, 2) as $snap) {
					$snapchat->getMedia($snap);
				}
			} else {
				echo "Not enough arguments for '{$params[0]} {$params[1]}'.\n";
				return false;
			}
			break;

		case 'snaptag':
			$snapchat->getSnaptag(true);
			break;

		case 'stories':
			if (count($params) >= 3) {
				foreach (compileList(array_slice($params, 2)) as $friend) {
					if (in_array($friend, $snapchat->getFriends())) {
						$snapchat->getStoriesByUsername($friend, true);
					} else {
						echo "$friend is not your friend.\n";
					}
				}
			} elseif (count($params) == 2) {
				$snapchat->getMyStories(true);
			}
			break;

		case 'story':
			if (count($params) == 2) {
				echo "Not enough arguments for '{$params[0]} {$params[1]}'.\n";
				return false;
			}
			$friendStories = $snapchat->getFriendStories();
			foreach ($friendStories as $story) {
				if ($story->media_id == $params[2]) {
					echo "Downloading story '{$story->media_id}' from '{$story->username}'... ";
					$snapchat->getStory($story->media_id, $story->media_key, $story->media_iv, $story->username, $story->timestamp, true);
					echo " done!\n";
					break;
				}
			}
			break;

		default:
			echo "'$params[1]' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function snaps($subcommand, $params) {
	global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($subcommand) {
		case 'get':
			get('snaps', $params);
			break;

		case 'adjust':

			break;

		default:
			echo "'$params[1]' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function stories($subcommand, $params) {
	global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($subcommand) {
		case 'get':
			get('stories', $params);
			break;

		case 'renew':

			break;

		default:
			echo "'$params[1]' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function fetch($subcommand, $params) {
	global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($subcommand) {
		case 'updates':
			$snapchat->getUpdates(true);
			break;

		case 'conversations':
		case 'convos':
			$snapchat->getConversations(true);
			echo " done!\n";
			break;

		default:
			echo "'$params[1]' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function write($params) {
	global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	foreach (array_slice($params, 1) as $subcommand) {
		switch ($subcommand) {
			case 'updates':
				echo "Writing updates.txt...";
				$data = $snapchat->getUpdates();
				file_put_contents("updates.txt", print_r($data, true));
				echo " done!\n";
				break;

			case 'friends':
				echo "Writing friends.txt...";
				$data = $snapchat->getFriends();
				file_put_contents("friends.txt", implode("\n", $data));
				echo " done!\n";
				break;

			case 'friends:added':
				echo "Writing friends_added.txt...";
				$data = $snapchat->getAddedFriends();
				file_put_contents("friends_added.txt", implode("\n", $data));
				echo " done!\n";
				break;

			case 'friends:unconfirmed':
				echo "Writing friends_unconfirmed.txt...";
				$data = $snapchat->getUnconfirmedFriends();
				file_put_contents("friends_unconfirmed.txt", implode("\n", $data));
				echo " done!\n";
				break;

			case 'scores':
				$data = $snapchat->getFriendScores($snapchat->getFriends());

				echo "Writing scores.txt...";
				asort($data);

				$lk = max(array_map('strlen', array_keys($data)));
				$lv = max(array_map('strlen', array_values($data)));
				$format = "%' -{$lk}s : %' {$lv}s";
				$datastring = array();
				foreach ($data as $k => $v) {
					$datastring[] = sprintf($format, $k, $v);
				}

				file_put_contents("scores.txt", implode("\n", $datastring));
				echo " done!\n";
				break;

			case 'snaps':
				echo "Writing snaps.txt...";
				$data = $snapchat->getSnaps();
				file_put_contents("snaps.txt", print_r($data, true));
				echo " done!\n";
				break;

			case 'stats':
				echo "Collecting data...\n";
				$data = $snapchat->getMyStories();
				$views = array();
				$screens = array();
				foreach ($data as $story) {
					foreach ($story->story_notes as $view) {
						$views[] = $view->viewer;
						if (!empty($view->screenshotted)) $screens[] = $view->viewer;
					}
				}

				if (is_file("stats_views.txt")) {
					$ex_views = explode("\n", file_get_contents("stats_views.txt"));
					$views = array_unique(array_merge($views, $ex_views));
					sort($views);
				}

				if (is_file("stats_screenshots.txt")) {
					$ex_screens = explode("\n", file_get_contents("stats_screenshots.txt"));
					$screens = array_unique(array_merge($screens, $ex_screens));
					sort($screens);
				}

				echo "Writing stats_views.txt and stats_screenshots.txt...";

				file_put_contents("stats_views.txt", implode("\n", $views));
				file_put_contents("stats_screenshots.txt", implode("\n", $screens));
				echo " done!\n";
				break;

			case 'stories':
				echo "Writing stories.txt...";
				$data = $snapchat->getFriendStories();
				file_put_contents("stories.txt", print_r($data, true));
				echo " done!\n";
				break;

			case 'stories:own':
				echo "Writing stories_own.txt...";
				$data = $snapchat->getMyStories();
				file_put_contents("stories_own.txt", print_r($data, true));
				echo " done!\n";
				break;

			case (preg_match("/stories:(.*)/", $subcommand, $matches) ? true : false):
				$username = $matches[1];
				echo "Writing stories_$username.txt...";
				$story = $snapchat->getStoriesByUsername($username);
				file_put_contents("stories_$username.txt", print_r($data, true));
				echo " done!\n";
				break;

			case 'conversations':
			case 'convos':
				echo "Writing conversations.txt...";
				$data = $snapchat->getConversations();
				file_put_contents("conversations.txt", print_r($data, true));
				echo " done!\n";
				break;

			case 'conversations:pending':
			case 'convos:pending':
			case 'convos:p':
				global $username;
				echo "Writing conversations_pending.txt...";
				$data = $snapchat->getConversations();
				$data_tw = array();
				foreach($data as &$conversation) {
					foreach ($conversation->participants as $participant) {
						if ($participant != $username) {
							$friend = $participant;
						}
					}
					if (count($conversation->pending_received_snaps) > 0) {
						$data_tw[] = $friend;
					}
				}
				file_put_contents("conversations_pending.txt", implode("\n", $data_tw));
				echo " done!\n";
				break;

			case 'conversations:friends':
			case 'convos:friends':
			case 'convos:f':
				global $username;
				echo "Writing conversations_friends.txt...";
				$data = $snapchat->getConversations();
				$data_tw = array();
				foreach($data as &$conversation) {
					foreach ($conversation->participants as $participant) {
						if ($participant != $username) {
							$data_tw[] = $participant;
						}
					}
				}
				file_put_contents("conversations_friends.txt", implode("\n", $data_tw));
				echo " done!\n";
				break;

			case 'conversations:list':
			case 'convos:list':
			case 'convos:l':
				global $username;
				echo "Writing conversations_list.txt...";
				$conversations = $snapchat->getConversations();
				$data = array();
				$lp = 0;
				foreach($conversations as &$conversation) {
					foreach ($conversation->participants as $participant) {
						if ($participant != $username) {
							$temp['participant'] = $participant;
							if ($lp < strlen($participant)) $lp = strlen($participant);
						}
					}
					$temp['timestamp'] = $conversation->last_interaction_ts;
					$data[] = (object)$temp;
				}

				$format = "%' -{$lp}s | %s | %s";
				$data_tw = array();
				foreach ($data as $d) {
					$data_tw[] = sprintf($format, $d->participant, date("Y-m-d H-i-s", (int) ($d->timestamp / 1000)), $d->timestamp);
				}

				file_put_contents("conversations_list.txt", implode("\n", $data_tw));
				echo " done!\n";
				break;

			default:
				echo "'$subcommand' is not a valid command for '{$params[0]}'.\n";
				return false;
				break;
		}
	}
}

function output($subcommand, $params) {
		global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($subcommand) {
		case 'friendmoji':
			$data = $snapchat->getUpdates();
			foreach ($data['data']->friends_response->friends as $friend) {
				if (!empty($friend->friendmoji_string)) echo sprintf("%' 20s -> %s\n", $friend->name, $friend->friendmoji_string);
			}
			break;

		default:
			echo "'$subcommand' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function friend($subcommand, $params) {
	global $snapchat;

	if (count($params) == 1) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($subcommand) {
		case 'add':
			foreach (compileList(array_slice($params, 2)) as $friend) {
				if (in_array($friend, $snapchat->getFriends())) {
					echo "$friend is your friend already.\n";
				} else {
					echo $snapchat->addFriend($friend)."\n";
				}
			}
			break;

		case 'delete':
			foreach (compileList(array_slice($params, 2)) as $friend) {
				if (in_array($friend, $snapchat->getFriends())) {
					echo $snapchat->deleteFriend($friend)."\n";
				} else {
					echo "$friend is not your friend.\n";
				}
			}
			break;

		case 'name':
			if (count($params) <= 2) {
				echo "Not enough arguments for '{$params[0]} {$params[1]}'.\n";
				return false;
			}

			$displayname = implode(" ", array_slice($params, 3));

			if (in_array($params[2], $snapchat->getFriends())) {
				echo $snapchat->setDisplayName($params[2], $displayname)."\n";
			} else {
				echo "$params[2] is not your friend.\n";
			}
			break;

		case 'block':
			foreach (compileList(array_slice($params, 2)) as $friend) {
				if (in_array($friend, $snapchat->getFriends())) {
					echo $snapchat->block($friend)."\n";
				} else {
					echo "$friend is not your friend.\n";
				}
			}
			break;

		case 'unblock':
			foreach (compileList(array_slice($params, 2)) as $friend) {
				if (in_array($friend, $snapchat->getFriends())) {
					echo $snapchat->unblock($friend)."\n";
				} else {
					echo "$friend is not your friend.\n";
				}
			}
			break;

		default:
			echo "'$params[1]' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function send($subcommand, $params) {
	global $snapchat;

	if (count($params) < 4) {
		echo "Not enough arguments for '{$params[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	$path = $params[2];
	$time = 10;
	$text = null;
	$friends = array();

	if (!file_exists($path)) {
		echo "The specified image does not exist.\n";
		return false;
	}

	$isText = false;
	for ($i=3; $i < count($params); $i++) {
		if (startsWith($params[$i], "time:")) {
			$time = (int)(str_replace("time:", "", $params[$i]));
		} elseif (startsWith($params[$i], "text:'")) {
			$isText = true;
			$text = str_replace("text:'", "", $params[$i]);
		} elseif ($isText) {
			if (endsWith($params[$i], "'")) {
				$text .= " " . str_replace("'", "", $params[$i]);
				$isText = false;
			} else {
				$text .= " " . $params[$i];
			}
		} else {
			if (file_exists($params[$i])) {
				$content = file_get_contents($params[$i]);
				$arr_friends = explode("\n", $content);
				$friends = array_merge($friends, $arr_friends);
			} else {
				$friends[] = $params[$i];
			}
		}
	}

	switch ($subcommand) {
		case 'snap':
			$batchcount = 30;
			$batches = array_chunk($friends, $batchcount);

			$total = count($friends);
			$current = 0;
			foreach ($batches as $batch) {
				$current = $current + count($batch);
				$snapchat->send($path, $batch, $text, $time);
				echo sprintf("Snapping: %' 4d / %' 4d done.\r", $current, $total);
			}
			echo "\n";
			break;

		case 'story':
			$snapchat->setStory($path, $time, $text);
			break;

		default:
			echo "'$params[1]' is not a valid command for '{$params[0]}'.\n";
			return false;
			break;
	}
}

function sync($subcommand, $commands) {
	global $snapchat;

	if (count($commands) == 1) {
		echo "Not enough arguments for '{$commands[0]}'.\n";
		return false;
	}

	if (!isset($snapchat)) {
		echo "You are not logged in. Please login first.\n";
		continue;
	}

	switch ($commands[1]) {
		case 'snaps':

			break;

		case 'stories':

			break;

		default:
			echo "'$commands[1]' is not a valid command for '{$commands[0]}'.\n";
			return false;
			break;
	}
}

////////////////////////////////////////////////////////////////////////////////////////////////////////

function compileList($arguments) {
	$return = array();
	foreach ($arguments as $arg) {
		if (file_exists($arg)) {
			$content = file_get_contents($arg);
			$arr = explode("\n", $content);
			$return = array_merge($return, $arr);
		} else {
			$return[] = $arg;
		}
	}
	return array_filter(array_unique($return, SORT_STRING));
}

function startsWith($haystack, $needle) {
    // search backwards starting from haystack length characters from the end
    return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== FALSE;
}
function endsWith($haystack, $needle) {
    // search forward starting from end minus needle length characters
    return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
}

?>
