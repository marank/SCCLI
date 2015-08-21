<?php

class Configuration
{
	public function __get($k)
	{
		$values = json_decode(file_get_contents("config.json"), true);

		if (isset($values[$k]))
			return $values[$k];
		else
			return null;
	}

	public function __set($k, $v)
	{
		$values = json_decode(file_get_contents("config.json"), true);
		$values[$k] = $v;
		file_put_contents("config.json", JSON::prettify($values));
	}

	public function __isset($k)
	{
		$values = json_decode(file_get_contents("config.json"), true);
		return isset($values[$k]);
	}
}