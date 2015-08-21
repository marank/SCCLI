<?php

class JSON
{
	static function prettify($json, $tabsize = null)
	{
		$json = json_encode($json);
		$result = '';
		$level = 0;
		$in_quotes = false;
		$in_escape = false;
		$ends_line_level = NULL;
		$json_length = strlen($json);
		for ($i = 0; $i < $json_length; $i++)
		{
			$char = $json[$i];
			$new_line_level = NULL;
			$post = "";
			if ($ends_line_level !== NULL)
			{
				$new_line_level = $ends_line_level;
				$ends_line_level = NULL;
			}

			if ($in_escape)
			{
				$in_escape = false;
			}
			elseif ($char === '"')
			{
				$in_quotes = !$in_quotes;
			}
			elseif (!$in_quotes)
			{
				switch ($char)
				{
				case '}':
				case ']':
					$level--;
					$ends_line_level = NULL;
					$new_line_level = $level;
					break;

				case '{':
				case '[':
					$level++;
				case ',':
					$ends_line_level = $level;
					break;

				case ':':
					$post = " ";
					break;

				case " ":
				case "\t":
				case "\n":
				case "\r":
					$char = "";
					$ends_line_level = $new_line_level;
					$new_line_level = NULL;
					break;
				}
			}
			elseif ($char === '\\')
			{
				$in_escape = true;
			}

			if ($new_line_level !== NULL)
			{
				$indent = is_null($tabsize) ? "\t" : str_repeat(" ", $tabsize);
				$result.= "\n" . str_repeat($indent, $new_line_level);
			}

			$result.= $char . $post;
		}

		return $result;
	}
}