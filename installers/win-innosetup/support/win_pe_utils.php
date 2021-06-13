<?php
	// Windows EXE PE utilities for PHP.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	if (!class_exists("WinPEFile", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/win_pe_file.php";

	class WinPEUtils
	{
		public static function GetIconOrCursorResource($winpe, $searchtype, $icoidname = true, $icoidlang = true)
		{
			if ($searchtype !== WinPEFile::RT_GROUP_CURSOR && $searchtype !== WinPEFile::RT_GROUP_ICON)  return array("success" => false, "error" => "Invalid search type specified.  Must be RT_GROUP_CURSOR or RT_GROUP_ICON.", "errorcode" => "invalid_search_type");

			if (!class_exists("WinICO", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/win_ico.php";

			// Locate the icon group.
			$result = $winpe->FindResource($searchtype, $icoidname, $icoidlang);
			if ($result === false)  return array("success" => false, "error" => "Group resource not found.", "errorcode" => "missing_group_resource");

			$groupnum = $result["num"];
			$groupentry = $result["entry"];

			// Extract the ICO header.
			$result = WinICO::ParseHeader($groupentry["data"], true);
			$result["group_num"] = $groupnum;
			if (!$result["success"])  return $result;
			if (($searchtype === WinPEFile::RT_GROUP_CURSOR && $result["type"] !== WinICO::TYPE_CUR) || ($searchtype === WinPEFile::RT_GROUP_ICON && $result["type"] !== WinICO::TYPE_ICO))  return array("success" => false, "error" => "Group resource type does not match.", "errorcode" => "invalid_group_resource", "group_num" => $groupnum);

			// Locate each resource and reconstruct the original icon/cursor.
			foreach ($result["icons"] as $num => $icon)
			{
				$result2 = $winpe->FindResource(($searchtype === WinPEFile::RT_GROUP_CURSOR ? WinPEFile::RT_CURSOR : WinPEFile::RT_ICON), $icon["id"], $groupentry["id"]);
				if ($result2 === false)  unset($result["icons"][$num]);
				else
				{
					$icon["data"] = $result2["entry"]["data"];
					$icon["orig_id"] = $icon["id"];
					unset($icon["id"]);

					$result["icons"][$num] = $icon;
				}
			}

			// Generate the ICO file data.
			$result2 = WinICO::Generate($result["type"], $result["icons"]);
			if (!$result2["success"])  return $result2;

			$result["data"] = $result2["data"];

			return $result;
		}

		public static function GetIconResource($winpe, $icoidname = true, $icoidlang = true)
		{
			return self::GetIconOrCursorResource($winpe, WinPEFile::RT_GROUP_ICON, $icoidname, $icoidlang);
		}

		public static function GetCursorResource($winpe, $icoidname = true, $icoidlang = true)
		{
			return self::GetCursorResource($winpe, WinPEFile::RT_GROUP_CURSOR, $icoidname, $icoidlang);
		}

		public static function SetIconOrCursorResource($winpe, &$data, $icoinfo, $icoidname = true, $icoidlang = true)
		{
			if (!class_exists("WinICO", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/win_ico.php";

			// Extract ICO file data, if necessary.
			if (is_string($icoinfo))
			{
				$result = WinICO::Parse($icoinfo);
				if (!$result["success"])  return $result;

				$icoinfo = $result;
			}

			if (!isset($icoinfo["icons"]))  return array("success" => false, "error" => "Input icon information does not contain icons.  Possibly a cursor?", "errorcode" => "invalid_icon_info");

			$searchtype = ($icoinfo["type"] === WinICO::TYPE_CUR ? WinPEFile::RT_GROUP_CURSOR : WinPEFile::RT_GROUP_ICON);

			// Locate the icon group.
			$result = $winpe->FindResource($searchtype, $icoidname, $icoidlang);
			if ($result === false)
			{
				// Generate valid, empty ICO file data.
				$result = WinICO::Generate($icoinfo["type"], array());
				if (!$result["success"])  return $result;

				// A group icon wasn't found/doesn't exist, so create one.
				$winpe->CreateResourceLangNode($searchtype, $icoidname, $icoidlang, $result["data"]);

				// Repeat the earlier find operation.
				$result = $winpe->FindResource($searchtype, $icoidname, $icoidlang);
				if ($result === false)  return array("success" => false, "error" => "Creating a group " . ($searchtype === WinPEFile::RT_GROUP_CURSOR ? "cursor" : "icon") . " failed.", "errorcode" => "failed_creating_group_" . ($searchtype === WinPEFile::RT_GROUP_CURSOR ? "cursor" : "icon"));
			}

			$groupnum = $result["num"];
			$groupentry = $result["entry"];

			// Extract the ICO header.
			$result = WinICO::ParseHeader($groupentry["data"], true);
			if (!$result["success"])  return $result;
			if (($searchtype === WinPEFile::RT_GROUP_CURSOR && $result["type"] !== WinICO::TYPE_CUR) || ($searchtype === WinPEFile::RT_GROUP_ICON && $result["type"] !== WinICO::TYPE_ICO))  return array("success" => false, "error" => "Group resource type does not match.", "errorcode" => "invalid_group_resource", "group_num" => $groupnum);

			// Locate each existing resource.  Then zero out original data and replace inline with new icon data (if possible) OR delete the resource.
			foreach ($result["icons"] as $num => $icon)
			{
				$result2 = $winpe->FindResource(($searchtype === WinPEFile::RT_GROUP_CURSOR ? WinPEFile::RT_CURSOR : WinPEFile::RT_ICON), $icon["id"], $groupentry["id"]);
				if ($result2 !== false)
				{
					// Get the resource's data position and zero the data but only if there is one resource directory RVA reference to the space.
					$rvainfo = $winpe->GetExclusiveResourceRVARefAndZero($data, $result2["num"]);
					if ($rvainfo === false)  $winpe->DeleteResource($result2["num"]);
					else
					{
						// Attempt to find an icon entry that maximally fits the available space.
						$maxnum = false;
						foreach ($icoinfo["icons"] as $num3 => $icon2)
						{
							if (!isset($icon2["id"]) && $rvainfo["size"] >= strlen($icon2["data"]) && ($maxnum === false || strlen($icoinfo["icons"][$maxnum]["data"]) < strlen($icon2["data"])))  $maxnum = $num3;
						}

						if ($maxnum === false)  $winpe->DeleteResource($result2["num"]);
						else
						{
							// Overwrite the existing space and mark the icon with the correct ID.
							$icoinfo["icons"][$maxnum]["id"] = $icon["id"];

							$winpe->OverwriteResourceData($data, $result2["num"], $icoinfo["icons"][$maxnum]["data"]);
						}
					}
				}
			}

			// Assign IDs to the remaining icons.
			foreach ($icoinfo["icons"] as $num => $icon)
			{
				if (!isset($icon["id"]))
				{
					$num2 = $winpe->CreateResourceLangNode(($searchtype === WinPEFile::RT_GROUP_CURSOR ? WinPEFile::RT_CURSOR : WinPEFile::RT_ICON), true, $groupentry["id"], $icon["data"]);
					$entry = $winpe->GetResource($num2);
					$identry = $winpe->GetResource($entry["parent"]);

					$icoinfo["icons"][$num]["id"] = $identry["id"];
				}
			}

			// Generate icon/cursor header.
			$result2 = WinICO::Generate($icoinfo["type"], $icoinfo["icons"]);
			if (!$result2["success"])  return $result2;

			// Overwrite the group with the icon/cursor header data.
			$winpe->OverwriteResourceData($data, $groupnum, $result2["data"]);

			return array("success" => true, "group_num" => $groupnum);
		}

		public static function SetIconResource($winpe, &$data, $icoinfo, $icoidname = true, $icoidlang = true)
		{
			return self::SetIconOrCursorResource($winpe, $data, $icoinfo, $icoidname, $icoidlang);
		}

		public static function SetCursorResource($winpe, &$data, $icoinfo, $icoidname = true, $icoidlang = true)
		{
			return self::SetIconOrCursorResource($winpe, $data, $icoinfo, $icoidname, $icoidlang);
		}

		public static function GetUnicodeStr(&$data, &$x, $y)
		{
			$str = "";
			while ($x + 1 < $y)
			{
				$currchr = $data[$x] . $data[$x + 1];
				$x += 2;

				if ($currchr === "\x00\x00")  break;

				$str .= $currchr;
			}

			return UTFUtils::Convert($str, UTFUtils::UTF16_LE, UTFUtils::UTF8);
		}

		public static function SetUnicodeStr(&$data, &$x, $str)
		{
			$str = UTFUtils::Convert($str, UTFUtils::UTF8, UTFUtils::UTF16_LE);

			WinPEFile::SetBytes($data, $x, $str, strlen($str) + 2);
		}

		public static function Internal_ParseVersionInfoEntry(&$data, &$x, $y, $parentkey, $allowedkeys)
		{
			// Depending on the parent type, the general structure of a version info entry is roughly:
			// 2 bytes - Structure length
			// 2 bytes - Value length
			// 2 bytes - Type (0 for binary data or 1 for text data)
			// Variable - Key (Unicode string)
			// Variable - Padding (32-bit alignment - only if value length > 0)
			// Variable - Value (Only if value length > 0)
			// Variable - Padding (32-bit alignment - only if the Key has child nodes)
			// Variable - Children (Only if the Key has child nodes)

			$len = WinPEFile::GetUInt16($data, $x, $y);
			if ($x + $len - 2 < $y)  $y = $x + $len - 2;

			$vallen = WinPEFile::GetUInt16($data, $x, $y);
			$type = WinPEFile::GetUInt16($data, $x, $y);
			$key = self::GetUnicodeStr($data, $x, $y);
			$x = WinPEFile::AlignValue($x, 4);

			if ($allowedkeys !== true && !isset($allowedkeys[$key]))  return array("success" => false, "error" => "Invalid version information key type encountered (" . $key . ").", "errorcode" => "invalid_verinfo_key_type");

			$entry = array(
				"type" => $type
			);

			// Handle values.
			if ($vallen)
			{
				$x2 = $x;
				$y2 = $x2 + $vallen;
				if ($y2 > $y)  $y2 = $y;

				if ($parentkey === false && $key === "VS_VERSION_INFO")
				{
					// Source:  https://docs.microsoft.com/en-us/windows/win32/api/verrsrc/ns-verrsrc-vs_fixedfileinfo
					$entry["fixed"] = array(
						"signature" => WinPEFile::GetUInt32($data, $x2, $y2),
						"struct_ver" => implode(".", array_reverse(array(WinPEFile::GetUInt16($data, $x2, $y2), WinPEFile::GetUInt16($data, $x2, $y2)))),
						"file_ver" => implode(".", array_reverse(array(WinPEFile::GetUInt16($data, $x2, $y2), WinPEFile::GetUInt16($data, $x2, $y2)))) . "." . implode(".", array_reverse(array(WinPEFile::GetUInt16($data, $x2, $y2), WinPEFile::GetUInt16($data, $x2, $y2)))),
						"product_ver" => implode(".", array_reverse(array(WinPEFile::GetUInt16($data, $x2, $y2), WinPEFile::GetUInt16($data, $x2, $y2)))) . "." . implode(".", array_reverse(array(WinPEFile::GetUInt16($data, $x2, $y2), WinPEFile::GetUInt16($data, $x2, $y2)))),
						"flags" => (WinPEFile::GetUInt32($data, $x2, $y2) & WinPEFile::GetUInt32($data, $x2, $y2)),
						"os" => WinPEFile::GetUInt32($data, $x2, $y2),
						"type" => WinPEFile::GetUInt32($data, $x2, $y2),
						"subtype" => WinPEFile::GetUInt32($data, $x2, $y2),
						"date" => WinPEFile::GetUInt32($data, $x2, $y2) * 0x100000000 + WinPEFile::GetUInt32($data, $x2, $y2),
					);
				}
				else if ($parentkey === "StringTable")
				{
					// Source:  https://docs.microsoft.com/en-us/windows/win32/menurc/string-str
					$y2 += $vallen;
					if ($y2 > $y)  $y2 = $y;
					$vallen *= 2;

					$entry["value"] = self::GetUnicodeStr($data, $x2, $y2);
				}
				else if ($parentkey === "VarFileInfo" && $key === "Translation")
				{
					// Source:  https://docs.microsoft.com/en-us/windows/win32/menurc/var-str
					$entry["translations"] = array();

					while ($x2 < $y2)
					{
						$entry["translations"][] = array(
							"lang_id" => WinPEFile::GetUInt16($data, $x2, $y2),
							"code_page" => WinPEFile::GetUInt16($data, $x2, $y2)
						);
					}
				}

				$x += $vallen;
				$x = WinPEFile::AlignValue($x, 4);
			}

			if ($parentkey === "StringTable" && !isset($entry["value"]))  $entry["value"] = "";
			if ($parentkey === "VarFileInfo" && $key === "Translation" && !isset($entry["translations"]))  $entry["translations"] = array();

			// Handle children.
			if ($parentkey === false && $key === "VS_VERSION_INFO")
			{
				// Source:  https://docs.microsoft.com/en-us/windows/win32/menurc/vs-versioninfo
				while ($x < $y)
				{
					$result = self::Internal_ParseVersionInfoEntry($data, $x, $y, $key, array("StringFileInfo" => true, "VarFileInfo" => true));

					if ($result["success"])
					{
						if ($result["key"] === "StringFileInfo")
						{
							if (!isset($entry["string_file_info"]))  $entry["string_file_info"] = $result["entry"];
							else
							{
								foreach ($result["entry"]["string_tables"] as $langid => $map)
								{
									if (!isset($entry["string_file_info"]["string_tables"][$langid]))  $entry["string_file_info"]["string_tables"][$langid] = $map;
									else  $entry["string_file_info"]["string_tables"][$langid] += $map;
								}
							}
						}
						else if ($result["key"] === "VarFileInfo")
						{
							if (!isset($entry["var_file_info"]))  $entry["var_file_info"] = $result["entry"];
							else  $entry["var_file_info"]["translations"] += $result["entry"]["translations"];
						}
					}
				}
			}
			else if ($parentkey === "VS_VERSION_INFO" && $key === "StringFileInfo")
			{
				// Source:  https://docs.microsoft.com/en-us/windows/win32/menurc/stringfileinfo
				$entry["string_tables"] = array();

				while ($x < $y)
				{
					$result = self::Internal_ParseVersionInfoEntry($data, $x, $y, $key, true);

					if ($result["success"])
					{
						if (!isset($entry["string_tables"][$result["key"]]))  $entry["string_tables"][$result["key"]] = array();

						$entry["string_tables"][$result["key"]] += $result["entry"]["strings"];

						ksort($entry["string_tables"][$result["key"]]);
					}
				}
			}
			else if ($parentkey === "StringFileInfo" && strlen($key) == 8)
			{
				// Source:  https://docs.microsoft.com/en-us/windows/win32/menurc/stringtable
				$entry["strings"] = array();

				while ($x < $y)
				{
					$result = self::Internal_ParseVersionInfoEntry($data, $x, $y, "StringTable", true);

					if ($result["success"])
					{
						$entry["strings"][$result["key"]] = $result["entry"]["value"];
					}
				}
			}
			else if ($parentkey === "VS_VERSION_INFO" && $key === "VarFileInfo")
			{
				// Source:  https://docs.microsoft.com/en-us/windows/win32/menurc/varfileinfo
				$entry["translations"] = array();

				while ($x < $y)
				{
					$result = self::Internal_ParseVersionInfoEntry($data, $x, $y, $key, array("Translation" => true));

					if ($result["success"])
					{
						foreach ($result["entry"]["translations"] as $entry2)  $entry["translations"][] = $entry2;
					}
				}
			}

			return array("success" => true, "key" => $key, "entry" => $entry);
		}

		public static function ParseVersionInfoData($data)
		{
			if (!class_exists("UTFUtils", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/utf_utils.php";

			$x = 0;
			$y = strlen($data);

			return self::Internal_ParseVersionInfoEntry($data, $x, $y, false, array("VS_VERSION_INFO" => true));
		}

		public static function GetVersionResource($winpe, $veridlang = true)
		{
			// Locate the version resource.
			$result = $winpe->FindResource(WinPEFile::RT_VERSION, 1, $veridlang);
			if ($result === false)  return array("success" => false, "error" => "Version information resource not found.", "errorcode" => "missing_version_resource");

			$vernum = $result["num"];
			$verentry = $result["entry"];

			$result = array(
				"success" => true,
				"ver_num" => $vernum,
				"ver_id" => $verentry["id"]
			);

			$result2 = self::ParseVersionInfoData($verentry["data"]);
			if (!$result2["success"])  return $result2;

			return $result + $result2;
		}

		public static function GenerateVersionInfoData($verinfo)
		{
			if (!isset($verinfo["key"]) || $verinfo["key"] !== "VS_VERSION_INFO")  return array("success" => false, "error" => "Expected VS_VERSION_INFO key to exist.", "errorcode" => "missing_vs_version_info_key");
			if (!isset($verinfo["entry"]) || !is_array($verinfo["entry"]))  return array("success" => false, "error" => "Expected VS_VERSION_INFO entry to exist.", "errorcode" => "missing_vs_version_info_entry");

			if (!class_exists("UTFUtils", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/utf_utils.php";

			// Start building the data.  Come back to the beginning later to fill in the final size.
			$data = "";
			$x = 0;
			WinPEFile::SetUInt16($data, $x, 0);
			WinPEFile::SetUInt16($data, $x, (isset($verinfo["entry"]["fixed"]) ? 52 : 0));
			WinPEFile::SetUInt16($data, $x, (isset($verinfo["entry"]["type"]) ? $verinfo["entry"]["type"] : 0));
			self::SetUnicodeStr($data, $x, "VS_VERSION_INFO");
			if ($x !== WinPEFile::AlignValue($x, 4))  WinPEFile::SetUInt16($data, $x, 0);

			// Fixed file information.
			if (isset($verinfo["entry"]["fixed"]))
			{
				// Signature and structure version are hardcoded.
				WinPEFile::SetUInt32($data, $x, (int)0xFEEF04BD);
				WinPEFile::SetUInt16($data, $x, 0);
				WinPEFile::SetUInt16($data, $x, 1);

				$ver = explode(".", $verinfo["entry"]["fixed"]["file_ver"]);
				while (count($ver) < 4)  $ver[] = 0;
				WinPEFile::SetUInt16($data, $x, (int)$ver[1]);
				WinPEFile::SetUInt16($data, $x, (int)$ver[0]);
				WinPEFile::SetUInt16($data, $x, (int)$ver[3]);
				WinPEFile::SetUInt16($data, $x, (int)$ver[2]);

				$ver = explode(".", $verinfo["entry"]["fixed"]["product_ver"]);
				while (count($ver) < 4)  $ver[] = 0;
				WinPEFile::SetUInt16($data, $x, (int)$ver[1]);
				WinPEFile::SetUInt16($data, $x, (int)$ver[0]);
				WinPEFile::SetUInt16($data, $x, (int)$ver[3]);
				WinPEFile::SetUInt16($data, $x, (int)$ver[2]);

				WinPEFile::SetUInt32($data, $x, WinPEFile::VERINFO_VS_FFI_FILEFLAGSMASK);
				WinPEFile::SetUInt32($data, $x, WinPEFile::VERINFO_VS_FFI_FILEFLAGSMASK & $verinfo["entry"]["fixed"]["flags"]);
				WinPEFile::SetUInt32($data, $x, $verinfo["entry"]["fixed"]["os"]);
				WinPEFile::SetUInt32($data, $x, $verinfo["entry"]["fixed"]["type"]);
				WinPEFile::SetUInt32($data, $x, $verinfo["entry"]["fixed"]["subtype"]);

				$date = "";
				$dx = 0;
				WinPEFile::SetUInt64($date, $dx, $verinfo["entry"]["fixed"]["date"]);
				WinPEFile::SetBytes($data, $x, substr($date, 4), 4);
				WinPEFile::SetBytes($data, $x, substr($date, 0, 4), 4);
			}

			// StringFileInfo.
			if (isset($verinfo["entry"]["string_file_info"]) && isset($verinfo["entry"]["string_file_info"]["string_tables"]))
			{
				$x2 = $x;
				WinPEFile::SetUInt16($data, $x, 0);
				WinPEFile::SetUInt16($data, $x, 0);
				WinPEFile::SetUInt16($data, $x, (isset($verinfo["entry"]["string_file_info"]["type"]) ? $verinfo["entry"]["string_file_info"]["type"] : 1));
				self::SetUnicodeStr($data, $x, "StringFileInfo");
				if ($x !== WinPEFile::AlignValue($x, 4))  WinPEFile::SetUInt16($data, $x, 0);

				foreach ($verinfo["entry"]["string_file_info"]["string_tables"] as $key => $stringmap)
				{
					// StringTable.
					$key = preg_replace('/[^0-9a-f]/', "", strtolower($key));

					if (strlen($key) != 8)  return array("success" => false, "error" => "An invalid StringTable key was encountered (" . $key . ").  Required to be an 8 byte hex code.", "errorcode" => "invalid_string_table_key");
					if (!count($stringmap))  return array("success" => false, "error" => "At least one String mapping must exist for a StringTable.", "errorcode" => "missing_string_map");

					$x3 = $x;
					WinPEFile::SetUInt16($data, $x, 0);
					WinPEFile::SetUInt16($data, $x, 0);
					WinPEFile::SetUInt16($data, $x, 1);
					self::SetUnicodeStr($data, $x, $key);

					ksort($stringmap);

					foreach ($stringmap as $key2 => $val)
					{
						if ($x !== WinPEFile::AlignValue($x, 4))  WinPEFile::SetUInt16($data, $x, 0);

						// String.
						$x4 = $x;
						WinPEFile::SetUInt16($data, $x, 0);
						WinPEFile::SetUInt16($data, $x, 0);
						WinPEFile::SetUInt16($data, $x, 1);
						self::SetUnicodeStr($data, $x, $key2);
						if ($x !== WinPEFile::AlignValue($x, 4))  WinPEFile::SetUInt16($data, $x, 0);
						$x5 = $x;
						self::SetUnicodeStr($data, $x, $val);

						WinPEFile::SetUInt16($data, $x4, $x - $x4);
						WinPEFile::SetUInt16($data, $x4, ($x - $x5) / 2);
					}

					WinPEFile::SetUInt16($data, $x3, $x - $x3);
				}

				WinPEFile::SetUInt16($data, $x2, $x - $x2);

				if ($x !== WinPEFile::AlignValue($x, 4))  WinPEFile::SetUInt16($data, $x, 0);
			}

			// VarFileInfo.
			if (isset($verinfo["entry"]["var_file_info"]))
			{
				$x2 = $x;
				WinPEFile::SetUInt16($data, $x, 0);
				WinPEFile::SetUInt16($data, $x, 0);
				WinPEFile::SetUInt16($data, $x, (isset($verinfo["entry"]["var_file_info"]["type"]) ? $verinfo["entry"]["var_file_info"]["type"] : 1));
				self::SetUnicodeStr($data, $x, "VarFileInfo");
				if ($x !== WinPEFile::AlignValue($x, 4))  WinPEFile::SetUInt16($data, $x, 0);

				if (isset($verinfo["entry"]["var_file_info"]["translations"]))
				{
					$x3 = $x;
					WinPEFile::SetUInt16($data, $x, 0);
					WinPEFile::SetUInt16($data, $x, 0);
					WinPEFile::SetUInt16($data, $x, 0);
					self::SetUnicodeStr($data, $x, "Translation");
					if ($x !== WinPEFile::AlignValue($x, 4))  WinPEFile::SetUInt16($data, $x, 0);

					$x4 = $x;
					foreach ($verinfo["entry"]["var_file_info"]["translations"] as $translation)
					{
						if (!isset($translation["lang_id"]) || !isset($translation["code_page"]))  return array("success" => false, "error" => "Missing Translation table 'lang_id' or 'code_page'", "errorcode" => "missing_lang_id_or_code_page");

						WinPEFile::SetUInt16($data, $x, $translation["lang_id"]);
						WinPEFile::SetUInt16($data, $x, $translation["code_page"]);
					}

					WinPEFile::SetUInt16($data, $x3, $x - $x3);
					WinPEFile::SetUInt16($data, $x3, $x - $x4);
				}

				WinPEFile::SetUInt16($data, $x2, $x - $x2);
			}

			$x2 = 0;
			WinPEFile::SetUInt16($data, $x2, $x);

			return array("success" => true, "data" => $data);
		}

		public static function SetVersionResource($winpe, &$data, $verinfo, $veridlang = true)
		{
			// Generate the binary VS_VERSION_INFO data.
			$result = self::GenerateVersionInfoData($verinfo);
			if (!$result["success"])  return $result;

			$verdata = $result["data"];

			// Locate the version resource.
			$result = $winpe->FindResource(WinPEFile::RT_VERSION, 1, $veridlang);
			if ($result === false)
			{
				// A version resource wasn't found/doesn't exist, so create one.
				$vernum = $winpe->CreateResourceLangNode(WinPEFile::RT_VERSION, 1, $veridlang, $verdata);

				return array("success" => true, "ver_num" => $vernum);
			}

			$vernum = $result["num"];

			// Overwrite the version resource.
			$winpe->OverwriteResourceData($data, $vernum, $verdata);

			return array("success" => true, "ver_num" => $vernum);
		}

		public static function CalculatePEImportsDirectoryOffsets($winpe, &$direntries)
		{
			$diroffset = 0;
			$namesize = 0;
			$bits64 = ($winpe->pe_opt_header["signature"] === WinPEFile::OPT_HEADER_SIGNATURE_PE32_PLUS);

			foreach ($direntries as &$direntry)
			{
				$diroffset += (count($direntry["imports"]) + 1) * ($bits64 ? 8 : 4);

				$namesize += WinPEFile::AlignValue(strlen($direntry["name"]) + 1, 2);

				foreach ($direntry["imports"] as $import)
				{
					if ($import["type"] === "named" && $import["rva"] === false)  $namesize += WinPEFile::AlignValue(2 + strlen($import["name"]) + 1, 2);
				}
			}

			$nametableoffset = $diroffset + (count($direntries) + 1) * 20;
			$namesoffset = $nametableoffset + $diroffset;

			return array("diroffset" => $diroffset, "nametableoffset" => $nametableoffset, "namesoffset" => $namesoffset, "namesize" => $namesize, "size" => WinPEFile::AlignValue($namesoffset + $namesize, 4));
		}

		public static function SavePEImportsDirectory($winpe, &$data, $secnum, $baserva, &$direntries)
		{
			// Initialize section information.
			if ($secnum === false)
			{
				// Check the last section in the file if it has import directory-compatible flags OR create a new section.
				$secnum = $winpe->GetLastPESectionIfAtEnd($data, WinPEFile::IMAGE_SCN_CNT_INITIALIZED_DATA | WinPEFile::IMAGE_SCN_MEM_READ | (int)WinPEFile::IMAGE_SCN_MEM_WRITE);
				if ($secnum === false)
				{
					$result = $winpe->CreateNewPESection($data, ".idata", 0, WinPEFile::IMAGE_SCN_CNT_INITIALIZED_DATA | WinPEFile::IMAGE_SCN_MEM_READ | (int)WinPEFile::IMAGE_SCN_MEM_WRITE);
					if (!$result["success"])  return $result;

					$secnum = $result["num"];
				}
			}

			$expandable = ($winpe->GetLastPESectionIfAtEnd($data) === $secnum);

			$rva = $winpe->pe_sections[$secnum]["rva"];
			if ($baserva === false)  $baserva = $rva;
			if (WinPEFile::AlignValue($baserva, 4) !== $baserva)  return array("success" => false, "error" => "Import directory base RVA is not DWORD aligned.", "errorcode" => "invalid_baserva");
			if ($baserva < $rva)  return array("success" => false, "error" => "Import directory base RVA is less than the section RVA.", "errorcode" => "invalid_baserva");

			$basepos = $baserva - $rva + $winpe->pe_sections[$secnum]["raw_data_ptr"];

			// Calculate offsets.
			$info = self::CalculatePEImportsDirectoryOffsets($winpe, $direntries);

			if ($baserva - $rva + $info["size"] > $winpe->pe_sections[$secnum]["raw_data_size"])
			{
				if (!$expandable)  return array("success" => false, "error" => "Import directory is bigger than the PE section and the PE section can't be resized.", "errorcode" => "imports_too_big");

				// Expand the section.
				$result = $winpe->ExpandLastPESection($data, $baserva - $rva + $info["size"] - $winpe->pe_sections[$secnum]["raw_data_size"]);
				if (!$result["success"])  return $result;
			}

			$iatrva = $baserva;
			$iatpos = $basepos;
			$dirrva = $baserva + $info["diroffset"];
			$dirpos = $basepos + $info["diroffset"];
			$nametablerva = $baserva + $info["nametableoffset"];
			$nametablepos = $basepos + $info["nametableoffset"];
			$namesrva = $baserva + $info["namesoffset"];
			$namespos = $basepos + $info["namesoffset"];

			// Generate imports table.
			$bits64 = ($winpe->pe_opt_header["signature"] === WinPEFile::OPT_HEADER_SIGNATURE_PE32_PLUS);

			foreach ($direntries as &$direntry)
			{
				$direntry["import_names_rva"] = $nametablerva;
				$direntry["name_rva"] = $namesrva;
				$direntry["iat_rva"] = $iatrva;

				$size = WinPEFile::AlignValue(strlen($direntry["name"]) + 1, 2);
				WinPEFile::SetBytes($data, $namespos, $direntry["name"], $size);
				$namesrva += $size;

				WinPEFile::SetUInt32($data, $dirpos, $direntry["import_names_rva"]);
				WinPEFile::SetUInt32($data, $dirpos, $direntry["created"]);
				WinPEFile::SetUInt32($data, $dirpos, $direntry["forward_chain"]);
				WinPEFile::SetUInt32($data, $dirpos, $direntry["name_rva"]);
				WinPEFile::SetUInt32($data, $dirpos, $direntry["iat_rva"]);

				// Prepare the name table first.
				foreach ($direntry["imports"] as &$import)
				{
					if ($import["type"] === "ord")
					{
						WinPEFile::SetUInt16($data, $nametablepos, $import["ord"]);

						if ($bits64)  WinPEFile::SetBytes($data, $nametablepos, "\x00\x00\x00\x00\x00\x80", 6);
						else  WinPEFile::SetBytes($data, $nametablepos, "\x00\x80", 2);
					}
					else if ($import["type"] === "named")
					{
						if ($import["rva"] === false)
						{
							$import["rva"] = $namesrva;

							WinPEFile::SetUInt16($data, $namespos, $import["hint"]);

							$size = WinPEFile::AlignValue(strlen($import["name"]) + 1, 2);
							WinPEFile::SetBytes($data, $namespos, $import["name"], $size);
							$namesrva += $size + 2;
						}

						WinPEFile::SetUInt32($data, $nametablepos, $import["rva"]);

						if ($bits64)  WinPEFile::SetBytes($data, $nametablepos, "", 4);
					}
					else
					{
						return array("success" => false, "error" => "An invalid import type (" . $import["type"] . ") was encountered.", "errorcode" => "invalid_import_type");
					}

					$nametablerva += ($bits64 ? 8 : 4);
					$iatrva += ($bits64 ? 8 : 4);
				}

				if ($bits64)
				{
					WinPEFile::SetBytes($data, $nametablepos, "", 8);
					$nametablerva += 8;
					$iatrva += 8;
				}
				else
				{
					WinPEFile::SetBytes($data, $nametablepos, "", 4);
					$nametablerva += 4;
					$iatrva += 4;
				}
			}

			WinPEFile::SetBytes($data, $dirpos, "", 20);

			// Copy the name table to the IAT.
			$size = $info["namesoffset"] - $info["nametableoffset"];
			$iatdata = substr($data, $basepos + $info["nametableoffset"], $size);
			WinPEFile::SetBytes($data, $iatpos, $iatdata, $size);

			// Update imports and IAT directory tables.
			$winpe->pe_data_dir["imports"]["rva"] = $baserva + $info["diroffset"];
			$winpe->pe_data_dir["imports"]["size"] = $info["nametableoffset"] - $info["diroffset"];
			$winpe->pe_data_dir["imports"]["dir_entries"] = $direntries;

			$winpe->pe_data_dir["iat"]["rva"] = $baserva;
			$winpe->pe_data_dir["iat"]["size"] = $size;
			$winpe->pe_data_dir["iat"]["data"] = $iatdata;

			// Save the headers.
			$winpe->SaveHeaders($data);

			return array("success" => true, "size" => $info["size"]);
		}

		public static function CalculatePEExportsDirectoryOffsets(&$exportdir, &$addresses, &$namemap)
		{
			$addressoffset = 40;
			$nameptroffset = $addressoffset + (count($addresses) * 4);
			$ordinaloffset = $nameptroffset + (count($namemap) * 4);
			$namesoffset = $ordinaloffset + (count($namemap) * 2);

			$namesize = strlen($exportdir["name"]) + 1;

			foreach ($addresses as $addr)
			{
				if ($addr["type"] === "forward")  $namesize += strlen($addr["name"]) + 1;
			}

			foreach ($namemap as $name => $ord)
			{
				$namesize += strlen($addr["name"]) + 1;
			}

			return array("addressoffset" => $addressoffset, "nameptroffset" => $nameptroffset, "ordinaloffset" => $ordinaloffset, "namesoffset" => $namesoffset, "namesize" => $namesize, "size" => $namesoffset + $namesize);
		}

		public static function SavePEExportsDirectory($winpe, &$data, $secnum, $baserva, &$exportdir, &$addresses, &$namemap)
		{
			// Initialize section information.
			if ($secnum === false)
			{
				// Check the last section in the file if it has import directory-compatible flags OR create a new section.
				$secnum = $winpe->GetLastPESectionIfAtEnd($data, WinPEFile::IMAGE_SCN_CNT_INITIALIZED_DATA | WinPEFile::IMAGE_SCN_MEM_READ);
				if ($secnum === false)
				{
					$result = $winpe->CreateNewPESection($data, ".edata", 0, WinPEFile::IMAGE_SCN_CNT_INITIALIZED_DATA | WinPEFile::IMAGE_SCN_MEM_READ);
					if (!$result["success"])  return $result;

					$secnum = $result["num"];
				}
			}

			$expandable = ($winpe->GetLastPESectionIfAtEnd($data) === $secnum);

			$rva = $winpe->pe_sections[$secnum]["rva"];
			if ($baserva === false)  $baserva = $rva;
			if (WinPEFile::AlignValue($baserva, 4) !== $baserva)  return array("success" => false, "error" => "Export directory base RVA is not DWORD aligned.", "errorcode" => "invalid_baserva");
			if ($baserva < $rva)  return array("success" => false, "error" => "Export directory base RVA is less than the section RVA.", "errorcode" => "invalid_baserva");

			$basepos = $baserva - $rva + $winpe->pe_sections[$secnum]["raw_data_ptr"];

			// Calculate offsets.
			$info = self::CalculatePEExportsDirectoryOffsets($exportdir, $addresses, $namemap);

			if ($baserva - $rva + $info["size"] > $winpe->pe_sections[$secnum]["raw_data_size"])
			{
				if (!$expandable)  return array("success" => false, "error" => "Export directory is bigger than the PE section and the PE section can't be resized.", "errorcode" => "exports_too_big");

				// Expand the section.
				$result = $winpe->ExpandLastPESection($data, $baserva - $rva + $info["size"] - $winpe->pe_sections[$secnum]["raw_data_size"]);
				if (!$result["success"])  return $result;
			}

			$dirrva = $baserva;
			$dirpos = $basepos;
			$addressrva = $baserva + $info["addressoffset"];
			$addresspos = $basepos + $info["addressoffset"];
			$nameptrrva = $baserva + $info["nameptroffset"];
			$nameptrpos = $basepos + $info["nameptroffset"];
			$ordinalrva = $baserva + $info["ordinaloffset"];
			$ordinalpos = $basepos + $info["ordinaloffset"];
			$namesrva = $baserva + $info["namesoffset"];
			$namespos = $basepos + $info["namesoffset"];

			// Generate exports table.
			$exportdir["name_rva"] = $namesrva;

			$size = strlen($exportdir["name"]) + 1;
			WinPEFile::SetBytes($data, $namespos, $exportdir["name"], $size);
			$namesrva += $size;

			$exportdir["num_addresses"] = count($addresses);
			$exportdir["num_name_ptrs"] = count($namemap);
			$exportdir["addresses_rva"] = $addressrva;
			$exportdir["name_ptr_rva"] = $nameptrrva;
			$exportdir["ordinal_map_rva"] = $ordinalrva;

			WinPEFile::SetUInt32($data, $dirpos, $exportdir["flags"]);
			WinPEFile::SetUInt32($data, $dirpos, $exportdir["created"]);
			WinPEFile::SetUInt16($data, $dirpos, $exportdir["major_ver"]);
			WinPEFile::SetUInt16($data, $dirpos, $exportdir["minor_ver"]);
			WinPEFile::SetUInt32($data, $dirpos, $exportdir["name_rva"]);
			WinPEFile::SetUInt32($data, $dirpos, $exportdir["ordinal_base"]);
			WinPEFile::SetUInt32($data, $dirpos, $exportdir["num_addresses"]);
			WinPEFile::SetUInt32($data, $dirpos, $exportdir["num_name_ptrs"]);
			WinPEFile::SetUInt32($data, $dirpos, $exportdir["addresses_rva"]);
			WinPEFile::SetUInt32($data, $dirpos, $exportdir["name_ptr_rva"]);
			WinPEFile::SetUInt32($data, $dirpos, $exportdir["ordinal_map_rva"]);

			foreach ($addresses as $addr)
			{
				if ($addr["type"] === "export")  WinPEFile::SetUInt32($data, $addresspos, $addr["rva"]);
				else if ($addr["type"] === "forward")
				{
					WinPEFile::SetUInt32($data, $addresspos, $namesrva);

					$size = strlen($addr["name"]) + 1;
					WinPEFile::SetBytes($data, $namespos, $addr["name"], $size);
					$namesrva += $size;
				}
				else
				{
					return array("success" => false, "error" => "An invalid export address type (" . $addr["type"] . ") was encountered.", "errorcode" => "invalid_export_address_type");
				}
			}

			ksort($namemap);

			foreach ($namemap as $name => $ord)
			{
				WinPEFile::SetUInt32($data, $nameptrpos, $namesrva);

				$size = strlen($name) + 1;
				WinPEFile::SetBytes($data, $namespos, $name, $size);
				$namesrva += $size;

				WinPEFile::SetUInt16($data, $ordinalpos, $ord);
			}

			// Update exports directory table.
			$winpe->pe_data_dir["exports"]["rva"] = $baserva;
			$winpe->pe_data_dir["exports"]["size"] = $info["size"];
			$winpe->pe_data_dir["exports"]["dir"] = $exportdir;
			$winpe->pe_data_dir["exports"]["addresses"] = $addresses;
			$winpe->pe_data_dir["exports"]["namemap"] = $namemap;

			// Save the headers.
			$winpe->SaveHeaders($data);

			return array("success" => true, "size" => WinPEFile::AlignValue($info["size"], 4));
		}

		public static function CalculatePEBaseRelocationsDirectorySize(&$blocks)
		{
			$size = 0;

			foreach ($blocks as &$block)
			{
				$size += 8;

				foreach ($block["offsets"] as $offset)
				{
					$size += 2;

					if ($offset["type"] === WinPEFile::IMAGE_REL_BASED_HIGHADJ)  $size += 2;
				}

				$size = WinPEFile::AlignValue($size, 4);
			}

			return $size;
		}

		public static function SavePEBaseRelocationsDirectory($winpe, &$data, $secnum, $baserva, &$blocks)
		{
			// Initialize section information.
			if ($secnum === false)
			{
				// Check the last section in the file if it has import directory-compatible flags OR create a new section.
				$secnum = $winpe->GetLastPESectionIfAtEnd($data, WinPEFile::IMAGE_SCN_CNT_INITIALIZED_DATA | WinPEFile::IMAGE_SCN_MEM_READ);
				if ($secnum === false)
				{
					$result = $winpe->CreateNewPESection($data, ".edata", 0, WinPEFile::IMAGE_SCN_CNT_INITIALIZED_DATA | WinPEFile::IMAGE_SCN_MEM_READ);
					if (!$result["success"])  return $result;

					$secnum = $result["num"];
				}
			}

			$expandable = ($winpe->GetLastPESectionIfAtEnd($data) === $secnum);

			$rva = $winpe->pe_sections[$secnum]["rva"];
			if ($baserva === false)  $baserva = $rva;
			if (WinPEFile::AlignValue($baserva, 4) !== $baserva)  return array("success" => false, "error" => "Base relocations directory base RVA is not DWORD aligned.", "errorcode" => "invalid_baserva");
			if ($baserva < $rva)  return array("success" => false, "error" => "Base relocations directory base RVA is less than the section RVA.", "errorcode" => "invalid_baserva");

			$basepos = $baserva - $rva + $winpe->pe_sections[$secnum]["raw_data_ptr"];

			// Calculate offsets.
			$dirsize = self::CalculatePEBaseRelocationsDirectorySize($blocks);

			if ($baserva - $rva + $dirsize > $winpe->pe_sections[$secnum]["raw_data_size"])
			{
				if (!$expandable)  return array("success" => false, "error" => "Base relocations directory is bigger than the PE section and the PE section can't be resized.", "errorcode" => "exports_too_big");

				// Expand the section.
				$result = $winpe->ExpandLastPESection($data, $baserva - $rva + $dirsize - $winpe->pe_sections[$secnum]["raw_data_size"]);
				if (!$result["success"])  return $result;
			}

			$tablepos = $basepos;

			// Generate exports table.
			foreach ($blocks as &$block)
			{
				$size = 0;

				foreach ($block["offsets"] as $offset)
				{
					$size += 2;

					if ($offset["type"] === WinPEFile::IMAGE_REL_BASED_HIGHADJ)  $size += 2;
				}

				$size = WinPEFile::AlignValue($size, 4);

				WinPEFile::SetUInt32($data, $tablepos, $block["rva"]);
				WinPEFile::SetUInt32($data, $tablepos, $size + 8);

				foreach ($block["offsets"] as $offset)
				{
					WinPEFile::SetUInt16($data, $tablepos, (($offset["type"] << 12) | ($offset["offset"] & 0x0FFF)) & 0xFFFF);
					$size -= 2;

					if ($offset["type"] === WinPEFile::IMAGE_REL_BASED_HIGHADJ)
					{
						WinPEFile::SetUInt16($data, $tablepos, $offset["extra"] & 0xFFFF);

						$size -= 2;
					}
				}

				if ($size)  WinPEFile::SetBytes($data, $tablepos, "", $size);
			}

			// Update exports directory table.
			$winpe->pe_data_dir["base_relocations"]["rva"] = $baserva;
			$winpe->pe_data_dir["base_relocations"]["size"] = $dirsize;
			$winpe->pe_data_dir["base_relocations"]["blocks"] = $blocks;

			// Save the headers.
			$winpe->SaveHeaders($data);

			return array("success" => true, "size" => $dirsize);
		}

		public static function CreateHookDLL($origfilename, $hookfilename, $winpeorig, $winpehooks, $win9x = false)
		{
			// Make sure machine types and signatures match.
			if (!isset($winpeorig->pe_header) || !isset($winpeorig->pe_opt_header))  return array("success" => false, "error" => "Original DLL file is not a valid PE file.", "errorcode" => "invalid_pe_file");
			if (!isset($winpehooks->pe_header) || !isset($winpehooks->pe_opt_header))  return array("success" => false, "error" => "Hook DLL file is not a valid PE file.", "errorcode" => "invalid_pe_file");
			if ($winpeorig->pe_header["machine_type"] !== $winpehooks->pe_header["machine_type"])  return array("success" => false, "error" => "Original and hook DLL machine types do not match.", "errorcode" => "mismatched_machine_types");
			if ($winpeorig->pe_opt_header["signature"] !== $winpehooks->pe_opt_header["signature"])  return array("success" => false, "error" => "Original and hook DLL PE file signatures do not match.", "errorcode" => "mismatched_pe_opt_header_signatures");

			// The new filename should be the same length as the original filename but with a '.dll' extension.
			$filename = str_replace("\\", "/", $origfilename);
			$pos = strrpos($filename, "/");
			if ($pos !== false)  $filename = substr($filename, $pos + 1);
			$filename = strtolower($filename);

			if (strlen($filename) < 5)  return array("success" => false, "error" => "Original filename is too short.  Must be at least 5 bytes long.", "errorcode" => "origfilename_too_short");

			if (!class_exists("CSPRNG", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/random.php";

			$rng = new CSPRNG();

			$newfilenamenoext = strtoupper($rng->GenerateString(strlen($filename) - 4));
			$origfilenamenoext = strtoupper("ORIG_" . $rng->GenerateString(4) . "_" . str_replace(".", "_", $filename));

			$filename = str_replace("\\", "/", $hookfilename);
			$pos = strrpos($filename, "/");
			if ($pos !== false)  $filename = substr($filename, $pos + 1);
			$filename = strtolower($filename);

			$pos = strrpos($filename, ".");
			if ($pos !== false)  $filename = substr($filename, 0, $pos);
			$hookfilenamenoext = strtoupper(str_replace(".", "_", $filename));

			// Initialize a new PE file.
			$winpe = new WinPEFile();
			$winpe->InitPE($winpeorig->pe_opt_header["signature"]);

			$bits64 = ($winpe->pe_opt_header["signature"] === WinPEFile::OPT_HEADER_SIGNATURE_PE32_PLUS);

			$winpe->pe_header["machine_type"] = $winpeorig->pe_header["machine_type"];
			$winpe->pe_header["flags"] = WinPEFile::IMAGE_FILE_DLL | WinPEFile::IMAGE_FILE_EXECUTABLE_IMAGE | ($bits64 ? WinPEFile::IMAGE_FILE_LARGE_ADDRESS_AWARE : WinPEFile::IMAGE_FILE_32BIT_MACHINE);

			$winpe->pe_opt_header["major_linker_ver"] = 1;
			$winpe->pe_opt_header["image_base"] = WinPEFile::IMAGE_BASE_DLL_DEFAULT + ($rng->GetInt(0, 0x5FFF) << 16);
			$winpe->pe_opt_header["major_os_ver"] = 4;
			$winpe->pe_opt_header["minor_os_ver"] = 0;
			$winpe->pe_opt_header["major_image_ver"] = 1;
			$winpe->pe_opt_header["minor_image_ver"] = 0;
			$winpe->pe_opt_header["major_subsystem_ver"] = 4;
			$winpe->pe_opt_header["minor_subsystem_ver"] = 0;
			$winpe->pe_opt_header["subsystem"] = $winpeorig->pe_opt_header["subsystem"];
			$winpe->pe_opt_header["dll_characteristics"] = WinPEFile::IMAGE_DLL_CHARACTERISTICS_NO_SEH | WinPEFile::IMAGE_DLL_CHARACTERISTICS_DYNAMIC_BASE;

			// Normally 1MB is reserved for the stack and heap.
			// Since the generated DLL only executes a return command, only a 4K stack/heap is necessary.
			$winpe->pe_opt_header["stack_reserve_size"] = 0x00001000;
			$winpe->pe_opt_header["heap_reserve_size"] = 0x00001000;

			// Save the headers.
			$data = "";
			$winpe->SaveHeaders($data);

			// Create the section.
			$result = $winpe->CreateNewPESection($data, ".text", 0, WinPEFile::IMAGE_SCN_CNT_CODE | WinPEFile::IMAGE_SCN_MEM_EXECUTE | WinPEFile::IMAGE_SCN_MEM_READ);
			if (!$result["success"])  return $result;

			$secnum = $result["num"];

			// Get the starting RVA and position in the data.
			$nextrva = $winpe->pe_sections[$secnum]["rva"];
			$nextpos = $winpe->pe_sections[$secnum]["raw_data_ptr"];

			$winpe->pe_opt_header["code_base"] = $winpe->pe_sections[0]["rva"];
			$winpe->pe_opt_header["entry_point_addr"] = $winpe->pe_sections[0]["rva"];

			// Write out assembler instructions.
			if ($winpeorig->pe_header["machine_type"] === WinPEFile::IMAGE_FILE_MACHINE_AMD64)
			{
				// Expand the section.
				$result = $winpe->ExpandLastPESection($data, 8);
				$bytesleft = $result["size"];

				// mov eax, 1
				// ret
				WinPEFile::SetBytes($data, $nextpos, "\xB8\x01\x00\x00\x00\xC3\x00\x00", 8);
				$nextrva += 8;
				$bytesleft -= 8;

				$win9x = false;
			}
			else if ($winpeorig->pe_header["machine_type"] === WinPEFile::IMAGE_FILE_MACHINE_I386)
			{
				// Expand the section.
				$result = $winpe->ExpandLastPESection($data, 8);
				$bytesleft = $result["size"];

				// xor eax, eax
				// inc eax
				// ret 12 (return and pop 12 bytes off the stack)
				WinPEFile::SetBytes($data, $nextpos, "\x33\xC0\x40\xC2\x0C\x00\x00\x00", 8);
				$nextrva += 8;
				$bytesleft -= 8;
			}
			else
			{
				return array("success" => false, "error" => "Unknown machine type.  Unable to generate suitable assembler instructions.  Expected Intel x86/x64.", "errorcode" => "unknown_machine_type");
			}

			// Create a combined export table.
			$exportdir = array(
				"flags" => 0,
				"created" => time(),
				"major_ver" => 0,
				"minor_ver" => 0,
				"name_rva" => 0,
				"name" => $newfilenamenoext . ".dll",
				"ordinal_base" => (isset($winpeorig->pe_data_dir["exports"]["dir"]) ? $winpeorig->pe_data_dir["exports"]["dir"]["ordinal_base"] : 1),
				"num_addresses" => 0,
				"num_name_ptrs" => 0,
				"addresses_rva" => 0,
				"name_ptr_rva" => 0,
				"ordinal_map_rva" => 0
			);

			$addresses = array();
			$namemap = array();
			$origordmap = array();

			$hooksnamemap = (isset($winpehooks->pe_data_dir["exports"]["namemap"]) ? $winpehooks->pe_data_dir["exports"]["namemap"] : array());

			if (isset($winpeorig->pe_data_dir["exports"]["namemap"]))
			{
				// Add all addresses as ordinal forwards so that the address table is the same size and ordinal mappings from executables will be correct.
				foreach ($winpeorig->pe_data_dir["exports"]["addresses"] as $ord => $info)
				{
					$addresses[] = array(
						"type" => "forward",
						"name" => $origfilenamenoext . ".#" . $ord
					);
				}

				// Now replace with names.
				foreach ($winpeorig->pe_data_dir["exports"]["namemap"] as $name => $ord)
				{
					if (!isset($addresses[$ord]))  continue;

					$namemap[$name] = $ord;

					$addresses[$ord] = array(
						"type" => "forward",
						"name" => (isset($hooksnamemap[$name]) ? $hookfilenamenoext : $origfilenamenoext) . "." . $name
					);

					$origordmap[$name] = (isset($hooksnamemap[$name]) ? $hooksnamemap[$name] : $ord);

					unset($hooksnamemap[$name]);
				}
			}

			// Append other exports from the hook DLL (e.g. missing functions).
			foreach ($hooksnamemap as $name => $ord)
			{
				$namemap[$name] = count($addresses);

				$addresses[] = array(
					"type" => "forward",
					"name" => $hookfilenamenoext . "." . $name
				);

				$origordmap[$name] = (isset($hooksnamemap[$name]) ? $hooksnamemap[$name] : $ord);
			}

			ksort($namemap);

			// Win9x/Me doesn't support export forwarding.
			// However, the behavior can be simulated by creating a table of jmp ds:addr instructions into the IAT (aka Thunk table).
			$finalrelocs = array();
			if ($win9x)
			{
				// Calculate the starting address of the import table.
				// Instructions are only 6 bytes each but are 8-byte aligned to make relocation table calculations simpler.
				$size = count($addresses) * 8;

				// Expand the section as needed.
				if ($bytesleft < $size)
				{
					$result = $winpe->ExpandLastPESection($data, $size - $bytesleft);
					$bytesleft += $result["size"];
				}

				// Generate the various tables.
				$direntries = array();
				$baseiatrva = $nextrva + $size;
				$iatrva = $baseiatrva;
				$relocs = array(
					"rva" => $winpe->pe_sections[$secnum]["rva"],
					"offsets" => array()
				);

				// Original entries.
				$direntries[] = array(
					"import_names_rva" => 0,
					"created" => time(),
					"forward_chain" => 0,
					"name_rva" => 0,
					"name" => strtolower($origfilenamenoext) . ".dll",
					"prefix" => $origfilenamenoext . ".",
					"iat_rva" => 0,
					"imports" => array()
				);

				// Hook DLL entries.
				$direntries[] = array(
					"import_names_rva" => 0,
					"created" => time(),
					"forward_chain" => 0,
					"name_rva" => 0,
					"name" => strtolower($hookfilenamenoext) . ".dll",
					"prefix" => $hookfilenamenoext . ".",
					"iat_rva" => 0,
					"imports" => array()
				);

				$y = count($direntries);

				for ($x = 0; $x < $y; $x++)
				{
					$prefix = $direntries[$x]["prefix"];
					$y2 = strlen($prefix);

					foreach ($addresses as $num => &$info)
					{
						if (!strncmp($info["name"], $prefix, $y2))
						{
							// Point at the jmp instruction.
							$info["type"] = "export";
							$info["rva"] = $nextrva;

							if ($info["name"][$y2] === "#")  $direntries[$x]["imports"][] = array("type" => "ord", "ord" => (int)substr($info["name"], $y2 + 1));
							else
							{
								$name = substr($info["name"], $y2);

								$direntries[$x]["imports"][] = array("type" => "named", "rva" => false, "hint" => $origordmap[$name], "name" => $name);
							}

							// jmp ds:addr.  Jumps to the address in the IAT.
							WinPEFile::SetBytes($data, $nextpos, "\xFF\x25", 2);
							WinPEFile::SetUInt32($data, $nextpos, $winpe->pe_opt_header["image_base"] + $iatrva);
							WinPEFile::SetBytes($data, $nextpos, "\x00\x00", 2);

							// Relocation table entry.  Points at the jmp instruction address so the DLL can be relocated.
							$relocs["offsets"][] = array("type" => WinPEFile::IMAGE_REL_BASED_HIGHLOW, "offset" => (($nextrva + 2) & 0x0FFF));

							$iatrva += 4;
							$nextrva += 8;
							$bytesleft -= 8;

							// If a new block is encountered, finish the previous relocations block.
							if ($nextrva % 0x1000 == 0)
							{
								if (count($relocs["offsets"]))  $finalrelocs[] = $relocs;

								$relocs = array(
									"rva" => $nextrva,
									"offsets" => array()
								);
							}
						}
					}

					$iatrva += 4;
				}

				// Finalize relocations.
				if (count($relocs["offsets"]))  $finalrelocs[] = $relocs;

				// Write the imports directory.
				$result = self::SavePEImportsDirectory($winpe, $data, $secnum, $baseiatrva, $direntries);
				if (!$result["success"])  return $result;

				$nextrva = $baseiatrva + $result["size"];
			}

			// Write the exports directory.
			$result = self::SavePEExportsDirectory($winpe, $data, $secnum, $nextrva, $exportdir, $addresses, $namemap);
			if (!$result["success"])  return $result;

			// Write the relocations directory.
			if (count($finalrelocs))
			{
				// Create the section.
				$result = $winpe->CreateNewPESection($data, ".reloc", 0, WinPEFile::IMAGE_SCN_CNT_INITIALIZED_DATA | WinPEFile::IMAGE_SCN_MEM_DISCARDABLE | WinPEFile::IMAGE_SCN_MEM_READ);
				if (!$result["success"])  return $result;

				$secnum = $result["num"];

				$rva = $winpe->pe_sections[$secnum]["rva"];

				$result = self::SavePEBaseRelocationsDirectory($winpe, $data, $secnum, $rva, $finalrelocs);
				if (!$result["success"])  return $result;
			}

			// Update the checksum.
			$winpe->UpdateChecksum($data);

			return array("success" => true, "winpe" => $winpe, "data" => $data, "filename" => strtolower($newfilenamenoext) . ".dll", "origfilename" => strtolower($origfilenamenoext) . ".dll", "hookfilename" => strtolower($hookfilenamenoext) . ".dll");
		}
	}
?>