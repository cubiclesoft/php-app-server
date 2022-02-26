<?php
	// Directory helper functions.
	// (C) 2022 CubicleSoft.  All Rights Reserved.

	class DirHelper
	{
		public static function Delete($path, $recursive = true, $exclude = array())
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

		public static function Copy($srcdir, $destdir, $recurse = true, $exclude = array())
		{
			$srcdir = rtrim(str_replace("\\", "/", $srcdir), "/");
			$destdir = rtrim(str_replace("\\", "/", $destdir), "/");

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
							if ($recurse)  self::Copy($srcdir . "/" . $file, $destdir . "/" . $file, true, $exclude);
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

		public static function SetPermissions($path, $dirowner, $dirgroup, $dirperms, $fileowner, $filegroup, $fileperms, $recurse = true, $exclude = array())
		{
			$path = rtrim(str_replace("\\", "/", $path), "/");

			if (is_string($dirowner))
			{
				$dirowner = (function_exists("posix_getpwnam") ? @posix_getpwnam($dirowner) : false);
				if ($dirowner !== false)  $dirowner = $dirowner["uid"];
			}

			if (is_string($dirgroup))
			{
				$dirgroup = (function_exists("posix_getgrnam") ? @posix_getgrnam($dirgroup) : false);
				if ($dirgroup !== false)  $dirgroup = $dirgroup["gid"];
			}

			if (is_string($fileowner))
			{
				$fileowner = (function_exists("posix_getpwnam") ? @posix_getpwnam($fileowner) : false);
				if ($fileowner !== false)  $fileowner = $fileowner["uid"];
			}

			if (is_string($filegroup))
			{
				$filegroup = (function_exists("posix_getgrnam") ? @posix_getgrnam($filegroup) : false);
				if ($filegroup !== false)  $filegroup = $filegroup["gid"];
			}

			if (!isset($exclude[$path]))
			{
				if ($dirowner !== false && @fileowner($path) !== $dirownwer)  @chown($path, $dirowner);
				if ($dirgroup !== false && @filegroup($path) !== $dirgroup)  @chgrp($path, $dirgroup);
				if ($dirperms !== false && @fileperms($path) & 07777 !== $dirperms)  @chmod($path, $dirperms);
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
							if ($fileowner !== false && @fileowner($path . "/" . $file) !== $fileowner)  @chown($path . "/" . $file, $fileowner);
							if ($filegroup !== false && @filegroup($path . "/" . $file) !== $filegroup)  @chgrp($path . "/" . $file, $filegroup);
							if ($fileperms !== false && @fileperms($path . "/" . $file) & 07777 !== $fileperms)  @chmod($path . "/" . $file, $fileperms);
						}
					}
				}

				@closedir($dir);
			}
		}
	}
?>