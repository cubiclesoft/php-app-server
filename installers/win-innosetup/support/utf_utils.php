<?php
	// CubicleSoft PHP UTF (Unicode) utility functions.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	class UTFUtils
	{
		const UTF8 = 1;
		const UTF8_BOM = 2;
		const UTF16_LE = 3;
		const UTF16_BE = 4;
		const UTF16_BOM = 5;
		const UTF32_LE = 6;
		const UTF32_BE = 7;
		const UTF32_BOM = 8;
		const UTF32_ARRAY = 9;

		// Checks a numeric value to see if it is a combining code point.
		public static function IsCombiningCodePoint($val)
		{
			return (($val >= 0x0300 && $val <= 0x036F) || ($val >= 0x1DC0 && $val <= 0x1DFF) || ($val >= 0x20D0 && $val <= 0x20FF) || ($val >= 0xFE20 && $val <= 0xFE2F));
		}

		public static function Convert($data, $srctype, $desttype)
		{
			$arr = is_array($data);
			if ($arr)  $srctype = self::UTF32_ARRAY;
			$x = 0;
			$y = ($arr ? count($data) : strlen($data));
			$result = ($desttype === self::UTF32_ARRAY ? array() : "");
			if (!$arr && $srctype === self::UTF32_ARRAY)  return $result;

			$first = true;

			if ($srctype === self::UTF8_BOM)
			{
				if (substr($data, 0, 3) === "\xEF\xBB\xBF")  $x = 3;

				$srctype = self::UTF8;
			}

			if ($srctype === self::UTF16_BOM)
			{
				if (substr($data, 0, 2) === "\xFE\xFF")
				{
					$srctype = self::UTF16_BE;
					$x = 2;
				}
				else if (substr($data, 0, 2) === "\xFF\xFE")
				{
					$srctype = self::UTF16_LE;
					$x = 2;
				}
				else
				{
					$srctype = self::UTF16_LE;
				}
			}

			if ($srctype === self::UTF32_BOM)
			{
				if (substr($data, 0, 4) === "\x00\x00\xFE\xFF")
				{
					$srctype = self::UTF32_BE;
					$x = 4;
				}
				else if (substr($data, 0, 4) === "\xFF\xFE\x00\x00")
				{
					$srctype = self::UTF32_LE;
					$x = 4;
				}
				else
				{
					$srctype = self::UTF32_LE;
				}
			}

			while ($x < $y)
			{
				// Read the next valid code point.
				$val = false;

				switch ($srctype)
				{
					case self::UTF8:
					{
						$tempchr = ord($data[$x]);
						if ($tempchr <= 0x7F)
						{
							$val = $tempchr;
							$x++;
						}
						else if ($tempchr < 0xC2)  $x++;
						else
						{
							$left = $y - $x;
							if ($left < 2)  $x++;
							else
							{
								$tempchr2 = ord($data[$x + 1]);

								if (($tempchr >= 0xC2 && $tempchr <= 0xDF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF))
								{
									$val = (($tempchr & 0x1F) << 6) | ($tempchr2 & 0x3F);
									$x += 2;
								}
								else if ($left < 3)  $x++;
								else
								{
									$tempchr3 = ord($data[$x + 2]);

									if ($tempchr3 < 0x80 || $tempchr3 > 0xBF)  $x++;
									else
									{
										if (($tempchr == 0xE0 && ($tempchr2 >= 0xA0 && $tempchr2 <= 0xBF)) || ((($tempchr >= 0xE1 && $tempchr <= 0xEC) || $tempchr == 0xEE || $tempchr == 0xEF) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF)) || ($tempchr == 0xED && ($tempchr2 >= 0x80 && $tempchr2 <= 0x9F)))
										{
											$val = (($tempchr & 0x0F) << 12) | (($tempchr2 & 0x3F) << 6) | ($tempchr3 & 0x3F);
											$x += 3;
										}
										else if ($left < 4)  $x++;
										else
										{
											$tempchr4 = ord($data[$x + 3]);

											if ($tempchr4 < 0x80 || $tempchr4 > 0xBF)  $x++;
											else if (($tempchr == 0xF0 && ($tempchr2 >= 0x90 && $tempchr2 <= 0xBF)) || (($tempchr >= 0xF1 && $tempchr <= 0xF3) && ($tempchr2 >= 0x80 && $tempchr2 <= 0xBF)) || ($tempchr == 0xF4 && ($tempchr2 >= 0x80 && $tempchr2 <= 0x8F)))
											{
												$val = (($tempchr & 0x07) << 18) | (($tempchr2 & 0x3F) << 12) | (($tempchr3 & 0x3F) << 6) | ($tempchr4 & 0x3F);
												$x += 4;
											}
											else
											{
												$x++;
											}
										}
									}
								}
							}
						}

						break;
					}
					case self::UTF16_LE:
					{
						if ($x + 1 >= $y)  $x = $y;
						else
						{
							$val = unpack("v", substr($data, $x, 2))[1];
							$x += 2;

							if ($val >= 0xD800 && $val <= 0xDBFF)
							{
								if ($x + 1 >= $y)
								{
									$x = $y;
									$val = false;
								}
								else
								{
									$val2 = unpack("v", substr($data, $x, 2))[1];

									if ($val2 < 0xDC00 || $val2 > 0xDFFF)  $val = false;
									else
									{
										$val = ((($val - 0xD800) << 10) | ($val2 - 0xDC00)) + 0x10000;
										$x += 2;
									}
								}
							}
						}

						break;
					}
					case self::UTF16_BE:
					{
						if ($x + 1 >= $y)  $x = $y;
						else
						{
							$val = unpack("n", substr($data, $x, 2))[1];
							$x += 2;

							if ($val >= 0xD800 && $val <= 0xDBFF)
							{
								if ($x + 1 >= $y)
								{
									$x = $y;
									$val = false;
								}
								else
								{
									$val2 = unpack("n", substr($data, $x, 2))[1];

									if ($val2 < 0xDC00 || $val2 > 0xDFFF)  $val = false;
									else
									{
										$val = ((($val - 0xD800) << 10) | ($val2 - 0xDC00)) + 0x10000;
										$x += 2;
									}
								}
							}
						}

						break;
					}
					case self::UTF32_LE:
					{
						if ($x + 3 >= $y)  $x = $y;
						else
						{
							$val = unpack("V", substr($data, $x, 4))[1];
							$x += 4;
						}

						break;
					}
					case self::UTF32_BE:
					{
						if ($x + 3 >= $y)  $x = $y;
						else
						{
							$val = unpack("N", substr($data, $x, 4))[1];
							$x += 4;
						}

						break;
					}
					case self::UTF32_ARRAY:
					{
						$val = (int)$data[$x];
						$x++;

						break;
					}
					default:  $x = $y;  break;
				}

				// Make sure it is a valid Unicode value.
				// 0xD800-0xDFFF are for UTF-16 surrogate pairs.  Invalid characters.
				// 0xFDD0-0xFDEF are non-characters.
				// 0x*FFFE and 0x*FFFF are reserved.
				// The largest possible character is 0x10FFFF.
				// First character can't be a combining code point.
				if ($val !== false && !($val < 0 || ($val >= 0xD800 && $val <= 0xDFFF) || ($val >= 0xFDD0 && $val <= 0xFDEF) || ($val & 0xFFFE) == 0xFFFE || $val > 0x10FFFF || ($first && self::IsCombiningCodePoint($val))))
				{
					if ($first)
					{
						if ($desttype === self::UTF8_BOM)
						{
							$result .= "\xEF\xBB\xBF";

							$desttype = self::UTF8;
						}

						if ($desttype === self::UTF16_BOM)
						{
							$result .= "\xFF\xFE";

							$desttype = self::UTF16_LE;
						}

						if ($srctype === self::UTF32_BOM)
						{
							$result .= "\xFF\xFE\x00\x00";

							$desttype = self::UTF32_LE;
						}

						$first = false;
					}

					switch ($desttype)
					{
						case self::UTF8:
						{
							if ($val <= 0x7F)  $result .= chr($val);
							else if ($val <= 0x7FF)  $result .= chr(0xC0 | ($val >> 6)) . chr(0x80 | ($val & 0x3F));
							else if ($val <= 0xFFFF)  $result .= chr(0xE0 | ($val >> 12)) . chr(0x80 | (($val >> 6) & 0x3F)) . chr(0x80 | ($val & 0x3F));
							else if ($val <= 0x10FFFF)  $result .= chr(0xF0 | ($val >> 18)) . chr(0x80 | (($val >> 12) & 0x3F)) . chr(0x80 | (($val  >> 6) & 0x3F)) . chr(0x80 | ($val & 0x3F));

							break;
						}
						case self::UTF16_LE:
						{
							if ($val <= 0xFFFF)  $result .= pack("v", $val);
							else
							{
								$val -= 0x10000;
								$result .= pack("v", ((($val >> 10) & 0x3FF) + 0xD800));
								$result .= pack("v", (($val & 0x3FF) + 0xDC00));
							}

							break;
						}
						case self::UTF16_BE:
						{
							if ($val <= 0xFFFF)  $result .= pack("n", $val);
							else
							{
								$val -= 0x10000;
								$result .= pack("n", ((($val >> 10) & 0x3FF) + 0xD800));
								$result .= pack("n", (($val & 0x3FF) + 0xDC00));
							}

							break;
						}
						case self::UTF32_LE:
						{
							$result .= pack("V", $val);

							break;
						}
						case self::UTF32_BE:
						{
							$result .= pack("N", $val);

							break;
						}
						case self::UTF32_ARRAY:
						{
							$result[] = $val;

							break;
						}
						default:  $x = $y;  break;
					}
				}
			}

			return $result;
		}
	}
?>