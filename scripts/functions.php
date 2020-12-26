<?php
	// Configuration file modifier functions.
	// (C) 2020 CubicleSoft.  All Rights Reserved.

	// Callback function for UpdateConfFile() that skips the final data map.
	function UpdateConfSkipFinal($line, &$datamap, $key, $separator, $val)
	{
		if ($line === false)
		{
			$datamap = array();

			return;
		}

		if ($val === false)  $line = "";
		else
		{
			$line = rtrim($key . $separator . $val);

			$datamap[$key] = false;
		}

		return $line;
	}

	// Intakes a series of lines, data keys and values to use, separators (if any), and an optional callback for custom behavior.
	// Returns a new set of lines.
	function UpdateConfFile($lines, $datamap, $separator, $uncomment = false, $callback = false)
	{
		foreach ($lines as $num => $line)
		{
			$line = trim($line);
			if ($uncomment !== false && substr($line, 0, strlen($uncomment)) === $uncomment)  $uline = trim(substr($line, strlen($uncomment)));
			else  $uline = false;

			if (trim($separator) !== "")
			{
				$linekey = false;
				if ($uline !== false)
				{
					$pos = strpos($uline, trim($separator));
					if ($pos !== false)  $linekey = trim(substr($uline, 0, $pos));
				}
				else
				{
					$pos = strpos($line, trim($separator));
					if ($pos !== false)  $linekey = trim(substr($line, 0, $pos));
				}

				if ($linekey !== false && isset($datamap[$linekey]))
				{
					$val = $datamap[$linekey];

					if (is_callable($callback))
					{
						$lines[$num] = call_user_func_array($callback, array(($uline !== false ? $uline : $line), &$datamap, $linekey, $separator, $val));
					}
					else if ($val === false)  $lines[$num] = "";
					else
					{
						$lines[$num] = rtrim($linekey . $separator . $val);

						$datamap[$linekey] = false;
					}
				}
			}
			else
			{
				foreach ($datamap as $key => $val)
				{
					if (substr($line, 0, strlen($key)) === $key || ($uline !== false && substr($uline, 0, strlen($key)) === $key))
					{
						if (is_callable($callback))
						{
							$lines[$num] = call_user_func_array($callback, array(($uline !== false ? $uline : $line), &$datamap, $key, $separator, $val));
						}
						else if ($val === false)  $lines[$num] = "";
						else
						{
							$lines[$num] = rtrim($key . $separator . $val);

							$datamap[$key] = false;
						}
					}
				}
			}
		}

		if (count($lines) && trim($lines[count($lines) - 1]) !== "")  $lines[] = "";

		if (is_callable($callback))  call_user_func_array($callback, array(false, &$datamap, false, false, false));

		foreach ($datamap as $key => $val)
		{
			if ($val !== false)  $lines[] = rtrim($key . $separator . $val);
		}

		return $lines;
	}

	// Intakes a series of lines and performs a series of regular expression matches.  Returns a new set of lines.
	function UpdateConfFileRegEx($lines, $datamap, $addunused = true)
	{
		foreach ($lines as $num => $line)
		{
			$line = trim($line);

			foreach ($datamap as $key => $val)
			{
				if (preg_match($key, $line))
				{
					if ($val === false)  $lines[$num] = "";
					else
					{
						$lines[$num] = $val;

						$datamap[$key] = false;
					}
				}
			}
		}

		if (count($lines) && trim($lines[count($lines) - 1]) !== "")  $lines[] = "";

		if ($addunused)
		{
			foreach ($datamap as $key => $val)
			{
				if ($val !== false)  $lines[] = $val;
			}
		}

		return $lines;
	}

	// Creates a new password.
	function MakePassword()
	{
		$rootpath = str_replace("\\", "/", dirname(__FILE__));

		if (!class_exists("CSPRNG", false))  require_once $rootpath . "/../support/random.php";

		$rng = new CSPRNG();
		$freqmap = json_decode(file_get_contents($rootpath . "/../support/en_us_lite.json"), true);

		$words = array();
		for ($x = 0; $x < 3; $x++)  $words[] = preg_replace('/[^a-z]/', "-", strtolower($rng->GenerateWordLite($freqmap, $rng->GetInt(4, 8))));

		return implode("-", $words);
	}
?>