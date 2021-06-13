<?php
	// Windows icon and cursor file format reader/writer class.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	class WinICO
	{
		const TYPE_ICO = 1;
		const TYPE_CUR = 2;

		public static function Create($data, $hotspotx = false, $hotspoty = false)
		{
			if (!is_int($hotspotx) || !is_int($hotspoty))
			{
				$hotspotx = false;
				$hotspoty = false;
			}

			@ini_set("memory_limit", "-1");

			// GD.
			$info = getimagesizefromstring($data);
			if ($info === false)  return array("success" => false, "error" => self::WICOTranslate("Unable to load image."), "errorcode" => "image_load_failed");
			$srcwidth = $info[0];
			$srcheight = $info[1];

			if ($hotspotx !== false && $hotspoty !== false)
			{
				if ($hotspotx < 0)  $hotspotx = 0;
				if ($hotspotx >= $srcwidth)  $hotspotx = $srcwidth;

				if ($hotspoty < 0)  $hotspoty = 0;
				if ($hotspoty >= $srcheight)  $hotspoty = $srcheight;
			}

			// Normalize the image to a maximum of 2048x2048 and constrain to a square image.
			if ($srcwidth > 2048 || $srcheight > 2048)
			{
				$result = self::ResizeImage($data, 2048, 2048);
				if (!$result["success"])  return $result;

				if ($hotspotx !== false && $hotspoty !== false)
				{
					$hotspotx = (int)($hotspotx * 2048 / $srcwidth);
					$hotspoty = (int)($hotspoty * 2048 / $srcheight);
				}

				$data = $result["data"];
				$srcwidth = 2048;
				$srcheight = 2048;
			}

			// No resizing takes place here if the image is a square image.
			if ($srcwidth < $srcheight)
			{
				$result = self::ResizeImage($data, $srcwidth, $srcwidth);
				if (!$result["success"])  return $result;

				if ($hotspoty !== false)  $hotspoty = (int)($hotspoty * $srcwidth / $srcheight);

				$data = $result["data"];
				$srcheight = $srcwidth;
			}
			else if ($srcwidth > $srcheight)
			{
				$result = self::ResizeImage($data, $srcheight, $srcheight);
				if (!$result["success"])  return $result;

				if ($hotspotx !== false)  $hotspotx = (int)($hotspotx * $srcheight / $srcwidth);

				$data = $result["data"];
				$srcwidth = $srcheight;
			}

			// Prepare icons in ascending bits per pixel and descending size order.
			$icons = array();

			$sizebits = array(
				array("size" => 16, "bits" => 24),
				array("size" => 24, "bits" => 24),
				array("size" => 32, "bits" => 24),
				array("size" => 48, "bits" => 24),
				array("size" => 16, "bits" => 32),
				array("size" => 24, "bits" => 32),
				array("size" => 32, "bits" => 32),
				array("size" => 48, "bits" => 32),
				array("size" => 256, "bits" => 32)
			);

			foreach ($sizebits as $info)
			{
				if ($info["size"] <= $srcwidth)
				{
					$result = self::ResizeAndConvertToIcon($data, $info["size"], $info["size"], $info["bits"], $hotspotx, $hotspoty);
					if ($result["success"])
					{
						unset($result["success"]);

						$icons[] = $result;
					}
				}
			}

			return self::Generate(($hotspotx === false || $hotspoty === false ? self::TYPE_ICO : self::TYPE_CUR), $icons);
		}

		// Resizes an image and convert it to PNG.
		public static function ResizeImage(&$data, $destwidth, $destheight)
		{
			@ini_set("memory_limit", "-1");

			// GD.
			$info = getimagesizefromstring($data);
			if ($info === false)  return array("success" => false, "error" => self::WICOTranslate("Unable to load image."), "errorcode" => "image_load_failed");
			$srcwidth = $info[0];
			$srcheight = $info[1];

			$img = imagecreatefromstring($data);
			if ($img === false)  return array("success" => false, "error" => self::WICOTranslate("Unable to load image."), "errorcode" => "image_load_failed");

			$img2 = imagecreatetruecolor($destwidth, $destheight);
			if ($img2 === false)
			{
				imagedestroy($img);

				return array("success" => false, "error" => self::WICOTranslate("Unable to resize image."), "errorcode" => "image_crop_resize_failed");
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

		// Resizes an image and convert it to PNG or ICO BMP and wraps it in a Generate() compatible wrapper.
		public static function ResizeAndConvertToIcon(&$data, $destwidth, $destheight, $bits, $hotspotx = false, $hotspoty = false)
		{
			@ini_set("memory_limit", "-1");

			if ($destwidth > 256)  $destwidth = 256;
			if ($destheight > 256)  $destheight = 256;

			$bits = ($bits >= 32 ? 32 : 24);

			// GD.
			$info = getimagesizefromstring($data);
			if ($info === false)  return array("success" => false, "error" => self::WICOTranslate("Unable to load image."), "errorcode" => "image_load_failed");
			$srcwidth = $info[0];
			$srcheight = $info[1];

			$img = imagecreatefromstring($data);
			if ($img === false)  return array("success" => false, "error" => self::WICOTranslate("Unable to load image."), "errorcode" => "image_load_failed");

			$img2 = imagecreatetruecolor($destwidth, $destheight);
			if ($img2 === false)
			{
				imagedestroy($img);

				return array("success" => false, "error" => self::WICOTranslate("Unable to resize image."), "errorcode" => "image_crop_resize_failed");
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

			$result = array(
				"success" => true,
				"width" => $destwidth,
				"height" => $destheight,
				"colors" => 0,
			);

			if ($hotspotx !== false && $hotspoty !== false)
			{
				$result["hotspot_x"] = (int)($hotspotx * $destwidth / $srcwidth);
				$result["hotspot_y"] = (int)($hotspoty * $destheight / $srcheight);
			}
			else
			{
				$result["planes"] = 1;
				$result["bits_per_pixel"] = $bits;
			}

			if ($destwidth === 256 || $destheight === 256)
			{
				ob_start();
				imagepng($img2);
				$data2 = ob_get_contents();
				ob_end_clean();

				$result["size"] = strlen($data2);
				$result["data"] = $data2;
				$result["format"] = "PNG";
			}
			else
			{
				// ICO BMP header.  Lacks a file header.
				$compress = 0;
				$printresx = 0;
				$printresy = 0;
				$importantcolors = 0;

				$rowsize = (int)(($destwidth + ($bits < 8 ? 8 - $bits : 0)) * $bits / 8);
				$extra = $rowsize % 4;
				if ($extra)  $extra = (4 - $extra);
				$rowsize += $extra;

				$rawsize = $rowsize * $destheight;

				$data2 = pack("V", 40);
				$data2 .= pack("V", $destwidth);
				$data2 .= pack("V", $destheight * 2);
				$data2 .= pack("v", $result["planes"]);
				$data2 .= pack("v", $bits);
				$data2 .= pack("V", $compress);
				$data2 .= pack("V", $rawsize);
				$data2 .= pack("V", $printresx);
				$data2 .= pack("V", $printresy);
				$data2 .= pack("V", $result["colors"]);
				$data2 .= pack("V", $importantcolors);

				// Emit rows in reverse order.  Generate a mask as well.
				$maskrowsize = (int)(($destwidth + 7) / 8);
				$extra2 = $maskrowsize % 4;
				if ($extra2)  $extra2 = (4 - $extra2);
				$maskrowsize += $extra2;
				$mask = "";

				for ($y = $destheight; $y; $y--)
				{
					$row = "";
					$mask2 = 0x80;
					$currchr = 0;
					$maskrow = "";

					for ($x = 0; $x < $destwidth; $x++)
					{
						$color = imagecolorat($img2, $x, $y - 1);

						$r = ($color >> 16) & 0xFF;
						$g = ($color >> 8) & 0xFF;
						$b = $color & 0xFF;
						$a = (($color >> 24) & 0xFF) / 127;

						$row .= chr($b) . chr($g) . chr($r);

						if ($bits == 32)  $row .= chr((int)((1.0 - $a) * 255));

						if ($a > 0.33)  $currchr ^= $mask2;

						$mask2 >>= 1;
						if (!$mask2)
						{
							$maskrow .= chr($currchr);
							$mask2 = 0x80;
							$currchr = 0;
						}
					}

					if ($destwidth)
					{
						$data2 .= $row;
						if ($extra)  $data2 .= str_repeat("\x00", $extra);

						$mask .= $maskrow;
						if ($extra2)  $mask .= str_repeat("\x00", $extra2);
					}
				}

				$data2 .= $mask;

				$result["size"] = strlen($data2);
				$result["data"] = $data2;
				$result["format"] = "ICO_BMP";
			}

			imagedestroy($img2);

			return $result;
		}

		public static function ParseHeader($data, $groupdir)
		{
			$result = array(
				"success" => true
			);

			$x = 0;
			$y = strlen($data);
			$reserved = self::GetUInt16($data, $x, $y);
			if ($reserved !== 0)  return array("success" => false, "error" => self::WICOTranslate("Invalid Windows ICO/CUR directory header."), "errorcode" => "invalid_header_dir");
			$result["type"] = self::GetUInt16($data, $x, $y);
			if ($result["type"] !== self::TYPE_ICO && $result["type"] !== self::TYPE_CUR)  return array("success" => false, "error" => self::WICOTranslate("Unknown Windows ICO/CUR directory type (%d).", $result["type"]), "errorcode" => "invalid_header_dir_type");

			$result["icons"] = array();
			$numleft = self::GetUInt16($data, $x, $y);

			while ($numleft > 0)
			{
				$icon = array();

				$icon["width"] = self::GetUInt8($data, $x, $y);
				if ($icon["width"] === 0)  $icon["width"] = 256;

				$icon["height"] = self::GetUInt8($data, $x, $y);
				if ($icon["height"] === 0)  $icon["height"] = 256;

				$icon["colors"] = self::GetUInt8($data, $x, $y);
				$reserved = self::GetUInt8($data, $x, $y);
				$icon[($result["type"] === self::TYPE_ICO ? "planes" : "hotspot_x")] = self::GetUInt16($data, $x, $y);
				$icon[($result["type"] === self::TYPE_ICO ? "bits_per_pixel" : "hotspot_y")] = self::GetUInt16($data, $x, $y);

				$icon["size"] = self::GetUInt32($data, $x, $y);

				if ($groupdir)
				{
					if ($x > $y - 2)  break;

					$icon["id"] = self::GetUInt16($data, $x, $y);
				}
				else
				{
					if ($x > $y - 4)  break;

					$icon["offset"] = self::GetUInt32($data, $x, $y);
				}

				if ($icon["size"] > 0)  $result["icons"][] = $icon;

				$numleft--;
			}

			return $result;
		}

		public static function Parse($data)
		{
			$result = self::ParseHeader($data, false);
			if (!$result["success"])  return $result;

			$y = strlen($data);
			$icons = array();
			foreach ($result["icons"] as $icon)
			{
				// Skip icons with invalid offsets.
				if ($icon["offset"] >= $y)  continue;

				// Correct the size.
				if ($icon["offset"] + $icon["size"] > $y)  $icon["size"] = $y - $icon["offset"];

				$icon["data"] = substr($data, $icon["offset"], $icon["size"]);

				// Check data for known signatures.  Only PNG and a "headerless" BMP w/ mask are the formats supported by ICO/CUR.
				// PNG signature:  \x89PNG\r\n\x1A\n
				if (substr($icon["data"], 0, 8) === "\x89PNG\r\n\x1A\n")  $icon["format"] = "PNG";
				else  $icon["format"] = "ICO_BMP";

				$icons[] = $icon;
			}

			$result["icons"] = $icons;

			return $result;
		}

		public static function ConvertICOBMPToPNG($data)
		{
			if (!function_exists("imagecreatefromstring"))  return array("success" => false, "error" => self::WICOTranslate("Required function 'imagecreatefromstring' is not available.  Enable the GD library and try again."), "errorcode" => "missing_function");

			// First, convert the ICO BMP to a BMP without its mask.
			// Read in the "headerless" BMP header.
			$x = 0;
			$y = strlen($data);
			$headersize = self::GetUInt32($data, $x, $y);
			if ($headersize < 40)  return array("success" => false, "error" => self::WICOTranslate("Invalid BMP file header size."), "errorcode" => "invalid_bmp_header_size");

			$width = self::GetUInt32($data, $x, $headersize);
			if ($width < 0 || $width > 0x7FFFFFFF)  return array("success" => false, "error" => self::WICOTranslate("Unsupported negative ICO BMP file header width encountered."), "errorcode" => "unsupported_ico_bmp_header_width");

			$height = self::GetUInt32($data, $x, $headersize);
			if ($height < 0 || $height > 0x7FFFFFFF)  return array("success" => false, "error" => self::WICOTranslate("Unsupported negative ICO BMP file header height encountered."), "errorcode" => "unsupported_ico_bmp_header_height");
			if ($height % 2 != 0)  return array("success" => false, "error" => self::WICOTranslate("Invalid ICO BMP file header height."), "errorcode" => "invalid_ico_bmp_header_height");
			$height /= 2;

			$planes = self::GetUInt16($data, $x, $headersize);

			$bitsperpixel = self::GetUInt16($data, $x, $headersize);
			if ($bitsperpixel < 1 || $bitsperpixel > 32)  return array("success" => false, "error" => self::WICOTranslate("Invalid BMP file header bits per pixel."), "errorcode" => "invalid_bmp_header_bits");

			$compress = self::GetUInt32($data, $x, $headersize);
			if ($compress < 0 || $compress > 6)  return array("success" => false, "error" => self::WICOTranslate("BMP file header contains unexpected compression value (%d).", $compress), "errorcode" => "unexpected_bmp_header_compression");
			if ($compress == 4 || $compress == 5)  return array("success" => false, "error" => self::WICOTranslate("BMP file header contains unexpected JPEG or PNG data."), "errorcode" => "unexpected_bmp_header_compression");

			$rawsize = self::GetUInt32($data, $x, $headersize);
			$printresx = self::GetUInt32($data, $x, $headersize);
			$printresy = self::GetUInt32($data, $x, $headersize);
			$numcolors = self::GetUInt32($data, $x, $headersize);
			$importantcolors = self::GetUInt32($data, $x, $headersize);

			// Attempt to fix incorrect values.
			if ($numcolors > 256)  $numcolors = 0;
			if (!$numcolors && $bitsperpixel <= 8)  $numcolors = 1 << $bitsperpixel;
			if ($importantcolors > $numcolors)  $importantcolors = $numcolors;

			$colortablesize = ($compress === 3 || $compress === 6 ? 16 : 0) + ($numcolors * 4);

			// Extract mask bits.
			$maskrowsize = (int)(($width + 7) / 8);
			$extra = $maskrowsize % 4;
			if ($extra)  $maskrowsize += (4 - $extra);
			$masksize = $maskrowsize * $height;

			$usemask = true;
			if ($compress == 0 || $compress === 3 || $compress === 6)
			{
				$rowsize = (int)(($width + ($bitsperpixel < 8 ? 8 - $bitsperpixel : 0)) * $bitsperpixel / 8);
				$extra = $rowsize % 4;
				if ($extra)  $rowsize += (4 - $extra);

				if ($bitsperpixel == 32)
				{
					// If any alpha channel value has data, then ignore the mask.
					$x2 = $headersize + $colortablesize;
					$y2 = $x2 + ($rowsize * $height);

					$x2 += 3;
					while ($x2 < $y2 && $x2 < $y)
					{
						if ($data[$x2] !== "\x00")
						{
							$usemask = false;

							break;
						}

						$x2 += 4;
					}
				}

				if (!$usemask)  $mask = "";
				else
				{
					$x2 = $headersize + $colortablesize + ($rowsize * $height);

					$mask = substr($data, $x2, $masksize);
				}
			}
			else
			{
				$mask = substr($data, -$masksize);
			}

			// Adjust raw pixel size if not set.
			if (!$rawsize)  $rawsize = $y - $headersize - $colortablesize - $masksize;

			// Build a new BMP with corrected header and a file header.
			$rawoffset = 14 + $headersize + $colortablesize;
			$data2 = "BM" . pack("V", strlen($data) + 14) . pack("v", 0) . pack("v", 0) . pack("V", $rawoffset);
			$data2 .= pack("V", $headersize);
			$data2 .= pack("V", $width);
			$data2 .= pack("V", $height);
			$data2 .= pack("v", $planes);
			$data2 .= pack("v", $bitsperpixel);
			$data2 .= pack("V", $compress);
			$data2 .= pack("V", $rawsize);
			$data2 .= pack("V", $printresx);
			$data2 .= pack("V", $printresy);
			$data2 .= pack("V", $numcolors);
			$data2 .= pack("V", $importantcolors);
			$data2 .= substr($data, 40);

			// Attempt to load the BMP file into GD.
			@ini_set("memory_limit", "-1");

			$img = imagecreatefromstring($data2);
			if ($img === false)  return array("success" => false, "error" => self::WICOTranslate("Unable to load image."), "errorcode" => "image_load_failed");

			$img2 = imagecreatetruecolor($width, $height);
			if ($img2 === false)
			{
				imagedestroy($img);

				return array("success" => false, "error" => self::WICOTranslate("Unable to create canvas for the image."), "errorcode" => "image_canvas_failed");
			}

			// Make fully transparent.
			$transparent = imagecolorallocatealpha($img2, 0, 0, 0, 127);
			imagecolortransparent($img2, $transparent);
			imagealphablending($img2, false);
			imagesavealpha($img2, true);
			imagefill($img2, 0, 0, $transparent);

			// Copy the source to the destination.
			imagecopy($img2, $img, 0, 0, 0, 0, $width, $height);

			// Force transparency where needed via the mask.
			if ($usemask)
			{
				for ($y2 = 0; $y2 < $height; $y2++)
				{
					$mask2 = 0x80;
					$mx = $maskrowsize * ($height - $y2 - 1);
					$my = $mx + $maskrowsize;
					$currchr = self::GetUInt8($mask, $mx, $my);

					for ($x2 = 0; $x2 < $width; $x2++)
					{
						if ($currchr & $mask2)  imagesetpixel($img2, $x2, $y2, $transparent);

						$mask2 >>= 1;
						if (!$mask2)
						{
							$mask2 = 0x80;
							$currchr = self::GetUInt8($mask, $mx, $my);
						}
					}
				}
			}

			imagedestroy($img);

			ob_start();
			imagepng($img2);
			$data3 = ob_get_contents();
			ob_end_clean();

			imagedestroy($img2);

			return array("success" => true, "bmp_data" => $data2, "png_data" => $data3);
		}

		public static function Generate($type, $icons)
		{
			// Count ids.  Either all are ids or none are.
			$numids = 0;
			foreach ($icons as $icon)
			{
				if (isset($icon["id"]))  $numids++;
			}

			if ($numids && $numids !== count($icons))  return array("success" => false, "error" => self::WICOTranslate("One or more icon directory entries do not have resource IDs.  When using resource IDs, all entries must have a resource ID."), "errorcode" => "id_count_mismatch");

			// Build the directory header.
			$header = pack("v", 0) . pack("v", $type) . pack("v", count($icons));
			$body = "";

			$nextoffset = 6 + (count($icons) * 16);

			foreach ($icons as $icon)
			{
				$header .= chr($icon["width"] >= 256 ? 0 : $icon["width"]);
				$header .= chr($icon["height"] >= 256 ? 0 : $icon["height"]);
				$header .= chr($icon["colors"]);
				$header .= "\x00";
				$header .= pack("v", (isset($icon["hotspot_x"]) ? $icon["hotspot_x"] : $icon["planes"]));
				$header .= pack("v", (isset($icon["hotspot_y"]) ? $icon["hotspot_y"] : $icon["bits_per_pixel"]));
				$header .= pack("V", $icon["size"]);

				if (isset($icon["id"]))  $header .= pack("v", $icon["id"]);
				else
				{
					$header .= pack("V", $nextoffset);
					$nextoffset += $icon["size"];

					if (isset($icon["data"]))  $body .= $icon["data"];
				}
			}

			return array("success" => true, "data" => $header . $body);
		}

		protected static function GetUInt8(&$data, &$x, $y)
		{
			return ord(self::GetBytes($data, $x, $y, 1));
		}

		protected static function GetUInt16(&$data, &$x, $y)
		{
			return unpack("v", self::GetBytes($data, $x, $y, 2))[1];
		}

		protected static function GetUInt32(&$data, &$x, $y)
		{
			return unpack("V", self::GetBytes($data, $x, $y, 4))[1];
		}

		protected static function GetBytes(&$data, &$x, $y, $size)
		{
			if ($size < 0)  return "";

			if ($x >= $y)  $result = str_repeat("\x00", $size);
			else if ($x + $size >= $y)  $result = (string)substr($data, $x, $y - $x) . str_repeat("\x00", $x + $size - $y);
			else  $result = (string)substr($data, $x, $size);

			$x += $size;

			return $result;
		}

		protected static function WICOTranslate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}
	}
?>