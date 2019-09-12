<?php
	// XTerm ANSI escape code emitter.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	// Sources:
	//   https://invisible-island.net/xterm/ctlseqs/ctlseqs.html
	//   https://vt100.net/docs/vt510-rm/contents.html
	//   https://www.xfree86.org/current/ctlseqs.html
	//   https://github.com/xtermjs/xterm.js/
	//   https://docs.microsoft.com/en-us/windows/console/console-virtual-terminal-sequences
	class XTerm
	{
		// CSI escape codes.
		// Category:  Character/Font attributes.

		// Reset all character/font attributes.
		public static function ResetAttributes()
		{
			echo "\x1B[0m";
		}

		public static function SetBold($on = true)
		{
			echo ($on ? "\x1B[1m" : "\x1B[22m");
		}

		// Not widely supported.
		public static function SetFaint($on = true)
		{
			// NOTE:  The same escape code clears both the bold and faint attributes.
			echo ($on ? "\x1B[2m" : "\x1B[22m");
		}

		// Not widely supported.
		public static function SetItalic($on = true)
		{
			echo ($on ? "\x1B[3m" : "\x1B[23m");
		}

		public static function SetUnderline($on = true)
		{
			echo ($on ? "\x1B[4m" : "\x1B[24m");
		}

		// Why would you ever enable the blinking text attribute?
		public static function SetBlink($on = true)
		{
			echo ($on ? "\x1B[5m" : "\x1B[25m");
		}

		// "\x1B[6m" is "fast blink" only ever used by MS-DOS.  Unsupported by everything else.

		// Inverts foreground and background colors.
		public static function SetInverse($on = true)
		{
			echo ($on ? "\x1B[7m" : "\x1B[27m");
		}

		public static function SetConceal($on = true)
		{
			echo ($on ? "\x1B[8m" : "\x1B[28m");
		}

		// Not widely supported.
		public static function SetStrikethrough($on = true)
		{
			echo ($on ? "\x1B[9m" : "\x1B[29m");
		}

		// There is no "\x1B[26m" escape code.

		// 0 = Default font, 1-9 = Alternate font (sometimes supported), 10 = Fraktur (hardly ever supported)
		public static function SetFontNum($num = 0)
		{
			// Escape codes "\x1B[10m" through "\x1B[20m".
			$num = (int)$num;
			if ($num < 0 || $num > 10)  $num = 0;

			echo "\x1B[" . (10 + $num) . "m";
		}

		// "\x1B[21m" is "bold off OR double underline".  Bold off is not widely supported.  There are other ways to clear bold.  Double underline is rarely supported.

		// Returns the static color palette for colors 16 through 255 (0 through 15 are mostly unreliable across hosts).
		public static function GetDefaultColorPalette()
		{
			$vals = array(0x00, 0x5F, 0x87, 0xAF, 0xD7, 0xFF);
			$result = array();
			$y = 16;
			for ($x = 0; $x < 216; $x++)
			{
				$r = $vals[($x / 36) % 6];
				$g = $vals[($x / 6) % 6];
				$b = $vals[$x % 6];

				$result[$y] = array($r, $g, $b);
				$y++;
			}

			for ($x = 0; $x < 24; $x++)
			{
				$c = $x * 10 + 8;

				$result[$y] = array($c, $c, $c);
				$y++;
			}

			return $result;
		}

		// Maps a color palette using a set of index values.
		public static function MapOptimizedColorPalette($palettemap, $palette)
		{
			$result = array();
			foreach ($palettemap as $x => $x2)
			{
				if ($x2 === false)  continue;

				$result[$x] = $palette[$x2];
			}

			return $result;
		}

		// Returns a color palette that's been optimized for readable text on black backgrounds.
		public static function GetBlackOptimizedColorPalette()
		{
			$palettemap = array(
				false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false,
				145, 61, 61, 62, 62, 62, 71, 73, 67, 74, 68, 81, 71, 72, 73, 74,
				81, 81, 77, 79, 79, 80, 81, 81, 83, 85, 86, 86, 87, 81, 83, 84,
				85, 86, 86, 87, 131, 133, 97, 134, 98, 171, 143, 145, 103, 61, 62, 62,
				107, 108, 109, 67, 68, 68, 149, 71, 72, 73, 74, 81, 119, 77, 78, 79,
				80, 81, 119, 83, 84, 85, 86, 87, 131, 132, 133, 134, 171, 171, 137, 138,
				139, 97, 98, 98, 143, 144, 145, 103, 104, 105, 149, 107, 108, 109, 110, 111,
				149, 113, 114, 115, 116, 117, 119, 119, 120, 121, 122, 123, 167, 169, 169, 170,
				171, 171, 179, 131, 132, 133, 134, 171, 179, 137, 138, 139, 140, 141, 185, 143,
				144, 145, 146, 147, 149, 149, 150, 151, 152, 153, 119, 119, 156, 157, 158, 159,
				203, 205, 206, 206, 207, 171, 209, 167, 168, 169, 170, 171, 179, 173, 174, 175,
				176, 177, 179, 179, 180, 181, 182, 183, 185, 185, 186, 187, 188, 189, 185, 149,
				192, 193, 194, 195, 203, 204, 205, 206, 206, 207, 209, 203, 204, 205, 206, 207,
				209, 209, 210, 211, 212, 213, 209, 209, 216, 217, 218, 219, 185, 179, 222, 223,
				224, 225, 185, 185, 228, 229, 230, 231, 145, 145, 145, 145, 145, 145, 145, 145,
				145, 145, 145, 145, 145, 145, 145, 145, 145, 249, 250, 251, 252, 253, 254, 255,
			);

			return $palettemap;
		}

		// Returns a color palette that's been optimized for readable text on white backgrounds.
		public static function GetWhiteOptimizedColorPalette()
		{
			$palettemap = array(
				false, false, false, false, false, false, false, false, false, false, false, false, false, false, false, false,
				16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31,
				32, 33, 28, 29, 29, 30, 32, 33, 28, 29, 29, 29, 31, 32, 28, 29,
				29, 29, 32, 32, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63,
				64, 65, 65, 61, 68, 69, 64, 65, 65, 68, 68, 69, 64, 65, 65, 65,
				68, 68, 64, 65, 65, 29, 68, 68, 88, 89, 90, 91, 92, 93, 94, 95,
				96, 97, 98, 99, 100, 95, 243, 60, 61, 62, 64, 65, 65, 65, 68, 68,
				64, 65, 65, 65, 68, 68, 64, 65, 65, 65, 65, 68, 124, 125, 126, 127,
				128, 129, 130, 131, 132, 133, 134, 135, 136, 131, 95, 96, 97, 98, 100, 131,
				95, 243, 60, 61, 64, 65, 65, 65, 68, 68, 64, 64, 65, 65, 65, 68,
				160, 161, 162, 163, 164, 165, 166, 167, 168, 169, 133, 135, 166, 167, 131, 132,
				133, 134, 136, 167, 131, 95, 96, 97, 136, 136, 131, 95, 243, 60, 136, 136,
				65, 65, 65, 68, 196, 197, 198, 199, 200, 200, 166, 167, 168, 169, 169, 169,
				166, 167, 167, 168, 169, 133, 166, 167, 167, 131, 132, 133, 136, 167, 167, 131,
				95, 96, 136, 136, 167, 131, 95, 243, 232, 233, 234, 235, 236, 237, 238, 239,
				240, 241, 242, 243, 243, 243, 243, 243, 243, 243, 243, 243, 243, 243, 243, 243,
			);

			return $palettemap;
		}

		// Returns the input RGB value as a string.
		public static function ConvertRGBToString($r, $g, $b, $prefix = "")
		{
			return $prefix . sprintf("%02X%02X%02X", $r, $g, $b);
		}

		public static function SetForegroundColor($fgcolor)
		{
			// "\x1B[30m" through "\x1B[37m" has been superceded by 38m.
			if (is_int($fgcolor))  echo "\x1B[38;5;" . ($fgcolor >= 0 && $fgcolor <= 255 ? $fgcolor : 7) . "m";
			else if (is_string($fgcolor))
			{
				$fgcolor = preg_replace('/[^0-9a-f]/', "", strtolower($fgcolor));
				if (strlen($fgcolor) == 3)  $fgcolor = $fgcolor{0} . $fgcolor{0} . $fgcolor{1} . $fgcolor{1} . $fgcolor{2} . $fgcolor{2};

				if (strlen($fgcolor) >= 6)  echo "\x1B[38;2;" . hexdec(substr($fgcolor, 0, 2)) . ";" . hexdec(substr($fgcolor, 2, 2)) . ";" . hexdec(substr($fgcolor, 4, 2)) . "m";
			}
			else
			{
				echo "\x1B[39m";
			}
		}

		public static function SetBackgroundColor($bgcolor)
		{
			// "\x1B[40m" through "\x1B[47m" has been superceded by 48m.
			if (is_int($bgcolor))  echo "\x1B[48;5;" . ($bgcolor >= 0 && $bgcolor <= 255 ? $bgcolor : 7) . "m";
			else if (is_string($bgcolor))
			{
				$bgcolor = preg_replace('/[^0-9a-f]/', "", strtolower($bgcolor));
				if (strlen($bgcolor) == 3)  $bgcolor = $bgcolor{0} . $bgcolor{0} . $bgcolor{1} . $bgcolor{1} . $bgcolor{2} . $bgcolor{2};

				if (strlen($bgcolor) >= 6)  echo "\x1B[48;2;" . hexdec(substr($bgcolor, 0, 2)) . ";" . hexdec(substr($bgcolor, 2, 2)) . ";" . hexdec(substr($bgcolor, 4, 2)) . "m";
			}
			else
			{
				echo "\x1B[49m";
			}
		}

		public static function SetColors($fgcolor, $bgcolor)
		{
			self::SetForegroundColor($fgcolor);
			self::SetBackgroundColor($bgcolor);
		}

		// Not widely supported.
		public static function SetFramed($on = true)
		{
			echo ($on ? "\x1B[51m" : "\x1B[54m");
		}

		// Not widely supported.
		public static function SetEncircled($on = true)
		{
			// NOTE:  The same escape code clears both the framed and encircled attributes.
			echo ($on ? "\x1B[52m" : "\x1B[54m");
		}

		// Not widely supported.
		public static function SetOverlined($on = true)
		{
			echo ($on ? "\x1B[53m" : "\x1B[55m");
		}

		// "\x1B[60m" through "\x1B[107m" are either not widely supported or are handled by other escape codes.


		// Category:  Cursor movement and display manipulation.
		// Unless otherwise specified, these functions don't generally affect scrollback.

		public static function InsertBlankChars($num)
		{
			echo "\x1B[" . ($num > 1 ? (int)$num : "") . "@";
		}

		public static function MoveCursorUp($num = 1)
		{
			echo "\x1B[" . ($num > 1 ? (int)$num : "") . "A";
		}

		public static function MoveCursorDown($num = 1)
		{
			echo "\x1B[" . ($num > 1 ? (int)$num : "") . "B";
		}

		// Moves the cursor forward in characters or tab stops.
		public static function MoveCursorForward($num = 1, $tabstops = false)
		{
			if ($tabstops)  echo "\x1B[" . ($num > 1 ? (int)$num : "") . "I";
			else  echo "\x1B[" . ($num > 1 ? (int)$num : "") . "C";
		}

		// Moves the cursor backward in characters or tab stops.
		public static function MoveCursorBack($num = 1, $tabstops = false)
		{
			if ($tabstops)  echo "\x1B[" . ($num > 1 ? (int)$num : "") . "Z";
			else  echo "\x1B[" . ($num > 1 ? (int)$num : "") . "D";
		}

		// Equivalent to moving the cursor down and to the beginning of the next line.
		public static function MoveCursorNextLines($num = 1)
		{
			echo "\x1B[" . ($num > 1 ? (int)$num : "") . "E";
		}

		// Equivalent to moving the cursor up and to the beginning of the previous line.
		public static function MoveCursorPrevLines($num = 1)
		{
			echo "\x1B[" . ($num > 1 ? (int)$num : "") . "F";
		}

		// Sets the cursor's current row character position.
		public static function SetCursorCharacterAbsolute($pos = 1, $capped = true)
		{
			echo "\x1B[" . ($num > 1 ? (int)$num : "") . ($capped ? "`" : "G");
		}

		// Move the cursor to the exact position on screen.
		public static function SetCursorPosition($col = 1, $row = 1)
		{
			echo "\x1B[" . ($row > 1 ? (int)$row : "") . ";" . ($col > 1 ? (int)$col : "") . "H";
		}

		// Clears all displayed characters from the start of the cursor to the end of the display.
		public static function EraseToDisplayEnd()
		{
			echo "\x1B[0J";
		}

		// Clears all displayed characters from the start of the display through the cursor.
		public static function EraseFromDisplayStart()
		{
			echo "\x1B[1J";
		}

		// Clears the entire display.
		public static function EraseDisplay()
		{
			echo "\x1B[2J";
		}

		// Clear all scrollback but leave the current display alone.  An XTerm only feature.
		public static function ClearScrollback()
		{
			echo "\x1B[3J";
		}

		// Combines a few of the above functions to erase the display, set the cursor position, and optionally clear scrollback.
		public static function ResetDisplay($scrollback = false)
		{
			if ($scrollback)  self::ClearScrollback();

			self::SetCursorPosition();
			self::EraseDisplay();
		}

		// Clears all displayed characters from the start of the cursor to the end of the line.
		public static function EraseToLineEnd()
		{
			echo "\x1B[0K";
		}

		// Clears all displayed characters from the start of the line through the cursor.
		public static function EraseFromLineStart()
		{
			echo "\x1B[1K";
		}

		// Clears the entire line.
		public static function EraseLine()
		{
			echo "\x1B[2K";
		}

		// Inserts one or more blank lines at the cursor position.  Lines below are moved down.  Lines that fall off the page are lost.
		public static function InsertLines($num = 1)
		{
			echo "\x1B[" . ($num > 1 ? (int)$num : "") . "L";
		}

		// Deletes one or more lines at the cursor position.  Lines below are moved up.  New blank lines with no attributes are placed at the bottom.
		public static function DeleteLines($num = 1)
		{
			echo "\x1B[" . ($num > 1 ? (int)$num : "") . "M";
		}

		// Deletes one or more characters at the cursor position.  Characters and attributes move left.  New blank characters with no attributes are placed at the end.
		public static function DeleteChars($num = 1)
		{
			echo "\x1B[" . ($num > 1 ? (int)$num : "") . "P";
		}

		// The number of lines to scroll the display up.  New blank lines with no attributes are placed at the bottom.
		public static function ScrollDisplayUp($num = 1)
		{
			echo "\x1B[" . ($num > 1 ? (int)$num : "") . "S";
		}

		// The number of lines to scroll the display down.  New blank lines with no attributes are placed at the top.
		public static function ScrollDisplayDown($num = 1)
		{
			echo "\x1B[" . ($num > 1 ? (int)$num : "") . "T";
		}

		// Clears one or more characters and their attributes starting at the cursor position.
		public static function EraseChars($num = 1)
		{
			echo "\x1B[" . ($num > 1 ? (int)$num : "") . "X";
		}

		// "\x1B[a" (Horizontal Position Relative) appears to be identical to "\x1B[C".  The code is identical in XTermJS.

		// Copies the preceding character one or more times starting at the cursor position.
		public static function RepeatPrecedingCharacter($num = 1)
		{
			echo "\x1B[" . ($num > 1 ? (int)$num : "") . "b";
		}

		// Sets the cursor's row position.
		public static function SetCursorLineAbsolute($pos = 1)
		{
			echo "\x1B[" . ($num > 1 ? (int)$num : "") . "d";
		}

		// "\x1B[e" (Vertical Position Relative) appears to be identical to "\x1B[B".  The code is identical in XTermJS.
		// "\x1B[f" (Horizontal and Vertical Position) appears to be identical to "\x1B[H".  The code appears to be logically identical in XTermJS.

		// Permanently deletes a tab stop at the cursor position.  The cursor must be at the tab stop to delete it.
		public static function DeleteTabStop()
		{
			echo "\x1B[g";
		}

		// Permanently deletes all tab stops.
		public static function DeleteAllTabStops()
		{
			echo "\x1B[3g";
		}

		// Sets a tab stop at the current cursor position.
		public static function SetTabStop()
		{
			echo "\x1BH";
		}

		// Sets the cursor style and whether or not it blinks.
		// The type is one of 'block', 'underline', 'bar'.  The 'bar' type is xterm only.
		public static function SetCursorStyle($type, $blink)
		{
			$type = strtolower($type);

			if ($type === 'underline')  echo "\x1B[" . ($blink ? 3 : 4) . " q";
			else if ($type === 'bar')  echo "\x1B[" . ($blink ? 5 : 6) . " q";
			else  echo "\x1B[" . ($blink ? 1 : 2) . " q";
		}

		// Sets the vertical scrolling region.
		public static function SetScrollRegion($top = 1, $bottom = false)
		{
			echo "\x1B[" . ($top < 1 ? 1 : (int)$top) . ($bottom === false || $bottom < 1 ? "" : ";" . (int)$bottom) . " q";
		}

		// Saves the cursor position.
		public static function SaveCursor()
		{
			echo "\x1B[s";
		}

		// Restore the cursor position.
		public static function RestoreCursor()
		{
			echo "\x1B[u";
		}

		// The default terminal behavior is to overwrite, not insert.
		public static function SetInsertMode($on = true)
		{
			echo ($on ? "\x1B[4h" : "\x1B[4l");
		}

		// Changes how arrow keys are handled.
		public static function SetApplicationCursorMode($on = true)
		{
			echo ($on ? "\x1B[?1h" : "\x1B[?1l");
		}

		// Restrict the cursor to the upper-left corner of the margins.  Off by default.
		public static function SetOriginMode($on = true)
		{
			echo ($on ? "\x1B[?6h" : "\x1B[?6l");
		}

		// Wrap text if the cursor is at the edge of the screen.  When turned off, text cuts off at the edge of the screen.  On by default.
		public static function SetWraparoundMode($on = true)
		{
			echo ($on ? "\x1B[?7h" : "\x1B[?7l");
		}

		// Shows the cursor.
		public static function ShowCursor()
		{
			echo "\x1B[?25h";
		}

		// Hides the cursor.
		public static function HideCursor()
		{
			echo "\x1B[?25l";
		}

		// Switches to the alternate screen buffer (e.g. vim).  XTerm only.
		public static function SetAltScreenBuffer($on = true)
		{
			echo ($on ? "\x1B[?47h" : "\x1B[?47l");
		}


		// Category:  Special device modes and status updates/information.
		// Many of the items in this category either send responses immediately or imply future data.  The caller must be ready to process incoming escape codes.

		// Request primary device attributes.  Up to the caller to get/handle the response.
		public static function SendPrimaryDeviceAttributes()
		{
			echo "\x1B[c";
		}

		// Request secondary device attributes.  Up to the caller to get/handle the response.
		public static function SendSecondaryDeviceAttributes()
		{
			echo "\x1B[>c";
		}

		// Request mouse events (button presses + movement).  XTerm only.
		public static function SetMouseEventsMode($on = true)
		{
			echo ($on ? "\x1B[?1003h" : "\x1B[?1003l");
		}

		// Request mouse focus events.  XTerm only.
		public static function SetFocusEventsMode($on = true)
		{
			echo ($on ? "\x1B[?1004h" : "\x1B[?1004l");
		}

		// Request UTF mouse mode.
		public static function SetUTFMouseMode($on = true)
		{
			echo ($on ? "\x1B[?1005h" : "\x1B[?1005l");
		}

		// Request SGR mouse mode.
		public static function SetSGRMouseMode($on = true)
		{
			echo ($on ? "\x1B[?1006h" : "\x1B[?1006l");
		}

		// Request URXVT mouse mode.
		public static function SetURXVTMouseMode($on = true)
		{
			echo ($on ? "\x1B[?1015h" : "\x1B[?1015l");
		}

		// Surrounds pasted text with "\x1B[200~," and "\x1B[200~.".  Default is off.
		public static function SetBracketedPasteMode($on = true)
		{
			echo ($on ? "\x1B[?2004h" : "\x1B[?2004l");
		}

		// Request the current cursor position.
		public static function RequestCursorPosition()
		{
			echo "\x1B[6n";
		}

		// Performs a full or soft reset of the terminal.
		public static function ResetTerminal($full)
		{
			echo ($full ? "\x1Bc" : "\x1B[!p");
		}

		// Rings the bell (usually a ding or thunk sound).
		public static function Bell()
		{
			echo "\x07";
		}


		// Category:  User interface.

		// Moves the cursor down but scrolls if the cursor is at the scroll region.
		public static function MoveForwardIndex()
		{
			echo "\x1BD";
		}

		// Equivalent to calling SetCursorCharacterAbsolute(1) and MoveForwardIndex().
		public static function MoveToNextLine()
		{
			echo "\x1BE";
		}

		// Moves the cursor up but scrolls if the cursor is at the scroll region.
		public static function MoveReverseIndex()
		{
			echo "\x1BM";
		}

		// Equivalent to turning NumLock mode off.  Default is numeric keypad mode.
		public static function SetApplicationKeypadMode($on = true)
		{
			echo ($on ? "\x1B=" : "\x1B>");
		}


		// OSC escape codes.

		public static function SetTitle($title)
		{
			echo "\x1B]0;" . str_replace("\x07", "", $title) . "\x07";
		}

		// This only works with the Run Process SDK.
		// The mode can be one of 'interactive', 'interactive_echo', 'readline', 'readline_secure', or 'none'.
		public static function SetCustomInputMode($mode)
		{
			echo "\x1B]1000;" . str_replace("\x07", "", $mode) . "\x07";
		}
	}
?>