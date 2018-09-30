<?php
	// Apple ICNS icon file format reader/writer class.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	class AppleICNS
	{
		public static $knowntypes = array(
			"ICON" => array("width" => 32, "height" => 32, "os_ver" => "1.0", "bits" => 1),
			"ICN#" => array("width" => 32, "height" => 32, "os_ver" => "6.0", "bits" => 1),
			"icm#" => array("width" => 16, "height" => 12, "os_ver" => "6.0", "bits" => 1),
			"icm4" => array("width" => 16, "height" => 12, "os_ver" => "7.0", "bits" => 4),
			"icm8" => array("width" => 16, "height" => 12, "os_ver" => "7.0", "bits" => 8),
			"ics#" => array("width" => 16, "height" => 16, "os_ver" => "6.0", "bits" => 1),
			"ics4" => array("width" => 16, "height" => 16, "os_ver" => "7.0", "bits" => 4),
			"ics8" => array("width" => 16, "height" => 16, "os_ver" => "7.0", "bits" => 8),
			"is32" => array("width" => 16, "height" => 16, "os_ver" => "8.5", "bits" => 24),
			"s8mk" => array("width" => 16, "height" => 16, "os_ver" => "8.5", "bits" => 8),
			"icl4" => array("width" => 32, "height" => 32, "os_ver" => "7.0", "bits" => 4),
			"icl8" => array("width" => 32, "height" => 32, "os_ver" => "7.0", "bits" => 8),
			"il32" => array("width" => 32, "height" => 32, "os_ver" => "8.5", "bits" => 24),
			"l8mk" => array("width" => 32, "height" => 32, "os_ver" => "8.5", "bits" => 8),
			"ich#" => array("width" => 48, "height" => 48, "os_ver" => "8.5", "bits" => 1),
			"ich4" => array("width" => 48, "height" => 48, "os_ver" => "8.5", "bits" => 4),
			"ich8" => array("width" => 48, "height" => 48, "os_ver" => "8.5", "bits" => 8),
			"ih32" => array("width" => 48, "height" => 48, "os_ver" => "8.5", "bits" => 24),
			"h8mk" => array("width" => 48, "height" => 48, "os_ver" => "8.5", "bits" => 8),
			"it32" => array("width" => 128, "height" => 128, "os_ver" => "10.0", "bits" => 24),
			"t8mk" => array("width" => 128, "height" => 128, "os_ver" => "10.0", "bits" => 8),
			"icp4" => array("width" => 16, "height" => 16, "os_ver" => "10.7"),
			"icp5" => array("width" => 32, "height" => 32, "os_ver" => "10.7"),
			"icp6" => array("width" => 64, "height" => 64, "os_ver" => "10.7"),
			"ic07" => array("width" => 128, "height" => 128, "os_ver" => "10.7"),
			"ic08" => array("width" => 256, "height" => 256, "os_ver" => "10.5"),
			"ic09" => array("width" => 512, "height" => 512, "os_ver" => "10.5"),
			"ic10" => array("width" => 1024, "height" => 1024, "os_ver" => "10.7"),
			"ic11" => array("width" => 32, "height" => 32, "os_ver" => "10.8", "retina" => true),
			"ic12" => array("width" => 64, "height" => 64, "os_ver" => "10.8", "retina" => true),
			"ic13" => array("width" => 256, "height" => 256, "os_ver" => "10.8", "retina" => true),
			"ic14" => array("width" => 512, "height" => 512, "os_ver" => "10.8", "retina" => true),
			"ic04" => array("width" => 16, "height" => 16, "os_ver" => "UNKNOWN"),
			"ic05" => array("width" => 32, "height" => 32, "os_ver" => "UNKNOWN"),
			"icsB" => array("width" => 36, "height" => 36, "os_ver" => "UNKNOWN"),
			"icsb" => array("width" => 18, "height" => 18, "os_ver" => "UNKNOWN"),
		);

		public static function Create($data)
		{
			@ini_set("memory_limit", "512M");

			// GD.
			$info = getimagesizefromstring($data);
			if ($info === false)  return array("success" => false, "error" => self::AICNSTranslate("Unable to load image."), "errorcode" => "image_load_failed");
			$srcwidth = $info[0];
			$srcheight = $info[1];

			// Normalize the image to a maximum of 2048x2048 and constrain to a square image.
			if ($srcwidth > 2048 || $srcheight > 2048)
			{
				$result = self::ResizeImage($data, 2048, 2048);
				if (!$result["success"])  return $result;

				$data = $result["data"];
				if ($srcwidth > 2048)  $srcwidth = 2048;
				if ($srcwidth > 2048)  $srcheight = 2048;
			}

			// No resizing takes place here if the image is a square image.
			if ($srcwidth < $srcheight)
			{
				$result = self::ResizeImage($data, $srcwidth, $srcwidth);
				if (!$result["success"])  return $result;

				$data = $result["data"];
				$srcheight = $srcwidth;
			}
			else if ($srcwidth > $srcheight)
			{
				$result = self::ResizeImage($data, $srcheight, $srcheight);
				if (!$result["success"])  return $result;

				$data = $result["data"];
				$srcwidth = $srcheight;
			}

			// Prepare icons in decending size order.
			$icons = array();
			$types = array("ic10", "ic09", "ic14", "ic08", "ic13", "ic07", "icp6", "ic12", "icp5", "ic11", "icp4");
			foreach ($types as $type)
			{
				if (self::$knowntypes[$type]["width"] <= $srcwidth)
				{
					$result = self::ResizeImage($data, self::$knowntypes[$type]["width"], self::$knowntypes[$type]["height"]);
					if ($result["success"])
					{
						unset($result["success"]);
						$result["type"] = $type;

						$icons[] = $result;
					}
				}
			}

			return array("success" => true, "data" => self::Generate($icons));
		}

		// Resizes an image and convert it to PNG.
		public static function ResizeImage(&$data, $destwidth, $destheight)
		{
			@ini_set("memory_limit", "512M");

			// GD.
			$info = getimagesizefromstring($data);
			if ($info === false)  return array("success" => false, "error" => self::AICNSTranslate("Unable to load image."), "errorcode" => "image_load_failed");
			$srcwidth = $info[0];
			$srcheight = $info[1];

			$img = imagecreatefromstring($data);
			if ($img === false)  return array("success" => false, "error" => self::AICNSTranslate("Unable to load image."), "errorcode" => "image_load_failed");

			$img2 = imagecreatetruecolor($destwidth, $destheight);
			if ($img2 === false)
			{
				imagedestroy($img);

				return array("success" => false, "error" => self::AICNSTranslate("Unable to resize image."), "errorcode" => "image_crop_resize_failed");
			}

			// Make fully transparent.
			$transparent = imagecolorallocatealpha($img2, 0, 0, 0, 127);
			imagecolortransparent($img2, $transparent);
			imagealphablending($img2, false);
			imagesavealpha($img2, true);
			imagefill($img2, 0, 0, $transparent);

			// Copy the source onto the destination, resizing in the process.
			imagecopyresampled($img2, $img, 0, 0, 0, 0, $destwidth, $destheight, $srcwidth, $srcheight);
			imagedestroy($img);

			ob_start();
			imagepng($img2);
			$data2 = ob_get_contents();
			ob_end_clean();

			imagedestroy($img2);

			return array("success" => true, "data" => $data2);
		}

		public static function Parse($data)
		{
			$result = array(
				"success" => true
			);

			$x = 0;
			$y = strlen($data);

			$result["format"] = self::GetBytes($data, $x, $y, 4);
			if ($result["format"] !== "icns")  return array("success" => false, "error" => self::AICNSTranslate("File is not a valid Apple ICNS file.  Missing 'icns' signature."), "errorcode" => "missing_icns_file_signature");
			$result["size"] = self::GetUInt32($data, $x, $y);

			$result["icons"] = array();
			while ($x < $y)
			{
				$icon = array(
					"type" => self::GetBytes($data, $x, $y, 4),
					"size" => self::GetUInt32($data, $x, $y),
				);
				if (isset(self::$knowntypes[$icon["type"]]))  $icon["typeinfo"] = self::$knowntypes[$icon["type"]];

				// Avoid data shenanigans.
				if ($x + $icon["size"] - 8 > $y)  $icon["size"] = $y - $x + 8;

				$icon["data"] = self::GetBytes($data, $x, $y, $icon["size"] - 8);

				// Check data for known signatures.
				// PNG signature:  \x89PNG\r\n\x1A\n
				// JPEG 2000 signature:  [4 bytes size] followed by "jP  " or "jP2 " (8 bytes total).
				if (substr($icon["data"], 0, 8) === "\x89PNG\r\n\x1A\n")  $icon["format"] = "PNG";
				else if (substr($icon["data"], 4, 4) === "jP  " || substr($icon["data"], 4, 4) === "jP2 ")  $icon["format"] = "JPEG 2000";
				else if ($icon["type"] === "ic04" || $icon["type"] === "ic05")  $icon["format"] = "ARGB";
				else if ($icon["type"] === "is32" || $icon["type"] === "il32" || $icon["type"] === "ih32" || $icon["type"] === "it32")  $icon["format"] = "PackedBits";
				else  $icon["format"] = false;

				$result["icons"][] = $icon;
			}

			return $result;
		}

		public static function Generate($icons)
		{
			$data = "";
			foreach ($icons as $icon)
			{
				if (!isset($icon["type"]) || strlen($icon["type"]) < 4 || !isset($icon["data"]) || !is_string($icon["data"]))  continue;

				$data .= substr($icon["type"], 0, 4);
				$data .= pack("N", strlen($icon["data"]) + 8);
				$data .= $icon["data"];
			}

			return "icns" . pack("N", strlen($data) + 8) . $data;
		}

		protected static function GetUInt32(&$data, &$x, $y)
		{
			return unpack("N", self::GetBytes($data, $x, $y, 4))[1];
		}

		protected static function GetBytes(&$data, &$x, $y, $size)
		{
			$result = (string)substr($data, $x, $size);
			if ($x + $size > $y)  $result .= str_repeat("\x00", $x + $size - $y);
			$x += $size;

			return $result;
		}

		protected static function AICNSTranslate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}
?>