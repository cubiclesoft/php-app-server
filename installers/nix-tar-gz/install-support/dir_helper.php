<?php
	// Directory helper functions.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	class DirHelper
	{
		static function Delete($path, $recursive = true, $exclude = array())
		{
			$path = rtrim(str_replace("\\", "/", $path), "/");

			$dir = @opendir($path);
			if ($dir !== false)
			{
				while (($file = readdir($dir)) !== false)
				{
					if ($file != "." && $file != ".." && !isset($exclude[$path . "/" . $file]))
					{
						if (is_file($path . "/" . $file) || is_link($path . "/" . $file))  @unlink($path . "/" . $file);
						else if ($recursive && is_dir($path . "/" . $file))  self::Delete($path . "/" . $file, true, $exclude);
					}
				}

				closedir($dir);

				@rmdir($path);
			}
		}

		static function Copy($srcdir, $destdir, $recurse = true, $exclude = array())
		{
			@mkdir($destdir, 0777, true);

			$dir = @opendir($srcdir);
			if ($dir)
			{
				while (($file = readdir($dir)) !== false)
				{
					if ($file != "." && $file != ".." && !isset($exclude[$srcdir . "/" . $file]))
					{
						if (is_dir($srcdir . "/" . $file))
						{
							if ($recurse)  self::Copy($srcdir . "/" . $file, $destdir . "/" . $file, true, true);
						}
						else
						{
							$fp = @fopen($srcdir . "/" . $file, "rb");
							$fp2 = @fopen($destdir . "/" . $file, "wb");

							if ($fp !== false && $fp2 !== false)
							{
								while (($data = fread($fp, 1048576)) !== false  && $data !== "")  fwrite($fp2, $data);
							}

							if ($fp2 !== false)  fclose($fp2);
							if ($fp !== false)  fclose($fp);
						}
					}
				}

				closedir($dir);
			}
		}

		static function SetPermissions($path, $dirowner, $dirgroup, $dirperms, $fileowner, $filegroup, $fileperms, $recurse = true, $exclude = array())
		{
			$path = rtrim(str_replace("\\", "/", $path), "/");

			if (!isset($exclude[$path]))
			{
				if ($dirowner !== false)  @chown($path, $dirowner);
				if ($dirgroup !== false)  @chgrp($path, $dirgroup);
				if ($dirperms !== false)  @chmod($path, $dirperms);
			}

			$dir = @opendir($path);
			if ($dir)
			{
				while (($file = readdir($dir)) !== false)
				{
					if ($file != "." && $file != ".." && !isset($exclude[$path . "/" . $file]))
					{
						if (is_dir($path . "/" . $file))
						{
							if ($recurse)  self::SetPermissions($path . "/" . $file, $dirowner, $dirgroup, $dirperms, $fileowner, $filegroup, $fileperms, true, $exclude);
						}
						else
						{
							if ($fileowner !== false)  @chown($path . "/" . $file, $fileowner);
							if ($filegroup !== false)  @chgrp($path . "/" . $file, $filegroup);
							if ($fileperms !== false)  @chmod($path . "/" . $file, $fileperms);
						}
					}
				}

				@closedir($dir);
			}
		}
	}
?>