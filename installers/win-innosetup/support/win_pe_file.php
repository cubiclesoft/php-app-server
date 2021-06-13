<?php
	// Windows Portable Executable (PE) file reader/writer class for PHP.
	// (C) 2019 CubicleSoft.  All Rights Reserved.

	// Very large executable files will run the parser out of RAM.
	@ini_set("memory_limit", "-1");

	class WinPEFile
	{
		// A MS-DOS stub that outputs "This program cannot be run in DOS mode."
		// Rich header removed (https://www.ntcore.com/files/richsign.htm).
		public static $defaultDOSstub = "\x0E\x1F\xBA\x0E\x00\xB4\x09\xCD\x21\xB8\x01\x4C\xCD\x21"
			. "This program cannot be run in DOS mode.\x0D\x0D\x0A\x24\x00\x00\x00\x00\x00\x00\x00"
			. "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"
			. "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"
			. "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"
			. "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"
			. "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00"
			. "\x00\x00\x00\x00\x00\x00\x00\x00";

		// A bunch of these values are VERY ancient.
		const IMAGE_FILE_MACHINE_UNKNOWN = 0x0000;  // Not machine-specific
		const IMAGE_FILE_MACHINE_I386 = 0x014C;  // Intel 386 or later
		const IMAGE_FILE_MACHINE_AMD64 = 0x8664;  // x64
		const IMAGE_FILE_MACHINE_DOT_NET_CLR = 0xC0EE;  // .NET CLR, pure MSIL

		const IMAGE_FILE_MACHINE_R3000_BE = 0x0160;  // MIPS R3000, big endian
		const IMAGE_FILE_MACHINE_R3000_LE = 0x0162;  // MIPS R3000, little endian
		const IMAGE_FILE_MACHINE_R4000 = 0x0166;  // MIPS R4000, little endian
		const IMAGE_FILE_MACHINE_R10000 = 0x0168;  // MIPS R10000, little endian
		const IMAGE_FILE_MACHINE_WCEMIPSV2 = 0x0169;  // MIPS WCE v2, little endian
		const IMAGE_FILE_MACHINE_OLD_DECALPHAAXP = 0x0183;  // Old DEC Alpha AXP
		const IMAGE_FILE_MACHINE_DECALPHAAXP = 0x0184;  // DEC Alpha AXP
		const IMAGE_FILE_MACHINE_SH3 = 0x01A2;  // Hitachi SH3
		const IMAGE_FILE_MACHINE_SH3DSP = 0x01A3;  // Hitachi SH3 DSP
		const IMAGE_FILE_MACHINE_SH3E = 0x01A4;  // Hitachi SH3E
		const IMAGE_FILE_MACHINE_SH4 = 0x01A6;  // Hitachi SH4
		const IMAGE_FILE_MACHINE_SH5 = 0x01A8;  // Hitachi SH5
		const IMAGE_FILE_MACHINE_ARM = 0x01C0;  // ARM, little endian
		const IMAGE_FILE_MACHINE_THUMB = 0x01C2;  // ARM Thumb
		const IMAGE_FILE_MACHINE_ARMNT = 0x01C4;  // ARM Thumb-2, little endian
		const IMAGE_FILE_MACHINE_AM33 = 0x01D3;  // Matsushita AM33
		const IMAGE_FILE_MACHINE_POWERPC = 0x01F0;  // IBM PowerPC, little endian
		const IMAGE_FILE_MACHINE_POWERPCFP = 0x01F1;  // IBM PowerPC with floating point
		const IMAGE_FILE_MACHINE_IA64 = 0x0200;  // Intel Itanium
		const IMAGE_FILE_MACHINE_MIPS16 = 0x0266;  // MIPS16
		const IMAGE_FILE_MACHINE_MOTOROLA68000 = 0x0268;  // Motorola 68000 series
		const IMAGE_FILE_MACHINE_DECALPHAAXP64 = 0x0284;  // DEC Alpha AXP 64
		const IMAGE_FILE_MACHINE_MIPSFPU = 0x0366;  // MIPS with FPU
		const IMAGE_FILE_MACHINE_MIPSFPU16 = 0x0466;  // MIPS16 with FPU
		const IMAGE_FILE_MACHINE_INFINEONTRICORE = 0x0520;  // Infineon TriCore
		const IMAGE_FILE_MACHINE_CEF = 0x0CEF;  // CEF
		const IMAGE_FILE_MACHINE_EBC = 0x0EBC;  // EFI byte code
		const IMAGE_FILE_MACHINE_RISCV32 = 0x5032;  // RISC-V 32-bit address space
		const IMAGE_FILE_MACHINE_RISCV64 = 0x5064;  // RISC-V 64-bit address space
		const IMAGE_FILE_MACHINE_RISCV128 = 0x5128;  // RISC-V 128-bit address space
		const IMAGE_FILE_MACHINE_M32R = 0x9041;  // Mitsubishi M32R, little endian
		const IMAGE_FILE_MACHINE_ARM64 = 0xAA64;  // ARM64, little endian

		public static $machine_types = array(
			0x0000 => "Unknown",  // For "resource only" DLLs with icons and such.  No executable code section.
			0x014C => "Intel 386 or later",  // 32-bit Intel/AMD.
			0x8664 => "x64",  // Most Intel/AMD 64-bit processors.
			0xC0EE => ".NET CLR, pure MSIL",  // Ugh.  Gross.  Someone at Microsoft legitimately thought, "You know what we should do for .NET?  Register it as a microprocessor just like we do for real hardware!"

			// The rest of these aren't seen as often.
			0x0160 => "MIPS R3000, big endian",
			0x0162 => "MIPS R3000, little endian",
			0x0166 => "MIPS R4000, little endian",
			0x0168 => "MIPS R10000, little endian",  // It's over 9,000!
			0x0169 => "MIPS WCE v2, little endian",
			0x0183 => "Old DEC Alpha AXP",
			0x0184 => "DEC Alpha AXP",
			0x01A2 => "Hitachi SH3",
			0x01A3 => "Hitachi SH3 DSP",
			0x01A4 => "Hitachi SH3E, little endian",
			0x01A6 => "Hitachi SH4, little endian",
			0x01A8 => "Hitachi SH5",
			0x01C0 => "ARM, little endian",  // Probably not the newer "Windows desktop on ARM" effort.
			0x01C2 => "ARM Thumb",  // See above comment.
			0x01C4 => "ARM Thumb-2, little endian; ARMv7; Acorn ARM2",  // Uh, hello 1986.  Somehow that's not a typo.
			0x01D3 => "Matsushita AM33",  // Uh, hello 1988.
			0x01F0 => "IBM PowerPC, little endian",  // Yeah, it's not a Mac.
			0x01F1 => "IBM PowerPC with floating point",  // Still not a Mac.
			0x0200 => "Intel Itanium",  // Intel's first attempt at 64-bit that no one wanted.  It didn't help that 64-bit Windows was ultra-buggy at first.
			0x0266 => "MIPS16",
			0x0268 => "Motorola 68000 series",
			0x0284 => "DEC Alpha AXP 64",  // Not sure what the difference between this and 0x0184 is.
			0x0366 => "MIPS with FPU",
			0x0466 => "MIPS16 with FPU",
			0x0520 => "Infineon TriCore",  // Great.  Now your car REALLY does run on Windows and can BSOD with "Your car has crashed due to unknown error 0xDEADBEEF" and then proceed to drive into an actual cow.  This is possibly related to Microsoft's attempt at implementating Apple CarPlay but with the Windows OS instead.
			0x0CEF => "CEF",  // ???
			0x0EBC => "EFI byte code",  // Intended for a unified device driver architecture.
			0x5032 => "RISC-V 32-bit",
			0x5064 => "RISC-V 64-bit",
			0x5128 => "RISC-V 128-bit",  // For when you need to future-proof everything, engineer a chip that no one asked for!
			0x9041 => "Mitsubishi M32R, little endian",  // Uh, hello 1997.
			0xAA64 => "ARM64, little endian",
		);

		// Image characteristics.
		const IMAGE_FILE_RELOCS_STRIPPED = 0x0001;  // Image only.  This indicates that the file does not contain base relocations and must therefore be loaded at its preferred base address.
		const IMAGE_FILE_EXECUTABLE_IMAGE = 0x0002;  // Image only.  This indicates that the image file is valid and can be run.
		const IMAGE_FILE_LINE_NUMS_STRIPPED = 0x0004;  // COFF line numbers have been removed.  Deprecated.
		const IMAGE_FILE_LOCAL_SYMS_STRIPPED = 0x0008;  // COFF symbol table entries for local symbols have been removed.  Deprecated.
		const IMAGE_FILE_AGGRESSIVE_WS_TRIM = 0x0010;  // Obsolete.  Aggressively trim working set.  Deprecated.
		const IMAGE_FILE_LARGE_ADDRESS_AWARE = 0x0020;  // Application can handle > 2 GB addresses.
		// 0x0040 is reserved for future use.
		const IMAGE_FILE_BYTES_REVERSED_LO = 0x0080;  // Little endian:  The least significant bit (LSB) precedes the most significant bit (MSB) in memory.  Deprecated.
		const IMAGE_FILE_32BIT_MACHINE = 0x0100;  // Machine is based on a 32-bit word architecture.
		const IMAGE_FILE_DEBUG_STRIPPED = 0x0200;  // Debugging information is removed from the image file.
		const IMAGE_FILE_REMOVABLE_RUN_FROM_SWAP = 0x0400;  // If the image is on removable media, fully load it and copy it to the swap file.
		const IMAGE_FILE_NET_RUN_FROM_SWAP = 0x0800;  // If the image is on network media, fully load it and copy it to the swap file.
		const IMAGE_FILE_SYSTEM = 0x1000;  // The image file is a system file, not a user program.
		const IMAGE_FILE_DLL = 0x2000;  // The image file is a dynamic-link library (DLL).
		const IMAGE_FILE_UP_SYSTEM_ONLY = 0x4000;  // Run only on a uniprocessor machine.
		const IMAGE_FILE_BYTES_REVERSED_HI = 0x8000;  // Big endian:  The MSB precedes the LSB in memory.  Deprecated.

		// Optional header signature.
		const OPT_HEADER_SIGNATURE_PE32 = 0x010B;
		const OPT_HEADER_SIGNATURE_PE32_PLUS = 0x020B;
		const OPT_HEADER_SIGNATURE_ROM_IMAGE = 0x0107;

		public static $opt_header_signatures = array(
			0x010B => "PE32",  // 32-bit address space.
			0x020B => "PE32+",  // 64-bit address space.
			0x0107 => "ROM image",
		);

		// Default image_base values.
		const IMAGE_BASE_DLL_DEFAULT = 0x10000000;
		const IMAGE_BASE_EXE_DEFAULT = 0x00400000;
		const IMAGE_BASE_CE_EXE_DEFAULT = 0x00100000;

		// Image subsystems (startup).  Values 2 and 3 are the most common.
		public static $image_subsystems = array(
			0 => "Unknown",
			1 => "Native",  // No subsystem required (device drivers and native system processes).
			2 => "Windows GUI",  // Windows graphical user interface (GUI) subsystem (WinMain).
			3 => "Windows Console",  // Windows character-mode user interface (console) subsystem (CLI).
			5 => "OS/2 Console",  // OS/2 console subsystem (CLI).
			7 => "POSIX Console",  // POSIX console subsystem (CLI).
			9 => "Windows CE GUI",  // Windows CE subsystem (GUI).
			10 => "EFI Application",  // Extensible Firmware Interface (EFI) application.
			11 => "EFI Boot Service Driver",  // An EFI driver with boot services.
			12 => "EFI Run-time Driver",  // An EFI driver with run-time services.
			13 => "EFI ROM Image",  // An EFI ROM image.
			14 => "XBox",  // Xbox system.
			16 => "Boot Application",  // Boot application.
		);

		// DLL characteristics.
		// 0x0001 is reserved.
		// 0x0002 is reserved.
		// 0x0004 is reserved.
		// 0x0008 is reserved.
		// 0x0010 ???
		// 0x0020 ???
		const IMAGE_DLL_CHARACTERISTICS_DYNAMIC_BASE = 0x0040;  // Can be relocated at load time.
		const IMAGE_DLL_CHARACTERISTICS_FORCE_INTEGRITY = 0x0080;  // Code Integrity checks are enforced.
		const IMAGE_DLL_CHARACTERISTICS_NX_COMPAT = 0x0100;  // Compatible with data execution prevention (DEP).
		const IMAGE_DLL_CHARACTERISTICS_NO_ISOLATION = 0x0200;  // Isolation aware, but do not isolate the image.
		const IMAGE_DLL_CHARACTERISTICS_NO_SEH = 0x0400;  // Does not use structured exception handling (SEH).  No handlers can be called in this image.
		const IMAGE_DLL_CHARACTERISTICS_NO_BIND = 0x0800;  // Do not bind the image.
		// 0x1000 is reserved.
		const IMAGE_DLL_CHARACTERISTICS_WDM_DRIVER = 0x2000;  // A WDM driver.
		// 0x4000 ???
		const IMAGE_DLL_CHARACTERISTICS_TERMINAL_SERVER_AWARE = 0x8000;  // Terminal Server aware.

		// Section flags.
		// 0x00000000 is reserved.
		// 0x00000001 is reserved.
		// 0x00000002 is reserved.
		// 0x00000004 is reserved.
		const IMAGE_SCN_TYPE_NO_PAD = 0x00000008;  // The section should not be padded to the next boundary.  Obsolete.  Replaced by IMAGE_SCN_ALIGN_1BYTES.
		// 0x00000010 is reserved.
		const IMAGE_SCN_CNT_CODE = 0x00000020;  // The section contains executable code.
		const IMAGE_SCN_CNT_INITIALIZED_DATA = 0x00000040;  // The section contains initialized data.
		const IMAGE_SCN_CNT_UNINITIALIZED_DATA = 0x00000080;  // The section contains uninitialized data.
		const IMAGE_SCN_LNK_OTHER = 0x00000100;  // Reserved.
		const IMAGE_SCN_LNK_INFO = 0x00000200;  // The section contains comments or other information.  Object files only.
		// 0x00000400 is reserved.
		const IMAGE_SCN_LNK_REMOVE = 0x00000800;  // The section will not become part of the image.  Object files only.
		const IMAGE_SCN_LNK_COMDAT = 0x00001000;  // The section contains COMDAT data.  Object files only.
		// 0x00002000 is reserved.
		const IMAGE_SCN_NO_DEFER_SPEC_EXC = 0x00004000;  // Reset speculative exceptions handling bits in the TLB entries for this section.
		const IMAGE_SCN_GPREL = 0x00008000;  // The section contains data referenced through the global pointer.
		// 0x00010000 is reserved.
		const IMAGE_SCN_MEM_PURGEABLE = 0x00020000;  // Reserved.
		const IMAGE_SCN_MEM_16BIT = 0x00020000;  // Reserved.
		const IMAGE_SCN_MEM_LOCKED = 0x00040000;  // Reserved.
		const IMAGE_SCN_MEM_PRELOAD = 0x00080000;  // Reserved.
		const IMAGE_SCN_ALIGN_1BYTES = 0x00100000;  // Align data on a 1-byte boundary.  Object files only.
		const IMAGE_SCN_ALIGN_2BYTES = 0x00200000;  // Align data on a 2-byte boundary.  Object files only.
		const IMAGE_SCN_ALIGN_4BYTES = 0x00300000;  // Align data on a 4-byte boundary.  Object files only.
		const IMAGE_SCN_ALIGN_8BYTES = 0x00400000;  // Align data on a 8-byte boundary.  Object files only.
		const IMAGE_SCN_ALIGN_16BYTES = 0x00500000;  // Align data on a 16-byte boundary.  Object files only.
		const IMAGE_SCN_ALIGN_32BYTES = 0x00600000;  // Align data on a 32-byte boundary.  Object files only.
		const IMAGE_SCN_ALIGN_64BYTES = 0x00700000;  // Align data on a 64-byte boundary.  Object files only.
		const IMAGE_SCN_ALIGN_128BYTES = 0x00800000;  // Align data on a 128-byte boundary.  Object files only.
		const IMAGE_SCN_ALIGN_256BYTES = 0x00900000;  // Align data on a 256-byte boundary.  Object files only.
		const IMAGE_SCN_ALIGN_512BYTES = 0x00A00000;  // Align data on a 512-byte boundary.  Object files only.
		const IMAGE_SCN_ALIGN_1024BYTES = 0x00B00000;  // Align data on a 1024-byte boundary.  Object files only.
		const IMAGE_SCN_ALIGN_2048BYTES = 0x00C00000;  // Align data on a 2048-byte boundary.  Object files only.
		const IMAGE_SCN_ALIGN_4096BYTES = 0x00D00000;  // Align data on a 4096-byte boundary.  Object files only.
		const IMAGE_SCN_ALIGN_8192BYTES = 0x00E00000;  // Align data on a 8192-byte boundary.  Object files only.
		// 0x00F00000 is unused.
		const IMAGE_SCN_LNK_NRELOC_OVFL = 0x01000000;  // Contains extended relocations due to overflow.
		const IMAGE_SCN_MEM_DISCARDABLE = 0x02000000;  // Can be discarded as needed.
		const IMAGE_SCN_MEM_NOT_CACHED = 0x04000000;  // Cannot be cached.
		const IMAGE_SCN_MEM_NOT_PAGED = 0x08000000;  // Cannot be paged.
		const IMAGE_SCN_MEM_SHARED = 0x10000000;  // Can be shared in memory.
		const IMAGE_SCN_MEM_EXECUTE = 0x20000000;  // Can be executed as code.
		const IMAGE_SCN_MEM_READ = 0x40000000;  // Can be read.
		const IMAGE_SCN_MEM_WRITE = 0x80000000;  // Can be written to.

		// Resource table types.
		// 0 is unused.
		const RT_CURSOR = 1;  // Hardware-dependent cursor.
		const RT_BITMAP = 2;  // Bitmap.
		const RT_ICON = 3;  // Hardware-dependent icon.
		const RT_MENU = 4;  // Menu.
		const RT_DIALOG = 5;  // Dialog box.
		const RT_STRING = 6;  // String table entry.
		const RT_FONTDIR = 7;  // Font directory.
		const RT_FONT = 8;  // Font.
		const RT_ACCELERATOR = 9;  // Accelerator table.  Keyboard shortcuts for menu items.
		const RT_RCDATA = 10;  // Application-defined raw data.
		const RT_MESSAGETABLE = 11;  // Message table.
		const RT_GROUP_CURSOR = 12;  // Hardware-independent cursor.
		// 13 is unused.
		const RT_GROUP_ICON = 14;  // Hardware-independent icon.
		// 15 is unused.
		const RT_VERSION = 16;  // Version information.
		const RT_DLGINCLUDE = 17;  // Resource editing tool-specific string.
		// 18 is unused.
		const RT_PLUGPLAY = 19;  // Plug and Play.  "Plug and pray" was related to late ISA/EISA, early PCI, and also when USB 1.x first came out.  Probably obsolete.
		const RT_VXD = 20;  // VXD.  Virtual hardware driver for Windows 3.x and Win9x.  Obsolete.
		const RT_ANICURSOR = 21;  // Animated cursor.
		const RT_ANIICON = 22;  // Animated icon.
		const RT_HTML = 23;  // HTML.
		const RT_MANIFEST = 24;  // Side-by-Side (SxS) Assembly Manifest.  Still somehow a thing but should be obsoleted because involving XML during loading of software was, is, and will always be a bad idea.

		public static $resource_types = array(
			// 0 is unused.
			1 => "Hardware-dependent cursor",
			2 => "Bitmap",
			3 => "Hardware-dependent Icon",
			4 => "Menu",
			5 => "Dialog",
			6 => "String table",
			7 => "Font directory",
			8 => "Font",
			9 => "Accelerator table",
			10 => "Application-defined",
			11 => "Message table",
			12 => "Hardware-independent cursor",
			// 13 is unused.
			14 => "Hardware-independent icon",
			// 15 is unused.
			16 => "Version information",
			17 => "Resource editor-specific string",
			// 18 is unused.
			19 => "Plug and Play",
			20 => "Virtual hardware driver (VxD)",
			21 => "Animated cursor",
			22 => "Animated icon",
			23 => "HTML",
			24 => "Manifest",
		);

		// Version information resource Fixed File Information structure.
		const VERINFO_VS_FFI_FILEFLAGSMASK = 0x0000003F;  // The default mask for the fixed info flags.
		const VERINFO_VS_FF_DEBUG = 0x00000001;  // The file contains debugging information.
		const VERINFO_VS_FF_PRERELEASE = 0x00000002;  // The file is a development version.
		const VERINFO_VS_FF_PATCHED = 0x00000004;  // The file has been modified and not identical to the original shipping version.
		const VERINFO_VS_FF_PRIVATEBUILD = 0x00000008;  // The file was not built using standard release procedures.  A PrivateBuild entry should be defined.
		const VERINFO_VS_FF_INFOINFERRED = 0x00000010;  // Structure created dynamically.  Flag should never be set.
		const VERINFO_VS_FF_SPECIALBUILD = 0x00000020;  // The file was built using standard release procedures but is special.  A SpecialBuild entry should be defined.

		const VERINFO_VOS_UNKNOWN = 0x00000000;
		const VERINFO_VOS__WINDOWS16 = 0x00000001;
		const VERINFO_VOS__PM16 = 0x00000002;  // 16-bit Presentation Manager.
		const VERINFO_VOS__PM32 = 0x00000003;  // 32-bit Presentation Manager.
		const VERINFO_VOS__WINDOWS32 = 0x00000004;
		const VERINFO_VOS_DOS = 0x00010000;
		const VERINFO_VOS_OS216 = 0x00020000;
		const VERINFO_VOS_OS232 = 0x00030000;
		const VERINFO_VOS_NT = 0x00040000;

		const VERINFO_VFT_UNKNOWN = 0x00000000;
		const VERINFO_VFT_APP = 0x00000001;
		const VERINFO_VFT_DLL = 0x00000002;
		const VERINFO_VFT_DRV = 0x00000003;  // File contains a device driver.
		const VERINFO_VFT_FONT = 0x00000004;
		const VERINFO_VFT_VXD = 0x00000005;  // File contains a virtual device.
		// 0x00000006 is reserved.
		const VERINFO_VFT_STATIC_LIB = 0x00000007;

		const VERINFO_VFT2_UNKNOWN = 0x00000000;
		const VERINFO_VFT2_DRV_PRINTER = 0x00000001;  // Printer driver.
		const VERINFO_VFT2_DRV_KEYBOARD = 0x00000002;  // Keyboard driver.
		const VERINFO_VFT2_DRV_LANGUAGE = 0x00000003;  // Language driver.
		const VERINFO_VFT2_DRV_DISPLAY = 0x00000004;  // Display driver.
		const VERINFO_VFT2_DRV_MOUSE = 0x00000005;  // Mouse driver.
		const VERINFO_VFT2_DRV_NETWORK = 0x00000006;  // Network driver.
		const VERINFO_VFT2_DRV_SYSTEM = 0x00000007;  // System driver.
		const VERINFO_VFT2_DRV_INSTALLABLE = 0x00000008;  // Installable driver.
		const VERINFO_VFT2_DRV_SOUND = 0x00000009;  // Sound driver.
		const VERINFO_VFT2_DRV_COMM = 0x0000000A;  // Communications driver.
		// 0x0000000B is reserved.
		const VERINFO_VFT2_DRV_VERSIONED_PRINTER = 0x0000000C;  // Versioned printer driver.

		const VERINFO_VFT2_FONT_RASTER = 0x00000001;  // Raster font.
		const VERINFO_VFT2_FONT_VECTOR = 0x00000002;  // Vector font.
		const VERINFO_VFT2_FONT_TRUETYPE = 0x00000003;  // TrueType font.


		// Certificates.
		const WIN_CERT_REVISION_1_0 = 0x0100;  // Version 1, legacy version.
		const WIN_CERT_REVISION_2_0 = 0x0200;  // Version 2, current version.

		const WIN_CERT_TYPE_X509 = 0x0001;  // X.509 certificate type.  Not Supported.
		const WIN_CERT_TYPE_PKCS_SIGNED_DATA = 0x0002;  // PKCS#7 SignedData structure.  // Only valid type.
		// 0x0003 is reserved.
		const WIN_CERT_TYPE_TS_STACK_SIGNED = 0x0004;  // Terminal Server Protocol Stack Certificate signing.  Not Supported.

		// Base relocation types.
		const IMAGE_REL_BASED_ABSOLUTE = 0;  // Skip.  Used to pad/align a block.
		const IMAGE_REL_BASED_HIGH = 1;  // Add high 16 bits to the 16-bit field at the offset.
		const IMAGE_REL_BASED_LOW = 2;  // Add low 16 bits to the 16-bit field at the offset.
		const IMAGE_REL_BASED_HIGHLOW = 3;  // Add all 32 bits of the difference at the offset.  Most common relocation type.
		const IMAGE_REL_BASED_HIGHADJ = 4;  // Add high 16 bits to the 16-bit field at the offset.  The low 16 bits of the 32-bit value are stored in the 16-bit word that follows this base relocation (i.e. occupies two slots).
		const IMAGE_REL_BASED_MIPS_JMPADDR = 5;  // Apply to a MIPS jump instruction.  Interpretation is actually dependent on the machine type.
		const IMAGE_REL_BASED_ARM_MOV32 = 5;
		const IMAGE_REL_BASED_RISCV_HIGH20 = 5;
		// 6 is reserved.
		const IMAGE_REL_BASED_THUMB_MOV32 = 7;
		const IMAGE_REL_BASED_RISCV_LOW12I = 7;
		const IMAGE_REL_BASED_RISCV_LOW12S = 8;
		const IMAGE_REL_BASED_MIPS_JMPADDR16 = 9;  // Apply to a MIPS16 jump instruction.
		const IMAGE_REL_BASED_IA64_IMM64 = 9;
		const IMAGE_REL_BASED_DIR64 = 10;  // Add the difference to the 64-bit field at the offset.
		const IMAGE_REL_BASED_HIGH3ADJ = 11;  // Not valid for NT executables.

		// Debug directory types.
		const IMAGE_DEBUG_TYPE_UNKNOWN = 0;
		const IMAGE_DEBUG_TYPE_COFF = 1;
		const IMAGE_DEBUG_TYPE_CODEVIEW = 2;
		const IMAGE_DEBUG_TYPE_FPO = 3;
		const IMAGE_DEBUG_TYPE_MISC = 4;
		const IMAGE_DEBUG_TYPE_EXCEPTION = 5;
		const IMAGE_DEBUG_TYPE_FIXUP = 6;
		const IMAGE_DEBUG_TYPE_OMAP_TO_SRC  = 7;
		const IMAGE_DEBUG_TYPE_OMAP_FROM_SRC  = 8;
		const IMAGE_DEBUG_TYPE_BORLAND = 9;  // Reserved for Borland.  Nice.  Borland doesn't exactly exist any more.
		// 10 is reserved.
		const IMAGE_DEBUG_TYPE_CLSID = 11;
		// 12-15 are unknown.
		const IMAGE_DEBUG_TYPE_REPRO = 16;  // PE determinism or reproducibility.
		// 17-19 are unknown.
		const IMAGE_DEBUG_TYPE_EX_DLLCHARACTERISTICS = 20;  // Extended DLL characteristics.


		// Win16 NE program flags.
		const WIN16_NE_PROGRAM_FLAGS_DGROUP_NONE = 0;
		const WIN16_NE_PROGRAM_FLAGS_DGROUP_SINSHARED = 1;
		const WIN16_NE_PROGRAM_FLAGS_DGROUP_MULTIPLE = 2;
		const WIN16_NE_PROGRAM_FLAGS_DGROUP_NULL = 3;
		const WIN16_NE_PROGRAM_FLAGS_GLOBAL_INIT = 0x04;  // Global initialization.
		const WIN16_NE_PROGRAM_FLAGS_PROTECTED_MODE_ONLY = 0x08;  // Protected Mode only.
		const WIN16_NE_PROGRAM_FLAGS_8086 = 0x10;  // 8086 instructions.
		const WIN16_NE_PROGRAM_FLAGS_80286 = 0x20;  // 80286 instructions.
		const WIN16_NE_PROGRAM_FLAGS_80386 = 0x40;  // 80386 instructions.
		const WIN16_NE_PROGRAM_FLAGS_8087 = 0x80;  // 8087 FPU instructions.

		// Win16 NE app flags.
		const WIN16_NE_APP_FLAGS_TYPE_NONE = 0;
		const WIN16_NE_APP_FLAGS_TYPE_FULLSCREEN = 1;  // Fullscreen, not aware of Windows P.M. API.  (P.M. = Protected Mode?)
		const WIN16_NE_APP_FLAGS_TYPE_WINPMCOMPAT = 2;  // Compatible with Windows P.M. API.
		const WIN16_NE_APP_FLAGS_TYPE_WINPMUSES = 3;  // Uses Windows P.M. API.
		const WIN16_NE_APP_FLAGS_OS2_APP = 0x08;  // OS/2 application.
		// 0x10 is reserved/unused.
		const WIN16_NE_APP_FLAGS_IMAGE_ERROR = 0x20;  // Errors in image/executable.  Weird.
		const WIN16_NE_APP_FLAGS_NON_CONFORM = 0x40;  // Non-conforming program.  Huh?
		const WIN16_NE_APP_FLAGS_DLL = 0x80;  // DLL or driver (SS:SP invalid, CS:IP->Far INIT routine, AX = HMODULE, returns AX == 0 success, AX != 0 fail)

		// Win16 NE target OS.
		const WIN16_NE_TARGET_OS_UNKNOWN = 0;
		const WIN16_NE_TARGET_OS_OS2 = 1;  // OS/2.
		const WIN16_NE_TARGET_OS_WIN = 2;  // Win16.
		const WIN16_NE_TARGET_OS_DOS4 = 3;  // European DOS 4.x.
		const WIN16_NE_TARGET_OS_WIN386 = 4;  // Win32s (32-bit code).
		const WIN16_NE_TARGET_OS_BOSS = 5;  // Borland Operating System Services.

		public static $ne_target_oses = array(
			0 => "Unknown",
			1 => "OS/2",
			2 => "Win16",
			3 => "DOS 4.x (European)",
			4 => "Win32s",
			5 => "Borland Operating System Services (BOSS)"
		);

		// Win16 NE other OS/2 flags.
		const WIN16_NE_OS2_EXE_FLAGS_LFN = 0x01;  // Long File Names.
		const WIN16_NE_OS2_EXE_FLAGS_PROTECTED_MODE = 0x02;  // Protected Mode executable (OS/2 2.x).
		const WIN16_NE_OS2_EXE_FLAGS_PROPORTIONAL_FONTS = 0x04;  // Proportional fonts (OS/2 2.x).
		const WIN16_NE_OS2_EXE_FLAGS_GANGLOAD_AREA = 0x08;  // Executable has gangload area.


		public function __construct()
		{
		}

		public static function GetDefaultDOSHeader()
		{
			// Best document on the DOS EXE header format that I was able to find:  http://www.tavi.co.uk/phobos/exeformat.html
			$result = array(
				"signature" => "MZ",  // Some very old compilers incorrectly produce ZM.
				"bytes_last_page" => strlen(self::$defaultDOSstub) % 512,
				"pages" => 3,  // Number of 512 byte pages in the file.  The '3' value doesn't seem to be correct?  Total file size = (pages - 1) * 512 + bytes_last_page
				"relocations" => 0,  // Number of relocation table entries.
				"header_size" => 4,  // Size of the MS-DOS header in paragraphs (a "paragraph" is 16 bytes).  (4 * 16 = 64)
				"min_memory" => 0,  // The minimum amount of extra memory required (in paragraphs) to run the DOS program.
				"max_memory" => 65535,  // The maximum amount of extra memory required (in paragraphs) to run the DOS program.
				"initial_ss" => 0,  // The paragraph address of the stack segment (SS) relative to the start of the load module.
				"initial_sp" => 0x00B8,  // The value to load into the SP register.
				"checksum" => 0x0000,  // The checksum of the file.  Ignored/not calculated when 0.
				"initial_ip" => 0x0000,  // The value to load into the IP register.
				"initial_cs" => 0x0000,  // The relative value to load into the CS register.
				"reloc_offset" => 64,  // The offset from the start of the file to the start of the relocation table (in bytes).
				"overlay_num" => 0,  // The number of the overlay.  This is only relevant to programs that use overlays, which is rare.  Sections of the program that remain on disk and are loaded with special code - basically shared memory of sorts.
				"reserved_1" => str_repeat("\x00", 8),
				"oem_identifier" => 0,  // Possibly for Windows drivers.
				"oem_info" => 0,  // Possibly for Windows drivers.  Value is dependent upon oem_identifier.
				"reserved_2" => str_repeat("\x00", 20),
				"pe_offset" => 0x000000D8,  // The 32-bit offset in the file to the start of the PE header.  This offset is always located at 0x3C in the DOS header.
				"pe_offset_valid" => true,  // Whether or not the PE offset is invalid.
			);

			return $result;
		}

		public static function GetDefaultPEHeader($optheadersig = self::OPT_HEADER_SIGNATURE_PE32)
		{
			$bits64 = ($optheadersig === self::OPT_HEADER_SIGNATURE_PE32_PLUS);

			// An interesting summary of the PE file format that DoD has put together:  https://github.com/deptofdefense/SalSA/wiki/PE-File-Format
			$result = array(
				"signature" => "PE\x00\x00",  // PE header signature.
				"machine_type" => self::IMAGE_FILE_MACHINE_UNKNOWN,  // Any machine.
				"num_sections" => 0,  // Number of sections.
				"created" => time(),  // A 32-bit UNIX timestamp.  Fixing this will be pretty awesome in 2038.
				"symbol_table_ptr" => 0,  // Offset to the COFF symbol table or zero if not present.  Should always be zero since COFF debugging info is deprecated.
				"num_symbols" => 0,  // Number of COFF symbol table entries.  Should always be zero since COFF debugging info is deprecated.
				"optional_header_size" => ($bits64 ? 0x00F0 : 0x00E0),  // The size of the "optional" header.  Required for images.
				"flags" => self::IMAGE_FILE_DLL,  // See "Image flags" options above.
			);

			return $result;
		}

		public static function GetDefaultPEOptHeader($optheadersig = self::OPT_HEADER_SIGNATURE_PE32)
		{
			$bits64 = ($optheadersig === self::OPT_HEADER_SIGNATURE_PE32_PLUS);

			$result = array(
				"signature" => $optheadersig,  // Default is PE32 (0x010B).  See "Optional header signature" options above.
				"major_linker_ver" => 0,  // Linker major version num.
				"minor_linker_ver" => 0,  // Linker major version num.
				"code_size" => 0,  // Total size of all code (text) sections.
				"initialized_data_size" => 0,  // Total size of all initialized data sections.
				"uninitialized_data_size" => 0,  // Total size of all uninitialized data (BSS) sections.
				"entry_point_addr" => 0,  // The address of the entry point.  Zero means no entry point.
				"code_base" => 0,  // The image_base relative address of the code section.
				"data_base" => 0,  // The image_base relative address of the data section.  Used by PE32 but not PE32+.

				// Start of Windows-specific fields for the COFF format.
				"image_base" => self::IMAGE_BASE_DLL_DEFAULT,  // The preferred address of the first byte of the image when loaded into memory.  Must be a multiple of 64K.  8 bytes for PE32+.
				"section_alignment" => 4096,  // The alignment, in bytes, of sections when loaded into memory.  Should generally be the size for the architecture.
				"file_alignment" => 512,  // The alignment, in bytes, used to align each section in the file.
				"major_os_ver" => 5,  // The major version number of the required operating system (e.g. 5.1 = Windows XP).
				"minor_os_ver" => 1,  // The minor version number of the required operating system (e.g. 5.1 = Windows XP).
				"major_image_ver" => 0,  // The major version number of the image.
				"minor_image_ver" => 0,  // The minor version number of the image.
				"major_subsystem_ver" => 5,  // The major version number of the subsystem.  Generally the same as major_os_ver.
				"minor_subsystem_ver" => 1,  // The minor version number of the subsystem.  Generally the same as minor_os_ver.
				"win32_version" => 0,  // Win32 version value.  Reserved.  Always zero.  Was possibly used for Win32s applications?
				"image_size" => 0,  // The size, in bytes, of the image.  Largest section RVA + the section's raw size rounded up to the nearest section_alignment.
				"headers_size" => 1024,  // The combined size of the MS-DOS headers, stub, PE header, and section headers rounded up to a multiple of file_alignment.
				"checksum_pos" => 0x00000130,  // The position in the parsed file data where the checksum starts.  Allows for hash and checksum calculations later on to avoid this position in the data.
				"checksum" => 0,  // The image file checksum.  The loader validates:  All drivers, DLLs loaded at boot, and any DLL loaded into a critical process.
				"subsystem" => 3,  // The Windows subsystem required to run this image.  See "Image subsystems (startup)" options above.
				"dll_characteristics" => 0,  // Can be used by EXEs too (e.g. IMAGE_DLL_CHARACTERISTICS_DYNAMIC_BASE | IMAGE_DLL_CHARACTERISTICS_NX_COMPAT).  See "DLL characteristics" options above.
				"stack_reserve_size" => 0x00100000,  // Maximum stack size to reserve (but not commit).  The rest is made available one page at a time as needed until the reserve size is reached.  8 bytes for PE32+.
				"stack_commit_size" => 0x00001000,  // Initial stack size to commit.  8 bytes for PE32+.
				"heap_reserve_size" => 0x00100000,  // Local heap size to reserve (but not commit).  The rest is made available one page at a time as needed until the reserve size is reached.  8 bytes for PE32+.
				"heap_commit_size" => 0x00001000,  // Initial heap size to commit.  8 bytes for PE32+.
				"loader_flags" => 0,  // Reserved.  Always zero.
				"num_data_directories" => 16,  // Windows won't load more than 16 (currently).
				"data_directories_pos" => 0x0000014C + ($bits64 ? 16 : 0),  // The position in the parsed file data where the data directory table starts.  Allows for hash calculations for certificates later on.
			);

			if ($bits64)  unset($result["data_base"]);

			return $result;
		}

		public static function InitDataDirectory()
		{
			$result = array(
				"exports" => array("rva" => 0, "size" => 0),  // .edata section.
				"imports" => array("rva" => 0, "size" => 0),  // .idata section.
				"resources" => array("rva" => 0, "size" => 0),  // .rsrc section.
				"exceptions" => array("rva" => 0, "size" => 0),  // .pdata section.
				"certificates" => array("pos" => 0, "size" => 0),  // Note:  Position in the data is used instead of RVA.
				"base_relocations" => array("rva" => 0, "size" => 0),  // .reloc section.
				"debug" => array("rva" => 0, "size" => 0),  // .debug section.
				"architecture" => array("rva" => 0, "size" => 0),  // Reserved.
				"global_ptr" => array("rva" => 0, "size" => 0),  // RVA to store in global pointer register.
				"tls" => array("rva" => 0, "size" => 0),  // Thread Local Storage.  .tls section.
				"load_config" => array("rva" => 0, "size" => 0),  // Load Configuration Structure.
				"bound_imports" => array("pos" => 0, "size" => 0),  // Bound imports table.
				"iat" => array("rva" => 0, "size" => 0),  // Import Address Table.
				"delay_imports" => array("rva" => 0, "size" => 0),  // Delay-load import tables.
				"clr_runtime_header" => array("rva" => 0, "size" => 0),  // .NET CLR header.  .cormeta section.
			);

			return $result;
		}

		public function InitPE($optheadersig = self::OPT_HEADER_SIGNATURE_PE32)
		{
			unset($this->ne_header);

			$this->dos_header = self::GetDefaultDOSHeader();
			$this->dos_stub = self::$defaultDOSstub;
			$this->pe_header = self::GetDefaultPEHeader($optheadersig);
			$this->pe_opt_header = self::GetDefaultPEOptHeader($optheadersig);
			$this->pe_data_dir = self::InitDataDirectory();
			$this->pe_sections = array();
		}

		public static function ValidateFile($filename, $readpesig = true)
		{
			if (!is_file($filename))  return array("success" => false, "error" => "Invalid filename.", "errorcode" => "invalid_filename", "info" => $filename);

			$size = filesize($filename);
			if ($size < 64)  return array("success" => false, "error" => "The file is too small to be a valid executable.", "errorcode" => "file_too_small");
			if ($size >= 2147483648)  return array("success" => false, "error" => "The file is too large to be a valid PE file.", "errorcode" => "file_too_large");

			// Do some basic seeking within the file to check for valid signatures.
			$fp = @fopen($filename, "rb");
			if ($fp === false)  return array("success" => false, "error" => "Unable to open file.", "errorcode" => "fopen_failed", "info" => $filename);

			// MS-DOS signature.
			$sig = fread($fp, 2);
			if ($sig !== "MZ" && $sig !== "ZM")
			{
				fclose($fp);

				return array("success" => false, "error" => "File is not a valid executable.  Missing 'MZ' signature.", "errorcode" => "missing_mz_signature");
			}

			if ($readpesig)
			{
				// Read PE offset.
				fseek($fp, 60);

				$offset = unpack("V", fread($fp, 4))[1];
				if ($offset < 0 || $offset > $size - 4)
				{
					fclose($fp);

					return array("success" => false, "error" => "File is not a valid PE file.  The PE offset is too large or invalid.", "errorcode" => "pe_offset_too_large_or_invalid");
				}

				// PE signature.
				fseek($fp, $offset);
				$sig = fread($fp, 4);

				if ($sig !== "PE\x00\x00")
				{
					fclose($fp);

					return array("success" => false, "error" => "File is not a valid PE file.  Missing 'PE' signature.", "errorcode" => "missing_pe_file_signature");
				}
			}

			fclose($fp);

			return array("success" => true);
		}

		public function Parse($data, $options = array())
		{
			// Default options.
			if (!isset($options["pe_section_data"]))  $options["pe_section_data"] = false;
			if (!isset($options["pe_directory_data"]))  $options["pe_directory_data"] = true;
			if (!isset($options["pe_directories"]) || !is_string($options["pe_directories"]))  $options["pe_directories"] = "all";

			$dirs = explode(",", $options["pe_directories"]);
			$options["pe_directories"] = array();
			foreach ($dirs as $dir)
			{
				$dir = trim($dir);

				if ($dir !== "")  $options["pe_directories"][$dir] = true;
			}

			// Be overly aggressive on RAM cleanup.
			if (function_exists("gc_mem_caches"))  gc_mem_caches();

			// Reset internal structures.
			$this->dos_header = array();
			unset($this->dos_stub);
			unset($this->pe_header);
			unset($this->pe_opt_header);
			unset($this->pe_data_dir);
			unset($this->pe_sections);

			// Read the DOS header.
			$y = strlen($data);
			$x = 0;
			$this->dos_header["signature"] = self::GetBytes($data, $x, $y, 2);
			if ($this->dos_header["signature"] !== "MZ" && $this->dos_header["signature"] !== "ZM")  return array("success" => false, "error" => "File is not a valid executable.  Missing 'MZ' signature.", "errorcode" => "missing_mz_signature");

			$this->dos_header["bytes_last_page"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["pages"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["relocations"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["header_size"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["min_memory"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["max_memory"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["initial_ss"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["initial_sp"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["checksum"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["initial_ip"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["initial_cs"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["reloc_offset"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["overlay_num"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["reserved_1"] = self::GetBytes($data, $x, $y, 8);
			$this->dos_header["oem_identifier"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["oem_info"] = self::GetUInt16($data, $x, $y);
			$this->dos_header["reserved_2"] = self::GetBytes($data, $x, $y, 20);
			$this->dos_header["pe_offset"] = self::GetUInt32($data, $x, $y);
			$this->dos_header["pe_offset_valid"] = true;

			// Detect MS-DOS only.
			if ($this->dos_header["pe_offset"] < 2 || $this->dos_header["pe_offset"] >= $y)
			{
				$this->dos_header["pe_offset_valid"] = false;

				$this->dos_stub = self::GetBytes($data, $x, $y, $y - $x);

				return array("success" => true);
			}

			// Read the DOS stub.  Note that if an EXE specifies a value less than 4 for 'header_size', then the 'reserved_1' and 'reserved_2' regions may actually contain code.
			$this->dos_stub = self::GetBytes($data, $x, $y, $this->dos_header["pe_offset"] - $x);

			// Some EXEs are invalid for modern Windows due to clever techiques to move the PE header into the DOS header in order to save a few bytes.
			// Modern Windows will refuse to load such files.  They are also invalid DOS executables.
			if ($this->dos_header["pe_offset"] < $x)
			{
				$x = $this->dos_header["pe_offset"];

				if ($x)  $this->dos_header["pe_offset_valid"] = false;
			}

			// The NE/PE header is expected to be DWORD-aligned in the file.
			if ($x % 4)  $this->dos_header["pe_offset_valid"] = false;

			// Peek at the signature first.
			if (substr($data, $x, 2) === "NE")
			{
				// Win16 NE file format.  Do some very basic structure extraction.
				// See:  http://bytepointer.com/resources/win16_ne_exe_format_win3.0.htm
				$this->ne_header = array();
				$this->ne_header["signature"] = self::GetBytes($data, $x, $y, 2);
				$this->ne_header["major_ver"] = self::GetUInt8($data, $x, $y);
				$this->ne_header["minor_ver"] = self::GetUInt8($data, $x, $y);
				$this->ne_header["entry_table_offset"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["entry_table_length"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["checksum"] = self::GetUInt32($data, $x, $y);
				$this->ne_header["program_flags"] = self::GetUInt8($data, $x, $y);
				$this->ne_header["app_flags"] = self::GetUInt8($data, $x, $y);
				$this->ne_header["auto_ds_index"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["init_heap_size"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["init_stack_size"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["entry_point_cs_ip"] = self::GetUInt32($data, $x, $y);
				$this->ne_header["init_stack_ss_sp"] = self::GetUInt32($data, $x, $y);
				$this->ne_header["num_segments"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["num_dll_refs"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["non_resident_names_table_size"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["segment_table_offset"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["resources_table_offset"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["resident_names_table_offset"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["dll_refs_offset"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["import_names_table_offset"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["non_resident_names_table_offset"] = self::GetUInt32($data, $x, $y);  // From the start of the file.
				$this->ne_header["num_moveable_entry_points"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["file_align_size_shift_count"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["num_resources"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["target_os"] = self::GetUInt8($data, $x, $y);
				$this->ne_header["os2_exe_flags"] = self::GetUInt8($data, $x, $y);
				$this->ne_header["return_thunks_offset"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["segment_ref_thunks_offset"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["min_code_swap_size"] = self::GetUInt16($data, $x, $y);
				$this->ne_header["expected_win_ver_minor"] = self::GetUInt8($data, $x, $y);
				$this->ne_header["expected_win_ver_major"] = self::GetUInt8($data, $x, $y);

				unset($this->pe_header);
				unset($this->pe_opt_header);
				unset($this->pe_data_dir);
				unset($this->pe_sections);
			}
			else
			{
				$this->pe_header = array();

				$this->pe_header["signature"] = self::GetBytes($data, $x, $y, 4);

				if ($this->pe_header["signature"] !== "PE\x00\x00")
				{
					$this->dos_header["pe_offset"] = 0;

					unset($this->pe_header);

					return array("success" => false, "error" => "File is not a valid PE file.  Missing 'PE' signature.", "errorcode" => "missing_pe_file_signature");
				}

				$this->pe_header["machine_type"] = self::GetUInt16($data, $x, $y);
				$this->pe_header["num_sections"] = self::GetUInt16($data, $x, $y);
				$this->pe_header["created"] = self::GetUInt32($data, $x, $y);
				$this->pe_header["symbol_table_ptr"] = self::GetUInt32($data, $x, $y);
				$this->pe_header["num_symbols"] = self::GetUInt32($data, $x, $y);
				$this->pe_header["optional_header_size"] = self::GetUInt16($data, $x, $y);
				$this->pe_header["flags"] = self::GetUInt16($data, $x, $y);

				// Process the optional header.
				if ($this->pe_header["optional_header_size"])
				{
					$this->pe_opt_header = array();

					$x2 = $x;
					$y2 = $x + $this->pe_header["optional_header_size"];
					$x = $y2;

					$this->pe_opt_header["signature"] = self::GetUInt16($data, $x2, $y2);
					$bits64 = ($this->pe_opt_header["signature"] === self::OPT_HEADER_SIGNATURE_PE32_PLUS);

					$this->pe_opt_header["major_linker_ver"] = self::GetUInt8($data, $x2, $y2);
					$this->pe_opt_header["minor_linker_ver"] = self::GetUInt8($data, $x2, $y2);
					$this->pe_opt_header["code_size"] = self::GetUInt32($data, $x2, $y2);
					$this->pe_opt_header["initialized_data_size"] = self::GetUInt32($data, $x2, $y2);
					$this->pe_opt_header["uninitialized_data_size"] = self::GetUInt32($data, $x2, $y2);
					$this->pe_opt_header["entry_point_addr"] = self::GetUInt32($data, $x2, $y2);
					$this->pe_opt_header["code_base"] = self::GetUInt32($data, $x2, $y2);
					if (!$bits64)  $this->pe_opt_header["data_base"] = self::GetUInt32($data, $x2, $y2);

					$this->pe_opt_header["image_base"] = ($bits64 ? self::GetUInt64($data, $x2, $y2) : self::GetUInt32($data, $x2, $y2));
					$this->pe_opt_header["section_alignment"] = self::GetUInt32($data, $x2, $y2);
					$this->pe_opt_header["file_alignment"] = self::GetUInt32($data, $x2, $y2);
					$this->pe_opt_header["major_os_ver"] = self::GetUInt16($data, $x2, $y2);
					$this->pe_opt_header["minor_os_ver"] = self::GetUInt16($data, $x2, $y2);
					$this->pe_opt_header["major_image_ver"] = self::GetUInt16($data, $x2, $y2);
					$this->pe_opt_header["minor_image_ver"] = self::GetUInt16($data, $x2, $y2);
					$this->pe_opt_header["major_subsystem_ver"] = self::GetUInt16($data, $x2, $y2);
					$this->pe_opt_header["minor_subsystem_ver"] = self::GetUInt16($data, $x2, $y2);
					$this->pe_opt_header["win32_version"] = self::GetUInt32($data, $x2, $y2);
					$this->pe_opt_header["image_size"] = self::GetUInt32($data, $x2, $y2);
					$this->pe_opt_header["headers_size"] = self::GetUInt32($data, $x2, $y2);
					$this->pe_opt_header["checksum_pos"] = $x2;
					$this->pe_opt_header["checksum"] = self::GetUInt32($data, $x2, $y2);
					$this->pe_opt_header["subsystem"] = self::GetUInt16($data, $x2, $y2);
					$this->pe_opt_header["dll_characteristics"] = self::GetUInt16($data, $x2, $y2);
					$this->pe_opt_header["stack_reserve_size"] = ($bits64 ? self::GetUInt64($data, $x2, $y2) : self::GetUInt32($data, $x2, $y2));
					$this->pe_opt_header["stack_commit_size"] = ($bits64 ? self::GetUInt64($data, $x2, $y2) : self::GetUInt32($data, $x2, $y2));
					$this->pe_opt_header["heap_reserve_size"] = ($bits64 ? self::GetUInt64($data, $x2, $y2) : self::GetUInt32($data, $x2, $y2));
					$this->pe_opt_header["heap_commit_size"] = ($bits64 ? self::GetUInt64($data, $x2, $y2) : self::GetUInt32($data, $x2, $y2));
					$this->pe_opt_header["loader_flags"] = self::GetUInt32($data, $x2, $y2);
					$this->pe_opt_header["num_data_directories"] = self::GetUInt32($data, $x2, $y2);
					$this->pe_opt_header["data_directories_pos"] = $x2;

					$this->pe_data_dir = self::InitDataDirectory();

					$num = 0;
					foreach ($this->pe_data_dir as $key => $info)
					{
						if ($num >= $this->pe_opt_header["num_data_directories"])  break;

						if (isset($info["rva"]))  $info["rva"] = self::GetUInt32($data, $x2, $y2);
						else  $info["pos"] = self::GetUInt32($data, $x2, $y2);

						$info["size"] = self::GetUInt32($data, $x2, $y2);

						$this->pe_data_dir[$key] = $info;

						$num++;
					}
				}

				$this->pe_sections = array();

				for ($x2 = 0; $x2 < $this->pe_header["num_sections"] && $x < $y; $x2++)
				{
					$section = array(
						"name" => self::GetBytes($data, $x, $y, 8),
						"virtual_size" => self::GetUInt32($data, $x, $y),
						"rva" => self::GetUInt32($data, $x, $y),
						"raw_data_size" => self::GetUInt32($data, $x, $y),
						"raw_data_ptr" => self::GetUInt32($data, $x, $y),
						"relocations_ptr" => self::GetUInt32($data, $x, $y),  // Should always be zero.
						"line_nums_ptr" => self::GetUInt32($data, $x, $y),  // Should always be zero.
						"num_relocations" => self::GetUInt16($data, $x, $y),  // Should always be zero.
						"num_line_nums" => self::GetUInt16($data, $x, $y),  // Should always be zero.
						"flags" => self::GetUInt32($data, $x, $y),  // Section flags.  See "Section flags" options above.
					);

					if ($options["pe_section_data"])  $section["data"] = (string)substr($data, $section["raw_data_ptr"], $section["raw_data_size"]);
					$section["size"] = ($section["raw_data_ptr"] + $section["raw_data_size"] > $y ? $y - $section["raw_data_ptr"] : $section["raw_data_size"]);

					// Relocations.  Object files only.
					$relocations = array();
					if ($section["num_relocations"])
					{
						$data2 = (string)substr($data, $section["relocations_ptr"], $section["num_relocations"] * 10);
						$x3 = 0;
						$y3 = strlen($data2);
						for ($x4 = 0; $x4 < $section["num_relocations"] && $x3 < $y3; $x4++)
						{
							$relocations[] = array(
								"virtual_addr" => self::GetUInt32($data2, $x3, $y3),
								"sym_table_idx" => self::GetUInt32($data2, $x3, $y3),
								"type" => self::GetUInt16($data2, $x3, $y3),
							);
						}
					}
					$section["relocations"] = $relocations;

					// COFF line numbers.  Deprecated and ignored.

					$this->pe_sections[] = $section;
				}

				// COFF symbol table.  Deprecated and ignored.


				// Extract the exports table.
				if ((isset($options["pe_directories"]["all"]) || isset($options["pe_directories"]["exports"])) && isset($this->pe_data_dir) && $this->pe_data_dir["exports"]["rva"] && $this->pe_data_dir["exports"]["size"])
				{
					$dirinfo = $this->RVAToPos($this->pe_data_dir["exports"]["rva"]);
					if ($dirinfo !== false)
					{
						$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
						$x = $pos + $dirinfo["pos"];
						$y = min($pos + $this->pe_sections[$dirinfo["section"]]["size"], $x + $this->pe_data_dir["exports"]["size"]);

						$exportdir = array(
							"flags" => self::GetUInt32($data, $x, $y),
							"created" => self::GetUInt32($data, $x, $y),
							"major_ver" => self::GetUInt16($data, $x, $y),
							"minor_ver" => self::GetUInt16($data, $x, $y),
							"name_rva" => self::GetUInt32($data, $x, $y),
							"name" => "",
							"ordinal_base" => self::GetUInt32($data, $x, $y),
							"num_addresses" => self::GetUInt32($data, $x, $y),
							"num_name_ptrs" => self::GetUInt32($data, $x, $y),
							"addresses_rva" => self::GetUInt32($data, $x, $y),
							"name_ptr_rva" => self::GetUInt32($data, $x, $y),
							"ordinal_map_rva" => self::GetUInt32($data, $x, $y),
						);

						$exportdir["name"] = $this->GetRVAString($data, $exportdir["name_rva"]);

						// Read the export address table.
						$addresses = array();
						$dirinfo2 = $this->RVAToPos($exportdir["addresses_rva"]);
						if ($dirinfo2 !== false)
						{
							$pos = $this->pe_sections[$dirinfo2["section"]]["raw_data_ptr"];
							$x = $pos + $dirinfo2["pos"];
							$y = $pos + $this->pe_sections[$dirinfo2["section"]]["size"];

							for ($x2 = 0; $x2 < $exportdir["num_addresses"] && $x < $y; $x2++)
							{
								$rva = self::GetUInt32($data, $x, $y);
								$address = array(
									"type" => "export",
									"rva" => $rva
								);

								// Forwarder RVAs must be located within the export table.
								$dirinfo3 = $this->RVAToPos($rva);
								if ($dirinfo3 !== false && $dirinfo3["section"] === $dirinfo["section"] && $dirinfo3["pos"] >= $dirinfo["pos"] && $dirinfo3["pos"] < $dirinfo["pos"] + $this->pe_data_dir["exports"]["size"])
								{
									$name = $this->GetRVAString($data, $rva);
									if ($name !== false)
									{
										$address = array(
											"type" => "forward",
											"name" => $name,
											"rva" => $rva
										);
									}
								}

								$addresses[] = $address;
							}
						}

						// Read the name pointer and ordinal mapping tables and create a unified mapping array.
						$namemap = array();
						$dirinfo2 = $this->RVAToPos($exportdir["name_ptr_rva"]);
						$dirinfo3 = $this->RVAToPos($exportdir["ordinal_map_rva"]);
						if ($dirinfo2 !== false && $dirinfo3 !== false)
						{
							$pos = $this->pe_sections[$dirinfo2["section"]]["raw_data_ptr"];
							$x = $pos + $dirinfo2["pos"];
							$y = $pos + $this->pe_sections[$dirinfo2["section"]]["size"];

							$pos = $this->pe_sections[$dirinfo3["section"]]["raw_data_ptr"];
							$x2 = $pos + $dirinfo3["pos"];
							$y2 = $pos + $this->pe_sections[$dirinfo3["section"]]["size"];

							for ($x3 = 0; $x3 < $exportdir["num_name_ptrs"] && $x < $y; $x3++)
							{
								$rva = self::GetUInt32($data, $x, $y);
								$num = self::GetUInt16($data, $x2, $y2);

								$name = $this->GetRVAString($data, $rva);
								if ($name !== false)  $namemap[$name] = $num;
							}
						}

						$this->pe_data_dir["exports"]["dir"] = $exportdir;
						$this->pe_data_dir["exports"]["addresses"] = $addresses;
						$this->pe_data_dir["exports"]["namemap"] = $namemap;
					}
				}

				// Extract imports table.
				if ((isset($options["pe_directories"]["all"]) || isset($options["pe_directories"]["imports"])) && isset($this->pe_data_dir) && $this->pe_data_dir["imports"]["rva"] && $this->pe_data_dir["imports"]["size"])
				{
					$dirinfo = $this->RVAToPos($this->pe_data_dir["imports"]["rva"]);
					if ($dirinfo !== false)
					{
						$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
						$x = $pos + $dirinfo["pos"];
						$y = min($pos + $this->pe_sections[$dirinfo["section"]]["size"], $x + $this->pe_data_dir["imports"]["size"]);

						$direntries = array();
						do
						{
							$direntry = array(
								"import_names_rva" => self::GetUInt32($data, $x, $y),
								"created" => self::GetUInt32($data, $x, $y),
								"forward_chain" => self::GetUInt32($data, $x, $y),
								"name_rva" => self::GetUInt32($data, $x, $y),
								"name" => "",
								"iat_rva" => self::GetUInt32($data, $x, $y),
							);

							if ($direntry["import_names_rva"] == 0 || $direntry["iat_rva"] == 0)  break;

							$direntry["name"] = $this->GetRVAString($data, $direntry["name_rva"]);

							$imports = array();
							$dirinfo2 = $this->RVAToPos($direntry["import_names_rva"]);
							if ($dirinfo2 !== false)
							{
								$pos = $this->pe_sections[$dirinfo2["section"]]["raw_data_ptr"];
								$x2 = $pos + $dirinfo2["pos"];
								$y2 = $pos + $this->pe_sections[$dirinfo2["section"]]["size"];

								do
								{
									$entry = self::GetBytes($data, $x2, $y2, ($bits64 ? 8 : 4));
									if (($bits64 && $entry === "\x00\x00\x00\x00\x00\x00\x00\x00") || (!$bits64 && $entry === "\x00\x00\x00\x00"))  break;

									// To avoid issues with 32-bit PHP, first extract the last byte, which contains the flag (little endian).
									$flag = ord(substr($entry, -1));
									if ($flag & 0x80)  $imports[] = array("type" => "ord", "ord" => unpack("v", substr($entry, 0, 2))[1]);
									else
									{
										$rva = unpack("V", substr($entry, 0, 4))[1];

										$dirinfo3 = $this->RVAToPos($rva);
										if ($dirinfo3 === false)
										{
											$imports[] = array("type" => "bad_rva", "rva" => $rva);

											break;
										}

										$pos = $this->pe_sections[$dirinfo3["section"]]["raw_data_ptr"];
										$x3 = $pos + $dirinfo3["pos"];
										$y3 = $pos + $this->pe_sections[$dirinfo3["section"]]["size"];

										$hint = self::GetUInt16($data, $x3, $y3);
										$pos = @strpos($data, "\x00", $x3);
										if ($pos === false)
										{
											$imports[] = array("type" => "bad_name", "rva" => $rva, "hint" => $hint);

											break;
										}

										$imports[] = array("type" => "named", "rva" => $rva, "hint" => $hint, "name" => substr($data, $x3, $pos - $x3));
									}
								} while (1);
							}

							$direntry["imports"] = $imports;
							$direntries[] = $direntry;

						} while (1);

						$this->pe_data_dir["imports"]["dir_entries"] = $direntries;
					}
				}

				// Extract the resources table.
				if ((isset($options["pe_directories"]["all"]) || isset($options["pe_directories"]["resources"])) && isset($this->pe_data_dir) && $this->pe_data_dir["resources"]["rva"] && $this->pe_data_dir["resources"]["size"])
				{
					$dirinfo = $this->RVAToPos($this->pe_data_dir["resources"]["rva"]);
					if ($dirinfo !== false)
					{
						$seen = array();
						$direntries = array();

						$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
						$x = $pos + $dirinfo["pos"];
						$y = min($pos + $this->pe_sections[$dirinfo["section"]]["size"], $x + $this->pe_data_dir["resources"]["size"]);

						$base = $x;

						// Used to prevent infinite loops.
						$seen[$x] = true;

						$direntries[] = array(
							"type" => "node",
							"subtype" => "root",
							"parent" => false,
							"pos" => $x,
							"loops" => 0
						);

						$y2 = 1;
						for ($x2 = 0; $x2 < $y2; $x2++)
						{
							$x = $direntries[$x2]["pos"];

							if ($direntries[$x2]["type"] === "node")
							{
								// Lookup the name in the string table.
								if ($direntries[$x2]["subtype"] === "name")
								{
									$x3 = $direntries[$x2]["name"];

									$size = self::GetUInt16($data, $x3, $y);
									$direntries[$x2]["name"] = self::GetBytes($data, $x3, $y, $size * 2);
								}

								// Read the directory info and entries.
								$direntries[$x2]["flags"] = self::GetUInt32($data, $x, $y);
								$direntries[$x2]["created"] = self::GetUInt32($data, $x, $y);
								$direntries[$x2]["major_ver"] = self::GetUInt16($data, $x, $y);
								$direntries[$x2]["minor_ver"] = self::GetUInt16($data, $x, $y);
								$direntries[$x2]["entries"] = array();

								$numleft = self::GetUInt16($data, $x, $y) + self::GetUInt16($data, $x, $y);

								for (; $numleft; $numleft--)
								{
									$nameid = self::GetUInt32($data, $x, $y);
									$offset = self::GetUInt32($data, $x, $y);

									$entry = array(
										"type" => ((int)$offset & 0x80000000 ? "node" : "leaf")
									);

									$nextpos = $base + (int)((int)$offset & 0x7FFFFFFF);

									if (((int)$offset & 0x80000000) && isset($seen[$nextpos]))
									{
										$direntries[0]["loops"]++;

										continue;
									}

									$seen[$nextpos] = true;

									if ((int)$nameid & 0x80000000)
									{
										$entry["subtype"] = "name";
										$entry["name"] = $base + (int)((int)$nameid & 0x7FFFFFFF);
									}
									else
									{
										$entry["subtype"] = "id";
										$entry["id"] = $nameid;
									}

									$entry["parent"] = $x2;
									$entry["pos"] = $nextpos;

									$direntries[$x2]["entries"][] = $y2;
									$direntries[] = $entry;
									$y2++;
								}
							}
							else
							{
								// Read the leaf entry.
								$direntries[$x2]["rva"] = self::GetUInt32($data, $x, $y);
								$direntries[$x2]["size"] = self::GetUInt32($data, $x, $y);
								$direntries[$x2]["code_page"] = self::GetUInt32($data, $x, $y);
								$direntries[$x2]["reserved"] = self::GetUInt32($data, $x, $y);

								if ($options["pe_directory_data"])
								{
									$dirinfo2 = $this->RVAToPos($direntries[$x2]["rva"]);
									if ($dirinfo2 !== false)
									{
										$pos = $this->pe_sections[$dirinfo2["section"]]["raw_data_ptr"];
										$x3 = $pos + $dirinfo2["pos"];
										$y3 = min($pos + $this->pe_sections[$dirinfo2["section"]]["size"], $x3 + $direntries[$x2]["size"]);

										$direntries[$x2]["data"] = self::GetBytes($data, $x3, $y3, $direntries[$x2]["size"]);
									}
								}
							}
						}

						$direntries[0]["nextid"] = $y2;

						$this->pe_data_dir["resources"]["dir_entries"] = $direntries;
					}
				}

				// Skip extracting the exceptions table.  Need an example use-case.

				// Extract the certificates table.
				if ((isset($options["pe_directories"]["all"]) || isset($options["pe_directories"]["certificates"])) && isset($this->pe_data_dir) && $this->pe_data_dir["certificates"]["pos"] && $this->pe_data_dir["certificates"]["size"])
				{
					$certs = array();
					$x = $this->pe_data_dir["certificates"]["pos"];
					$y = min(strlen($data), $x + $this->pe_data_dir["certificates"]["size"]);
					while ($x < $y)
					{
						$cert = array(
							"size" => self::GetUInt32($data, $x, $y),
							"revision" => self::GetUInt16($data, $x, $y),
							"cert_type" => self::GetUInt16($data, $x, $y),
						);

						$cert["data_ptr"] = $x;

						if ($options["pe_directory_data"])  $cert["cert_data"] = self::GetBytes($data, $x, $y, $cert["size"]);
						else  $x += $cert["size"];

						if ($cert["size"] % 8 != 0)  $x += 8 - ($cert["size"] % 8);

						$certs[] = $cert;
					}

					$this->pe_data_dir["certificates"]["certs"] = $certs;
				}

				// Extract the base relocations table.
				if ((isset($options["pe_directories"]["all"]) || isset($options["pe_directories"]["base_relocations"])) && isset($this->pe_data_dir) && $this->pe_data_dir["base_relocations"]["rva"] && $this->pe_data_dir["base_relocations"]["size"])
				{
					$dirinfo = $this->RVAToPos($this->pe_data_dir["base_relocations"]["rva"]);
					if ($dirinfo !== false)
					{
						$blocks = array();

						$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
						$x = $pos + $dirinfo["pos"];
						$y = min($pos + $this->pe_sections[$dirinfo["section"]]["size"], $x + $this->pe_data_dir["base_relocations"]["size"]);

						while ($x < $y)
						{
							$rva = self::GetUInt32($data, $x, $y);
							$size = self::GetUInt32($data, $x, $y) - 8;
							$offsets = array();

							while ($x < $y && $size > 1)
							{
								$x2 = self::GetUInt16($data, $x, $y);
								$size -= 2;

								$offset = array(
									"type" => (($x2 >> 12) & 0x0F),
									"offset" => ($x2 & 0x0FFF)
								);

								if ($offset["type"] === self::IMAGE_REL_BASED_HIGHADJ)
								{
									$offset["extra"] = ($size > 1 ? self::GetUInt16($data, $x, $y) : 0);
									$size -= 2;
								}

								$offsets[] = $offset;
							}

							$blocks[] = array(
								"rva" => $rva,
								"offsets" => $offsets
							);
						}

						$this->pe_data_dir["base_relocations"]["blocks"] = $blocks;
					}
				}

				// Extract the debug directory table.
				if ((isset($options["pe_directories"]["all"]) || isset($options["pe_directories"]["debug"])) && isset($this->pe_data_dir) && $this->pe_data_dir["debug"]["rva"] && $this->pe_data_dir["debug"]["size"])
				{
					$dirinfo = $this->RVAToPos($this->pe_data_dir["debug"]["rva"]);
					if ($dirinfo !== false)
					{
						$direntries = array();

						$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
						$x = $pos + $dirinfo["pos"];
						$y = min($pos + $this->pe_sections[$dirinfo["section"]]["size"], $x + $this->pe_data_dir["debug"]["size"]);

						while ($x < $y)
						{
							$entry = array(
								"flags" => self::GetUInt32($data, $x, $y),
								"created" => self::GetUInt32($data, $x, $y),
								"major_ver" => self::GetUInt16($data, $x, $y),
								"minor_ver" => self::GetUInt16($data, $x, $y),
								"type" => self::GetUInt32($data, $x, $y),
								"size" => self::GetUInt32($data, $x, $y),
								"data_rva" => self::GetUInt32($data, $x, $y),
								"data_ptr" => self::GetUInt32($data, $x, $y),
							);

							if ($options["pe_directory_data"])  $entry["data"] = substr($data, $entry["data_ptr"], $entry["size"]);

							$direntries[] = $entry;
						}

						$this->pe_data_dir["debug"]["dir_entries"] = $direntries;
					}
				}

				// Skip extracting the architecture table.  It's reserved anyway.

				// Skip global pointer.  It's not a table.

				// Extract the Thread Local Storage directory.
				if ((isset($options["pe_directories"]["all"]) || isset($options["pe_directories"]["tls"])) && isset($this->pe_data_dir) && $this->pe_data_dir["tls"]["rva"] && $this->pe_data_dir["tls"]["size"])
				{
					$dirinfo = $this->RVAToPos($this->pe_data_dir["tls"]["rva"]);
					if ($dirinfo !== false)
					{
						$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
						$x = $pos + $dirinfo["pos"];
						$y = min($pos + $this->pe_sections[$dirinfo["section"]]["size"], $x + $this->pe_data_dir["tls"]["size"]);

						$tlsdir = array(
							"data_start_va" => ($bits64 ? self::GetUInt64($data, $x, $y) : self::GetUInt32($data, $x, $y)),
							"data_end_va" => ($bits64 ? self::GetUInt64($data, $x, $y) : self::GetUInt32($data, $x, $y)),
							"index_addr" => ($bits64 ? self::GetUInt64($data, $x, $y) : self::GetUInt32($data, $x, $y)),
							"callbacks_addr" => ($bits64 ? self::GetUInt64($data, $x, $y) : self::GetUInt32($data, $x, $y)),
							"zero_fill_size" => self::GetUInt32($data, $x, $y),
							"flags" => self::GetUInt32($data, $x, $y),
						);

						$this->pe_data_dir["tls"]["dir"] = $tlsdir;
					}
				}

				// Extract the load configuration table.
				if ((isset($options["pe_directories"]["all"]) || isset($options["pe_directories"]["load_config"])) && isset($this->pe_data_dir) && $this->pe_data_dir["load_config"]["rva"] && $this->pe_data_dir["load_config"]["size"])
				{
					$dirinfo = $this->RVAToPos($this->pe_data_dir["load_config"]["rva"]);
					if ($dirinfo !== false)
					{
						$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
						$x = $pos + $dirinfo["pos"];
						$y = min($pos + $this->pe_sections[$dirinfo["section"]]["size"], $x + $this->pe_data_dir["load_config"]["size"]);

						$loadconfigdir = array(
							"flags" => self::GetUInt32($data, $x, $y),
							"timestamp" => self::GetUInt32($data, $x, $y),
							"major_ver" => self::GetUInt16($data, $x, $y),
							"minor_ver" => self::GetUInt16($data, $x, $y),
							"clear_global_flags" => self::GetUInt32($data, $x, $y),
							"set_global_flags" => self::GetUInt32($data, $x, $y),
							"crit_sec_timeout" => self::GetUInt32($data, $x, $y),
							"free_mem_bytes" => self::GetUInt64($data, $x, $y),
							"total_free_mem_bytes" => self::GetUInt64($data, $x, $y),
							"lock_prefix_va" => self::GetUInt64($data, $x, $y),
							"max_alloc_size" => self::GetUInt64($data, $x, $y),
							"max_virt_mem_bytes" => self::GetUInt64($data, $x, $y),
							"proc_affinity_mask" => self::GetUInt64($data, $x, $y),
							"proc_heap_flags" => self::GetUInt32($data, $x, $y),
							"service_pack_ver" => self::GetUInt16($data, $x, $y),
							"reserved" => self::GetUInt16($data, $x, $y),
							"edit_list_reserved" => self::GetUInt64($data, $x, $y),
							"security_cookie" => ($bits64 ? self::GetUInt64($data, $x, $y) : self::GetUInt32($data, $x, $y)),
							"se_handler_va" => ($bits64 ? self::GetUInt64($data, $x, $y) : self::GetUInt32($data, $x, $y)),
							"se_handler_num" => ($bits64 ? self::GetUInt64($data, $x, $y) : self::GetUInt32($data, $x, $y))
						);

						$this->pe_data_dir["load_config"]["dir"] = $loadconfigdir;
					}
				}

				// Extract the bound import table.  Documentation on this table is almost non-existent.
				// In general, it was used by VB6 to optimize application loading.
				if ((isset($options["pe_directories"]["all"]) || isset($options["pe_directories"]["bound_imports"])) && isset($this->pe_data_dir) && $this->pe_data_dir["bound_imports"]["pos"] && $this->pe_data_dir["bound_imports"]["size"])
				{
					$entries = array();

					$x = $this->pe_data_dir["bound_imports"]["pos"];
					$y = min(strlen($data), $x + $this->pe_data_dir["bound_imports"]["size"]);

					$x2 = $x;

					while ($x < $y)
					{
						$entry = array(
							"created" => self::GetUInt32($data, $x, $y),
							"name_offset" => self::GetUInt16($data, $x, $y),
							"name" => "",
							"num_forward_refs" => self::GetUInt16($data, $x, $y),
						);

						if (!$entry["name_offset"] && !$entry["num_forward_refs"])  break;

						$pos = strpos($data, "\x00", $x2 + $entry["name_offset"]);
						if ($pos === false)  $pos = $y;

						$entry["name"] = substr($data, $x2 + $entry["name_offset"], $pos - $x2 - $entry["name_offset"]);

						$entries[] = $entry;
					}

					$this->pe_data_dir["bound_imports"]["dir_entries"] = $entries;
				}

				// Extract the raw Import Address Table (IAT).  It's just a table of addresses that get overwritten by the loader though, so decoding it is basically pointless.
				if ($options["pe_directory_data"] && (isset($options["pe_directories"]["all"]) || isset($options["pe_directories"]["iat"])) && isset($this->pe_data_dir) && $this->pe_data_dir["iat"]["rva"] && $this->pe_data_dir["iat"]["size"])
				{
					$dirinfo = $this->RVAToPos($this->pe_data_dir["iat"]["rva"]);
					if ($dirinfo !== false)
					{
						$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
						$x = $pos + $dirinfo["pos"];
						$y = min($pos + $this->pe_sections[$dirinfo["section"]]["size"], $x + $this->pe_data_dir["iat"]["size"]);

						$this->pe_data_dir["iat"]["data"] = substr($data, $x, $y);
					}
				}

				// Extract delay imports table.
				if ((isset($options["pe_directories"]["all"]) || isset($options["pe_directories"]["delay_imports"])) && isset($this->pe_data_dir) && $this->pe_data_dir["delay_imports"]["rva"] && $this->pe_data_dir["delay_imports"]["size"])
				{
					$dirinfo = $this->RVAToPos($this->pe_data_dir["delay_imports"]["rva"]);
					if ($dirinfo !== false)
					{
						$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
						$x = $pos + $dirinfo["pos"];
						$y = min($pos + $this->pe_sections[$dirinfo["section"]]["size"], $x + $this->pe_data_dir["delay_imports"]["size"]);

						$direntries = array();
						do
						{
							$direntry = array(
								"flags" => self::GetUInt32($data, $x, $y),
								"name_rva" => self::GetUInt32($data, $x, $y),
								"name" => "",
								"hmodule_rva" => self::GetUInt32($data, $x, $y),
								"delay_iat_rva" => self::GetUInt32($data, $x, $y),
								"import_names_rva" => self::GetUInt32($data, $x, $y),
								"bound_iat_rva" => self::GetUInt32($data, $x, $y),
								"unload_iat_rva" => self::GetUInt32($data, $x, $y),
								"created" => self::GetUInt32($data, $x, $y),
							);

							if ($direntry["import_names_rva"] == 0 || $direntry["name_rva"] == 0)  break;

							$direntry["name"] = $this->GetRVAString($data, $direntry["name_rva"]);

							$imports = array();
							$dirinfo2 = $this->RVAToPos($direntry["import_names_rva"]);
							if ($dirinfo2 !== false)
							{
								$pos = $this->pe_sections[$dirinfo2["section"]]["raw_data_ptr"];
								$x2 = $pos + $dirinfo2["pos"];
								$y2 = $pos + $this->pe_sections[$dirinfo2["section"]]["size"];

								do
								{
									$entry = self::GetBytes($data, $x2, $y2, ($bits64 ? 8 : 4));
									if (($bits64 && $entry === "\x00\x00\x00\x00\x00\x00\x00\x00") || (!$bits64 && $entry === "\x00\x00\x00\x00"))  break;

									// To avoid issues with 32-bit PHP, first extract the last byte, which contains the flag (little endian).
									$flag = ord(substr($entry, -1));
									if ($flag & 0x80)  $imports[] = array("type" => "ord", "ord" => unpack("v", substr($entry, 0, 2))[1]);
									else
									{
										$rva = unpack("V", substr($entry, 0, 4))[1];

										$dirinfo3 = $this->RVAToPos($rva);
										if ($dirinfo3 === false)
										{
											$imports[] = array("type" => "bad_rva", "rva" => $rva);

											break;
										}

										$pos = $this->pe_sections[$dirinfo3["section"]]["raw_data_ptr"];
										$x3 = $pos + $dirinfo3["pos"];
										$y3 = $pos + $this->pe_sections[$dirinfo3["section"]]["size"];

										$hint = self::GetUInt16($data, $x3, $y3);
										$pos = @strpos($data, "\x00", $x3);
										if ($pos === false)
										{
											$imports[] = array("type" => "bad_name", "rva" => $rva, "hint" => $hint);

											break;
										}

										$imports[] = array("type" => "named", "hint" => $hint, "name" => substr($data, $x3, $pos - $x3));
									}
								} while (1);
							}

							$direntry["imports"] = $imports;
							$direntries[] = $direntry;

						} while (1);

						$this->pe_data_dir["delay_imports"]["dir_entries"] = $direntries;
					}
				}

				// Extract the .NET CLR Runtime Header.
				if ($options["pe_directory_data"] && (isset($options["pe_directories"]["all"]) || isset($options["pe_directories"]["clr_runtime_header"])) && isset($this->pe_data_dir) && $this->pe_data_dir["clr_runtime_header"]["rva"] && $this->pe_data_dir["clr_runtime_header"]["size"])
				{
					$dirinfo = $this->RVAToPos($this->pe_data_dir["clr_runtime_header"]["rva"]);
					if ($dirinfo !== false)
					{
						$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
						$x = $pos + $dirinfo["pos"];
						$y = min($pos + $this->pe_sections[$dirinfo["section"]]["size"], $x + $this->pe_data_dir["clr_runtime_header"]["size"]);

						$this->pe_data_dir["clr_runtime_header"]["data"] = substr($data, $x, $y);
					}
				}
			}

			return array("success" => true);
		}

		public function InitResourcesDir()
		{
			if (!isset($this->pe_data_dir["resources"]["dir_entries"]))
			{
				$this->pe_data_dir["resources"]["dir_entries"] = array(
					array(
						"type" => "node",
						"subtype" => "root",
						"parent" => false,
						"pos" => 0,
						"loops" => 0,
						"nextid" => 1,
						"entries" => array()
					)
				);
			}
		}

		public function CreateResourceTypeNode($type)
		{
			$this->InitResourcesDir();

			// Find a matching type node.
			foreach ($this->pe_data_dir["resources"]["dir_entries"][0]["entries"] as $num)
			{
				$entry = &$this->pe_data_dir["resources"]["dir_entries"][$num];

				if ((isset($entry["id"]) && $entry["id"] === $type) || (isset($entry["name"]) && $entry["name"] === $type))  return $num;
			}

			// Create the type node.
			$num = $this->pe_data_dir["resources"]["dir_entries"][0]["nextid"];

			$this->pe_data_dir["resources"]["dir_entries"][$num] = array(
				"type" => "node",
				"subtype" => (is_string($type) ? "name" : "id"),
				(is_string($type) ? "name" : "id") => (is_string($type) ? $type : (int)$type),
				"parent" => 0,
				"pos" => 0,
				"flags" => 0,
				"created" => 0,
				"major_ver" => 0,
				"minor_ver" => 0,
				"entries" => array()
			);

			$this->pe_data_dir["resources"]["dir_entries"][0]["nextid"]++;

			return $num;
		}

		public function CreateResourceIDNameNode($type, $idname)
		{
			$parentnum = $this->CreateResourceTypeNode($type);

			if ($idname === true)
			{
				// Find an unused ID.
				$ids = array();
				foreach ($this->pe_data_dir["resources"]["dir_entries"][$parentnum]["entries"] as $num)
				{
					$entry = &$this->pe_data_dir["resources"]["dir_entries"][$num];

					if (isset($entry["id"]))  $ids[$entry["id"]] = true;
				}

				for ($idname = 1; isset($ids[$idname]); $idname++)
				{
				}
			}
			else
			{
				// Attempt to match the ID/name.
				foreach ($this->pe_data_dir["resources"]["dir_entries"][$parentnum]["entries"] as $num)
				{
					$entry = &$this->pe_data_dir["resources"]["dir_entries"][$num];

					if ((isset($entry["id"]) && $entry["id"] === $idname) || (isset($entry["name"]) && $entry["name"] === $idname))  return $num;
				}
			}

			// Create the ID/name node.
			$num = $this->pe_data_dir["resources"]["dir_entries"][0]["nextid"];

			$this->pe_data_dir["resources"]["dir_entries"][$parentnum]["entries"][] = $num;

			$this->pe_data_dir["resources"]["dir_entries"][$num] = array(
				"type" => "node",
				"subtype" => (is_string($idname) ? "name" : "id"),
				(is_string($idname) ? "name" : "id") => (is_string($idname) ? $idname : (int)$idname),
				"parent" => $parentnum,
				"pos" => 0,
				"flags" => 0,
				"created" => 0,
				"major_ver" => 0,
				"minor_ver" => 0,
				"entries" => array()
			);

			$this->pe_data_dir["resources"]["dir_entries"][0]["nextid"]++;

			return $num;
		}

		public function CreateResourceLangNode($type, $idname, $lang, $data = false)
		{
			$parentnum = $this->CreateResourceIDNameNode($type, $idname);

			// Attempt to auto-detect the language based on other resources.
			if ($lang === true)
			{
				$ids = array();
				foreach ($this->pe_data_dir["resources"]["dir_entries"] as $num => &$entry)
				{
					if ($entry["type"] === "leaf" && isset($entry["id"]))
					{
						if (!isset($ids[$entry["id"]]))  $ids[$entry["id"]] = 0;

						$ids[$entry["id"]]++;
					}
				}

				if (!count($ids))  $lang = 0;
				else
				{
					arsort($ids);

					foreach ($ids as $id => $val)
					{
						$lang = $id;

						break;
					}
				}
			}

			// Attempt to match the language.
			foreach ($this->pe_data_dir["resources"]["dir_entries"][$parentnum]["entries"] as $num)
			{
				$entry = &$this->pe_data_dir["resources"]["dir_entries"][$num];

				if ((isset($entry["id"]) && $entry["id"] === $lang) || (isset($entry["name"]) && $entry["name"] === $lang))  return $num;
			}

			// Create the language leaf.
			$num = $this->pe_data_dir["resources"]["dir_entries"][0]["nextid"];

			$this->pe_data_dir["resources"]["dir_entries"][$parentnum]["entries"][] = $num;

			$this->pe_data_dir["resources"]["dir_entries"][$num] = array(
				"type" => "leaf",
				"subtype" => (is_string($lang) ? "name" : "id"),
				(is_string($lang) ? "name" : "id") => (is_string($lang) ? $lang : (int)$lang),
				"parent" => $parentnum,
				"pos" => 0,
				"rva" => 0,
				"size" => 0,
				"code_page" => 0,
				"reserved" => 0
			);

			if ($data !== false)  $this->pe_data_dir["resources"]["dir_entries"][$num]["data"] = $data;

			$this->pe_data_dir["resources"]["dir_entries"][0]["nextid"]++;

			return $num;
		}

		public function GetResource($num)
		{
			return (isset($this->pe_data_dir["resources"]["dir_entries"][$num]) ? $this->pe_data_dir["resources"]["dir_entries"][$num] : false);
		}

		public function FindResources($type, $idname, $lang, $limit = false)
		{
			$result = array();

			if (isset($this->pe_data_dir["resources"]["dir_entries"]))
			{
				foreach ($this->pe_data_dir["resources"]["dir_entries"] as $num => &$entry)
				{
					if ($entry["type"] === "leaf")
					{
						// Match language.
						if ($entry["parent"] != 0 && ($lang === true || (isset($entry["id"]) && $entry["id"] === $lang) || (isset($entry["name"]) && $entry["name"] === $lang)))
						{
							$entry2 = &$this->pe_data_dir["resources"]["dir_entries"][$entry["parent"]];

							// Match ID/name.
							if ($entry2["parent"] != 0 && ($idname === true || (isset($entry2["id"]) && $entry2["id"] === $idname) || (isset($entry2["name"]) && $entry2["name"] === $idname)))
							{
								$entry2 = &$this->pe_data_dir["resources"]["dir_entries"][$entry2["parent"]];

								// Match type.
								if ($entry2["parent"] == 0 && ($type === true || (isset($entry2["id"]) && $entry2["id"] === $type) || (isset($entry2["name"]) && $entry2["name"] === $type)))
								{
									$result[] = array("num" => $num, "entry" => $entry);

									if ($limit !== false && $limit >= count($result))  break;
								}
							}
						}
					}
				}
			}

			return $result;
		}

		public function FindResource($type, $idname, $lang)
		{
			$result = $this->FindResources($type, $idname, $lang, 1);

			return (count($result) ? $result[0] : false);
		}

		public function DeleteResource($num)
		{
			if (isset($this->pe_data_dir["resources"]["dir_entries"][$num]) && $this->pe_data_dir["resources"]["dir_entries"][$num]["type"] === "leaf")
			{
				$parentnum = $this->pe_data_dir["resources"]["dir_entries"][$num]["parent"];

				unset($this->pe_data_dir["resources"]["dir_entries"][$num]);

				// Clean up parent nodes.
				do
				{
					$entry = &$this->pe_data_dir["resources"]["dir_entries"][$parentnum];

					foreach ($entry["entries"] as $pos => $num2)
					{
						if ($num === $num2)  unset($entry["entries"][$pos]);
					}

					if (count($entry["entries"]) || !$parentnum)  break;

					$num = $parentnum;
					$parentnum = $entry["parent"];

					unset($entry);
					unset($this->pe_data_dir["resources"]["dir_entries"][$num]);
				} while (1);
			}
		}

		public function DeleteResources($type, $idname, $lang, $limit = false)
		{
			$result = $this->FindResources($type, $idname, $lang, $limit);

			foreach ($result as $info)
			{
				$this->DeleteResource($info["num"]);
			}
		}

		public function GetExclusiveResourceRVARefAndZero(&$data, $num)
		{
			if (!isset($this->pe_data_dir["resources"]["dir_entries"][$num]))  return false;

			$entry = &$this->pe_data_dir["resources"]["dir_entries"][$num];

			if ($entry["type"] !== "leaf" || !$entry["rva"])  return false;

			// Verify RVA.  Delete resource if the RVA reference is bad.
			$dirinfo = $this->RVAToPos($entry["rva"]);
			if ($dirinfo === false)
			{
				if ($entry["size"])  $this->DeleteResource($result2["num"]);

				return false;
			}

			$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
			$x = $pos + $dirinfo["pos"];
			$y = min($pos + $this->pe_sections[$dirinfo["section"]]["size"], $x + $entry["size"]);
			$size = $y - $x;

			foreach ($this->pe_data_dir["resources"]["dir_entries"] as $num2 => &$entry2)
			{
				if ($num === $num2)  continue;

				if ($entry2["type"] === "leaf" && (($entry["rva"] <= $entry2["rva"] && $entry["rva"] + $size > $entry2["rva"]) || ($entry["rva"] < $entry2["rva"] + $entry2["size"] && $entry["rva"] + $size >= $entry2["rva"] + $entry2["size"])))
				{
					$entry["rva"] = 0;
				}
			}

			$x2 = $x;
			self::SetBytes($data, $x2, "", $size);

			return array("pos" => $x, "size" => $size);
		}

		public function OverwriteResourceData(&$data, $num, $newdata)
		{
			if (!isset($this->pe_data_dir["resources"]["dir_entries"][$num]))  return false;

			$y = strlen($newdata);
			$entry = &$this->pe_data_dir["resources"]["dir_entries"][$num];

			if ($entry["type"] !== "leaf")  return false;

			$result = $this->GetExclusiveResourceRVARefAndZero($data, $num);
			if ($result !== false && $y <= $result["size"])
			{
				self::SetBytes($data, $result["pos"], $newdata, $result["size"]);

				$entry["size"] = $y;
				$entry["data"] = $newdata;
			}
			else
			{
				$entry["rva"] = 0;
				$entry["size"] = 0;
				$entry["data"] = $newdata;
			}

			return true;
		}

		public function UpdateChecksum(&$data, $zeroall = false)
		{
			// MS-DOS checksum calculation.  Note that these can't be calculated/updated correctly when any other checksum is involved.
			// Note also that almost nothing ever validated the checksum, including MS-DOS.  So maybe this doesn't matter beyond the principle of storing the correct value.
			// Source:  https://jeffpar.github.io/kbarchive/kb/071/Q71971/
			// Source:  http://mathforum.org/library/drmath/view/54379.html
			if ($zeroall || isset($this->ne_header) || isset($this->pe_header))  $this->dos_header["checksum"] = 0;
			else
			{
				$x = 0;
				$y = strlen($data);
				$val = 0;
				while ($x < $y)
				{
					// Skip the checksum field location.
					if ($x === 0x12)  $x += 2;
					else
					{
						$val += self::GetUInt16($data, $x, $y);
						if ($val > 0xFFFF)  $val = ($val & 0xFFFF) + 1;
					}
				}

				// 1's complement (aka bit-flip the value).
				$val ^= 0xFFFF;

				// Update header.
				$this->dos_header["checksum"] = $val;
			}

			// Update data.
			$x = 0x12;
			self::SetUInt16($data, $x, $this->dos_header["checksum"]);

			// Win16 NE header checksum calculation.  Knowledge of how to calculate this was difficult to come by.
			// Actually ran into it completely by accident when looking for MS-DOS checksum calculation above.
			// Source:  https://jeffpar.github.io/kbarchive/kb/071/Q71971/
			if (isset($this->ne_header) && $this->dos_header["pe_offset_valid"])
			{
				$skippos = $this->dos_header["pe_offset"] + 8;

				if ($zeroall)  $this->ne_header["checksum"] = 0;
				else
				{
					$x = 0;
					$y = ($this->dos_header["bytes_last_page"] ? ($this->dos_header["pages"] - 1) * 512 + $this->dos_header["bytes_last_page"] : $this->dos_header["pages"] * 512);
					$val = 0;
					while ($x < $y)
					{
						// Skip the checksum field location.
						if ($x === $skippos)  $x += 4;
						else
						{
							// Read in two 16-bit values to do the calculation (avoids issues on 32-bit PHP).
							$val += self::GetUInt16($data, $x, $y) + (self::GetUInt16($data, $x, $y) * 0x10000);

							// Emulate 1's complement wrapping regardless of PHP version.
							if ($val > 0xFFFFFFFF)  $val = $val - 0x100000000 + 1;
						}
					}

					// Update header.
					$this->ne_header["checksum"] = (int)$val;
				}

				// Update data.
				self::SetUInt32($data, $skippos, $this->ne_header["checksum"]);
			}

			// PE header checksum calculation.
			// Want to know what's harder to find than Win16 NE header checksum calculation logic?  Finding *sane* Win32 PE header checksum calculation logic.
			// Fun fact:  The PE checksum algorithm is quite similar to the MS-DOS checksum algorithm.
			if (isset($this->pe_opt_header) && $this->dos_header["pe_offset_valid"])
			{
				if ($zeroall)  $this->pe_opt_header["checksum"] = 0;
				else
				{
					$x = 0;
					$y = strlen($data);
					$val = 0;
					while ($x < $y)
					{
						// Skip the checksum field location.
						if ($x === $this->pe_opt_header["checksum_pos"])  $x += 4;
						else
						{
							$val += self::GetUInt16($data, $x, $y);
							if ($val > 0xFFFF)  $val = ($val & 0xFFFF) + 1;
						}
					}

					// Add the file size.
					$val += $y;

					// Update header.
					$this->pe_opt_header["checksum"] = (int)$val;
				}

				// Update data.
				$x = $this->pe_opt_header["checksum_pos"];
				self::SetUInt32($data, $x, $this->pe_opt_header["checksum"]);
			}
		}

		public function SaveHeaders(&$data)
		{
			// Write the DOS header.
			$x = 0;
			self::SetBytes($data, $x, $this->dos_header["signature"], 2);
			self::SetUInt16($data, $x, $this->dos_header["bytes_last_page"]);
			self::SetUInt16($data, $x, $this->dos_header["pages"]);
			self::SetUInt16($data, $x, $this->dos_header["relocations"]);
			self::SetUInt16($data, $x, $this->dos_header["header_size"]);
			self::SetUInt16($data, $x, $this->dos_header["min_memory"]);
			self::SetUInt16($data, $x, $this->dos_header["max_memory"]);
			self::SetUInt16($data, $x, $this->dos_header["initial_ss"]);
			self::SetUInt16($data, $x, $this->dos_header["initial_sp"]);
			self::SetUInt16($data, $x, $this->dos_header["checksum"]);
			self::SetUInt16($data, $x, $this->dos_header["initial_ip"]);
			self::SetUInt16($data, $x, $this->dos_header["initial_cs"]);
			self::SetUInt16($data, $x, $this->dos_header["reloc_offset"]);
			self::SetUInt16($data, $x, $this->dos_header["overlay_num"]);
			self::SetBytes($data, $x, $this->dos_header["reserved_1"], 8);
			self::SetUInt16($data, $x, $this->dos_header["oem_identifier"]);
			self::SetUInt16($data, $x, $this->dos_header["oem_info"]);
			self::SetBytes($data, $x, $this->dos_header["reserved_2"], 20);
			self::SetUInt32($data, $x, $this->dos_header["pe_offset"]);

			// Write the DOS stub.
			self::SetBytes($data, $x, $this->dos_stub, strlen($this->dos_stub));

			// Pad as necessary.
			if ($x < $this->dos_header["pe_offset"])  self::SetBytes($data, $x, "", $this->dos_header["pe_offset"] - $x);

			$x = $this->dos_header["pe_offset"];

			if (isset($this->ne_header))
			{
				self::SetBytes($data, $x, $this->ne_header["signature"], 2);
				self::SetUInt8($data, $x, $this->ne_header["major_ver"]);
				self::SetUInt8($data, $x, $this->ne_header["minor_ver"]);
				self::SetUInt16($data, $x, $this->ne_header["entry_table_offset"]);
				self::SetUInt16($data, $x, $this->ne_header["entry_table_length"]);
				self::SetUInt32($data, $x, $this->ne_header["checksum"]);
				self::SetUInt8($data, $x, $this->ne_header["program_flags"]);
				self::SetUInt8($data, $x, $this->ne_header["app_flags"]);
				self::SetUInt16($data, $x, $this->ne_header["auto_ds_index"]);
				self::SetUInt16($data, $x, $this->ne_header["init_heap_size"]);
				self::SetUInt16($data, $x, $this->ne_header["init_stack_size"]);
				self::SetUInt32($data, $x, $this->ne_header["entry_point_cs_ip"]);
				self::SetUInt32($data, $x, $this->ne_header["init_stack_ss_sp"]);
				self::SetUInt16($data, $x, $this->ne_header["num_segments"]);
				self::SetUInt16($data, $x, $this->ne_header["num_dll_refs"]);
				self::SetUInt16($data, $x, $this->ne_header["non_resident_names_table_size"]);
				self::SetUInt16($data, $x, $this->ne_header["segment_table_offset"]);
				self::SetUInt16($data, $x, $this->ne_header["resources_table_offset"]);
				self::SetUInt16($data, $x, $this->ne_header["resident_names_table_offset"]);
				self::SetUInt16($data, $x, $this->ne_header["dll_refs_offset"]);
				self::SetUInt16($data, $x, $this->ne_header["import_names_table_offset"]);
				self::SetUInt32($data, $x, $this->ne_header["non_resident_names_table_offset"]);
				self::SetUInt16($data, $x, $this->ne_header["num_moveable_entry_points"]);
				self::SetUInt16($data, $x, $this->ne_header["file_align_size_shift_count"]);
				self::SetUInt16($data, $x, $this->ne_header["num_resources"]);
				self::SetUInt8($data, $x, $this->ne_header["target_os"]);
				self::SetUInt8($data, $x, $this->ne_header["os2_exe_flags"]);
				self::SetUInt16($data, $x, $this->ne_header["return_thunks_offset"]);
				self::SetUInt16($data, $x, $this->ne_header["segment_ref_thunks_offset"]);
				self::SetUInt16($data, $x, $this->ne_header["min_code_swap_size"]);
				self::SetUInt8($data, $x, $this->ne_header["expected_win_ver_minor"]);
				self::SetUInt8($data, $x, $this->ne_header["expected_win_ver_major"]);
			}
			else if (isset($this->pe_header))
			{
				self::SetBytes($data, $x, $this->pe_header["signature"], 4);
				self::SetUInt16($data, $x, $this->pe_header["machine_type"]);
				self::SetUInt16($data, $x, $this->pe_header["num_sections"]);
				self::SetUInt32($data, $x, $this->pe_header["created"]);
				self::SetUInt32($data, $x, $this->pe_header["symbol_table_ptr"]);
				self::SetUInt32($data, $x, $this->pe_header["num_symbols"]);
				self::SetUInt16($data, $x, $this->pe_header["optional_header_size"]);
				self::SetUInt16($data, $x, $this->pe_header["flags"]);

				if (isset($this->pe_opt_header))
				{
					$x2 = $x + $this->pe_header["optional_header_size"];

					self::SetUInt16($data, $x, $this->pe_opt_header["signature"]);
					$bits64 = ($this->pe_opt_header["signature"] === self::OPT_HEADER_SIGNATURE_PE32_PLUS);

					self::SetUInt8($data, $x, $this->pe_opt_header["major_linker_ver"]);
					self::SetUInt8($data, $x, $this->pe_opt_header["minor_linker_ver"]);
					self::SetUInt32($data, $x, $this->pe_opt_header["code_size"]);
					self::SetUInt32($data, $x, $this->pe_opt_header["initialized_data_size"]);
					self::SetUInt32($data, $x, $this->pe_opt_header["uninitialized_data_size"]);
					self::SetUInt32($data, $x, $this->pe_opt_header["entry_point_addr"]);
					self::SetUInt32($data, $x, $this->pe_opt_header["code_base"]);
					if (!$bits64)  self::SetUInt32($data, $x, $this->pe_opt_header["data_base"]);

					if ($bits64)  self::SetUInt64($data, $x, $this->pe_opt_header["image_base"]);
					else  self::SetUInt32($data, $x, $this->pe_opt_header["image_base"]);

					self::SetUInt32($data, $x, $this->pe_opt_header["section_alignment"]);
					self::SetUInt32($data, $x, $this->pe_opt_header["file_alignment"]);
					self::SetUInt16($data, $x, $this->pe_opt_header["major_os_ver"]);
					self::SetUInt16($data, $x, $this->pe_opt_header["minor_os_ver"]);
					self::SetUInt16($data, $x, $this->pe_opt_header["major_image_ver"]);
					self::SetUInt16($data, $x, $this->pe_opt_header["minor_image_ver"]);
					self::SetUInt16($data, $x, $this->pe_opt_header["major_subsystem_ver"]);
					self::SetUInt16($data, $x, $this->pe_opt_header["minor_subsystem_ver"]);
					self::SetUInt32($data, $x, $this->pe_opt_header["win32_version"]);
					self::SetUInt32($data, $x, $this->pe_opt_header["image_size"]);
					self::SetUInt32($data, $x, $this->pe_opt_header["headers_size"]);
					$this->pe_opt_header["checksum_pos"] = $x;
					self::SetUInt32($data, $x, $this->pe_opt_header["checksum"]);
					self::SetUInt16($data, $x, $this->pe_opt_header["subsystem"]);
					self::SetUInt16($data, $x, $this->pe_opt_header["dll_characteristics"]);

					if ($bits64)  self::SetUInt64($data, $x, $this->pe_opt_header["stack_reserve_size"]);
					else  self::SetUInt32($data, $x, $this->pe_opt_header["stack_reserve_size"]);

					if ($bits64)  self::SetUInt64($data, $x, $this->pe_opt_header["stack_commit_size"]);
					else  self::SetUInt32($data, $x, $this->pe_opt_header["stack_commit_size"]);

					if ($bits64)  self::SetUInt64($data, $x, $this->pe_opt_header["heap_reserve_size"]);
					else  self::SetUInt32($data, $x, $this->pe_opt_header["heap_reserve_size"]);

					if ($bits64)  self::SetUInt64($data, $x, $this->pe_opt_header["heap_commit_size"]);
					else  self::SetUInt32($data, $x, $this->pe_opt_header["heap_commit_size"]);

					self::SetUInt32($data, $x, $this->pe_opt_header["loader_flags"]);
					self::SetUInt32($data, $x, $this->pe_opt_header["num_data_directories"]);

					$this->pe_opt_header["data_directories_pos"] = $x;

					$num = 0;
					foreach ($this->pe_data_dir as $key => &$dinfo)
					{
						if ($num >= $this->pe_opt_header["num_data_directories"])  break;

						self::SetUInt32($data, $x, (isset($dinfo["rva"]) ? $dinfo["rva"] : $dinfo["pos"]));
						self::SetUInt32($data, $x, $dinfo["size"]);

						$num++;
					}

					if ($x < $x2)  self::SetBytes($data, $x, "", $x2 - $x);
					$x = $x2;

					if (strlen($data) < $this->pe_opt_header["headers_size"])  self::SetBytes($data, $x2, "", $this->pe_opt_header["headers_size"] - $x2);
				}

				foreach ($this->pe_sections as &$sinfo)
				{
					self::SetBytes($data, $x, $sinfo["name"], 8);
					self::SetUInt32($data, $x, $sinfo["virtual_size"]);
					self::SetUInt32($data, $x, $sinfo["rva"]);
					self::SetUInt32($data, $x, $sinfo["raw_data_size"]);
					self::SetUInt32($data, $x, $sinfo["raw_data_ptr"]);
					self::SetUInt32($data, $x, $sinfo["relocations_ptr"]);
					self::SetUInt32($data, $x, $sinfo["line_nums_ptr"]);
					self::SetUInt16($data, $x, $sinfo["num_relocations"]);
					self::SetUInt16($data, $x, $sinfo["num_line_nums"]);
					self::SetUInt32($data, $x, $sinfo["flags"]);
				}
			}
		}

		public function GetRealImageHeadersSize($sections = true)
		{
			return $this->dos_header["pe_offset"] + 24 + $this->pe_header["optional_header_size"] + ($sections ? $this->pe_header["num_sections"] * 40 : 0);
		}

		public static function AlignValue($val, $alignment)
		{
			return (int)(($val + $alignment - 1) / $alignment) * $alignment;
		}

		public function SectionAlignValue($val)
		{
			$alignment = $this->pe_opt_header["section_alignment"];

			return (int)(($val + $alignment - 1) / $alignment) * $alignment;
		}

		public function FileAlignValue($val)
		{
			$alignment = $this->pe_opt_header["file_alignment"];

			return (int)(($val + $alignment - 1) / $alignment) * $alignment;
		}

		public function PrepareForNewPESection(&$data, $numbytes = 40)
		{
			if (!isset($this->pe_opt_header))  return array("success" => false, "error" => "Not a suitable PE file.  The PE optional header is missing.", "errorcode" => "missing_pe_opt_header");

			// Find the first available position.
			$firstptr = $this->pe_opt_header["headers_size"];
			foreach ($this->pe_sections as &$sinfo)
			{
				if ($sinfo["raw_data_ptr"] > 0 && $firstptr > $sinfo["raw_data_ptr"])  $firstptr = $sinfo["raw_data_ptr"];

				if ($sinfo["relocations_ptr"] > 0 && $firstptr > $sinfo["relocations_ptr"] && $sinfo["num_relocations"] > 0)  $firstptr = $sinfo["relocations_ptr"];
			}

			// Look at data directories and items that can be outside sections.
			if ($this->pe_data_dir["certificates"]["pos"] > 0 && $firstptr > $this->pe_data_dir["certificates"]["pos"] && $this->pe_data_dir["certificates"]["size"] > 0)  $firstptr = $this->pe_data_dir["certificates"]["pos"];
			if ($this->pe_data_dir["bound_imports"]["pos"] > 0 && $firstptr > $this->pe_data_dir["bound_imports"]["pos"] && $this->pe_data_dir["bound_imports"]["size"] > 0)  $firstptr = $this->pe_data_dir["bound_imports"]["pos"];

			$removedebug = false;
			if (isset($this->pe_data_dir["debug"]["dir_entries"]))
			{
				foreach ($this->pe_data_dir["debug"]["dir_entries"] as $entry)
				{
					if ($entry["data_ptr"] > 0 && $firstptr > $entry["data_ptr"])
					{
						$firstptr = $entry["data_ptr"];

						$removedebug = true;
					}
				}
			}

			$lastpos = $this->GetRealImageHeadersSize();
			$bytesavail = $firstptr - $lastpos;

			// If there aren't enough bytes available, it's time to do drastic things.
			if ($bytesavail < $numbytes)
			{
				// Shrink the DOS stub if possible.
				if (strlen($this->dos_stub) > strlen(self::$defaultDOSstub))
				{
					$this->dos_header = self::GetDefaultDOSHeader();
					$this->dos_stub = self::$defaultDOSstub;

					$tempheader = self::GetDefaultPEOptHeader($this->pe_opt_header["signature"]);
					$this->pe_opt_header["checksum_pos"] = $tempheader["checksum_pos"];
					$this->pe_opt_header["data_directories_pos"] = $tempheader["data_directories_pos"];
				}

				// Remove junk from the header to free up space.
				foreach ($this->pe_sections as &$sinfo)
				{
					if ($sinfo["relocations_ptr"] > 0 && $sinfo["relocations_ptr"] < $this->pe_opt_header["headers_size"] && $sinfo["num_relocations"] > 0)
					{
						$sinfo["relocations_ptr"] = 0;
						$sinfo["num_relocations"] = 0;
					}
				}

				if ($this->pe_data_dir["certificates"]["pos"] > 0 && $this->pe_data_dir["certificates"]["pos"] < $this->pe_opt_header["headers_size"] && $this->pe_data_dir["certificates"]["size"] > 0)  $this->ClearCertificates($data);
				if ($this->pe_data_dir["bound_imports"]["pos"] > 0 && $this->pe_data_dir["bound_imports"]["pos"] < $this->pe_opt_header["headers_size"] && $this->pe_data_dir["bound_imports"]["size"] > 0)  $this->ClearBoundImports($data);

				if ($removedebug)  $this->ClearDebugDirectory($data);

				// Enlarge the header to its section alignment (i.e. make it as big as possible).
				$newsize = $this->SectionAlignValue($this->pe_opt_header["headers_size"]);
				if ($newsize > $this->pe_opt_header["headers_size"])
				{
					$injectbytes = $newsize - $this->pe_opt_header["headers_size"];
					$data = substr($data, 0, $this->pe_opt_header["headers_size"]) . str_repeat("\x00", $injectbytes) . substr($data, $this->pe_opt_header["headers_size"]);

					// Update the header size.
					$this->pe_opt_header["headers_size"] = $newsize;

					// Zero out the header space.  It'll get saved in just a moment but there might be cruft floating around.
					$x = 0;
					self::SetBytes($data, $x, "", $this->pe_opt_header["headers_size"]);

					// Update section pointers.
					foreach ($this->pe_sections as &$sinfo)
					{
						if ($sinfo["raw_data_ptr"] > 0)  $sinfo["raw_data_ptr"] += $injectbytes;

						if ($sinfo["relocations_ptr"] > 0 && $sinfo["num_relocations"] > 0)  $sinfo["relocations_ptr"] += $injectbytes;
					}

					// Adjust data directories and items that moved.
					if ($this->pe_data_dir["certificates"]["pos"] > 0 && $this->pe_data_dir["certificates"]["size"] > 0)  $this->pe_data_dir["certificates"]["pos"] += $injectbytes;
					if ($this->pe_data_dir["bound_imports"]["pos"] > 0 && $this->pe_data_dir["bound_imports"]["size"] > 0)  $this->pe_data_dir["bound_imports"]["pos"] += $injectbytes;

					if (isset($this->pe_data_dir["debug"]["dir_entries"]))
					{
						$dirinfo = $this->RVAToPos($this->pe_data_dir["debug"]["rva"]);
						if ($dirinfo !== false)
						{
							$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
							$x = $pos + $dirinfo["pos"];
							$y = min($pos + $this->pe_sections[$dirinfo["section"]]["size"], $x + $this->pe_data_dir["debug"]["size"]);

							foreach ($this->pe_data_dir["debug"]["dir_entries"] as &$entry)
							{
								$entry["data_ptr"] += $injectbytes;

								$x += 24;
								self::SetUInt32($data, $x, $entry["data_ptr"]);
							}
						}
					}

					// Save the updated headers.
					$this->SaveHeaders($data);
				}

				// Recalculate available space.
				$lastpos = $this->GetRealImageHeadersSize();
				$bytesavail = $this->pe_opt_header["headers_size"] - $lastpos;

				if ($bytesavail < $numbytes)  return array("success" => false, "error" => "Unable to reserve %u bytes in the PE header.  PE header is full.", "errorcode" => "pe_opt_header_full");
			}

			return array("success" => true);
		}

		public function UpdatePEOptHeaderSizes()
		{
			if (!isset($this->pe_opt_header))  return array("success" => false, "error" => "Not a suitable PE file.  The PE optional header is missing.", "errorcode" => "missing_pe_opt_header");

			$this->pe_opt_header["code_size"] = 0;
			$this->pe_opt_header["initialized_data_size"] = 0;
			$this->pe_opt_header["uninitialized_data_size"] = 0;
			$this->pe_opt_header["image_size"] = $this->SectionAlignValue($this->pe_opt_header["headers_size"]);

			foreach ($this->pe_sections as &$sinfo)
			{
				$size = $this->SectionAlignValue(max($sinfo["virtual_size"], $sinfo["raw_data_size"]));

				if ($sinfo["flags"] & self::IMAGE_SCN_CNT_CODE || $sinfo["flags"] & self::IMAGE_SCN_MEM_EXECUTE)  $this->pe_opt_header["code_size"] += $size;
				if ($sinfo["flags"] & self::IMAGE_SCN_CNT_INITIALIZED_DATA)  $this->pe_opt_header["initialized_data_size"] += $size;
				if ($sinfo["flags"] & self::IMAGE_SCN_CNT_UNINITIALIZED_DATA)  $this->pe_opt_header["uninitialized_data_size"] += $size;

				$this->pe_opt_header["image_size"] += $size;
			}

			return array("success" => true);
		}

		public function CreateNewPESection(&$data, $name, $numbytes, $flags)
		{
			// Prepare space for the PE section.
			$result = $this->PrepareForNewPESection($data);
			if (!$result["success"])  return $result;

			// Calculate the base RVA of the new section.
			$rva = $this->SectionAlignValue($this->pe_opt_header["headers_size"]);
			foreach ($this->pe_sections as &$sinfo)
			{
				$rva2 = $this->SectionAlignValue($sinfo["rva"] + max($sinfo["virtual_size"], $sinfo["raw_data_size"]));
				if ($rva < $rva2)  $rva = $rva2;
			}

			// Fix inputs.
			if (strlen($name) < 8)  $name .= str_repeat("\x00", 8 - strlen($name));

			$numbytes = $this->FileAlignValue($numbytes);

			// Calculate start of file data.
			$x = strlen($data);
			$y = $this->FileAlignValue($x);

			// Append the section.
			$section = array(
				"name" => substr($name, 0, 8),
				"virtual_size" => $numbytes,
				"rva" => $rva,
				"raw_data_size" => $numbytes,
				"raw_data_ptr" => $y,
				"relocations_ptr" => 0,
				"line_nums_ptr" => 0,
				"num_relocations" => 0,
				"num_line_nums" => 0,
				"flags" => (int)$flags
			);

			$num = count($this->pe_sections);
			$this->pe_sections[] = $section;

			// Adjust section count.
			$this->pe_header["num_sections"]++;

			// Align and append data.
			$y += $numbytes;
			self::SetBytes($data, $x, "", $y - $x);

			// Update optional header calculations.
			$this->UpdatePEOptHeaderSizes();

			// Save the updated headers.
			$this->SaveHeaders($data);

			return array("success" => true, "num" => $num, "info" => $section);
		}

		public function GetLastPESectionIfAtEnd(&$data, $checkflags = false)
		{
			if (!isset($this->pe_opt_header))  return false;

			$num = count($this->pe_sections) - 1;
			if ($num < 0)  return false;

			// Checks for exact section flags.
			if ($checkflags !== false && $this->pe_sections[$num]["flags"] !== (int)$checkflags)  return false;

			// Check to see if the file is aligned properly, that the last section is at the end of the file data, and that the virtual size is not greater than the raw data size.
			$x = strlen($data);
			$y = $this->FileAlignValue($x);
			if ($x !== $y || $this->pe_sections[$num]["raw_data_ptr"] + $this->pe_sections[$num]["raw_data_size"] < $x || $this->pe_sections[$num]["virtual_size"] > $this->pe_sections[$num]["raw_data_size"])  return false;

			// Determine if there are any larger RVAs.
			$rva = $this->pe_sections[$num]["rva"];
			foreach ($this->pe_sections as &$sinfo)
			{
				if ($rva < $sinfo["rva"])  return false;
			}

			return $num;
		}

		public function ExpandLastPESection(&$data, $numbytes)
		{
			$num = $this->GetLastPESectionIfAtEnd($data);
			if ($num === false)  return array("success" => false, "error" => "The last PE section is not located at the end of the file data.", "errorcode" => "last_pe_section_not_at_end");

			// Increase the section size by the file aligned number of bytes.
			$numbytes = $this->FileAlignValue($numbytes);
			$this->pe_sections[$num]["virtual_size"] += $numbytes;
			$this->pe_sections[$num]["raw_data_size"] += $numbytes;

			// Append aligned data.
			$x = strlen($data);
			$y = $x + $numbytes;
			$origsize = $x;
			self::SetBytes($data, $x, "", $y - $x);

			// Update optional header calculations.
			$this->UpdatePEOptHeaderSizes();

			// Save the updated headers.
			$this->SaveHeaders($data);

			return array("success" => true, "pos" => $origsize, "size" => $y - $origsize);
		}

		public function DeletePESection(&$data, $num)
		{
			if (!isset($this->pe_opt_header))  return array("success" => false, "error" => "Not a suitable PE file.  The PE optional header is missing.", "errorcode" => "missing_pe_opt_header");
			if (!isset($this->pe_sections[$num]))  return array("success" => false, "error" => "The specified PE section does not exist.", "errorcode" => "invalid_pe_section");

			// Shrink the data if the last section in the file or just zero it out.
			$x = $this->pe_sections[$num]["raw_data_ptr"];
			$y = $x + $this->pe_sections[$num]["raw_data_size"];
			if ($y >= strlen($data))  $data = substr($data, 0, $x);
			else  self::SetBytes($data, $x, "", $y - $x);

			unset($this->pe_sections[$num]);
			$this->pe_sections = array_values($this->pe_sections);

			// Zero out the header space.
			$lastpos = $this->GetRealImageHeadersSize();
			$x = 0;
			self::SetBytes($data, $x, "", $lastpos - $x);

			// Adjust section count.
			$this->pe_header["num_sections"]--;

			// Update optional header calculations.
			$this->UpdatePEOptHeaderSizes();

			// Save the updated headers.
			$this->SaveHeaders($data);

			return array("success" => true);
		}

		public function ExpandPEDataDirectories(&$data, $newnum = 16)
		{
			if (!isset($this->pe_opt_header))  return array("success" => false, "error" => "Not a suitable PE file.  The PE optional header is missing.", "errorcode" => "missing_pe_opt_header");

			if ($this->pe_opt_header["num_data_directories"] < $newnum)
			{
				$numbytes = ($newnum - $this->pe_opt_header["num_data_directories"]) * 8;

				// Use the PE section insert function to create the necessary space.
				$result = $this->PrepareForNewPESection($data, $numbytes);
				if (!$result["success"])  return $result;

				$this->pe_header["optional_header_size"] += $numbytes;
				$this->pe_opt_header["num_data_directories"] = $newnum;
			}

			return array("success" => true);
		}

		public function Internal_SortPEResourceDirEntries($num, $num2)
		{
			$entry = &$this->pe_data_dir["resources"]["dir_entries"][$num];
			$entry2 = &$this->pe_data_dir["resources"]["dir_entries"][$num2];

			if (isset($entry["name"]) && !isset($entry2["name"]))  return -1;
			if (!isset($entry["name"]) && isset($entry2["name"]))  return 1;

			if (isset($entry["name"]) && isset($entry2["name"]))  return strcasecmp($entry["name"], $entry2["name"]);

			if ($entry["id"] === $entry2["id"])  return 0;

			return ($entry["id"] < $entry2["id"] ? -1 : 1);
		}

		public function SavePEResourcesDirectory(&$data)
		{
			// Expand to the standard number of directories if needed.
			$result = $this->ExpandPEDataDirectories($data);
			if (!$result["success"])  return $result;

			// Load and zero the existing resource data directory.
			$nextrva = false;
			$nextpos = 0;
			$bytesleft = 0;
			if ($this->pe_data_dir["resources"]["rva"] && $this->pe_data_dir["resources"]["size"])
			{
				$dirinfo = $this->RVAToPos($this->pe_data_dir["resources"]["rva"]);
				if ($dirinfo !== false)
				{
					$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
					$x = $pos + $dirinfo["pos"];
					$y = min($pos + $this->pe_sections[$dirinfo["section"]]["size"], $x + $this->pe_data_dir["resources"]["size"]);

					$nextrva = $this->pe_sections[$dirinfo["section"]]["rva"] + $dirinfo["pos"];
					$nextpos = $x;
					$bytesleft = $y - $x;

					self::SetBytes($data, $x, "", $bytesleft);
				}
			}

			// Pass 1:  Calculate the size of each directory level to sort entries and determine starting offsets.
			$levelmap = array(0 => 0);
			$diroffsets = array();
			$namesize = 0;
			$leafbytes = 0;
			$leaves = array();
			while (count($levelmap))
			{
				foreach ($levelmap as $num => $level)
				{
					if (!isset($diroffsets[$level]))  $diroffsets[$level] = 0;

					$entry = &$this->pe_data_dir["resources"]["dir_entries"][$num];

					if ($entry["type"] === "node")
					{
						if (!isset($entry["entries"]))  $entry["entries"] = array();

						foreach ($entry["entries"] as $key => $num2)
						{
							if (!isset($this->pe_data_dir["resources"]["dir_entries"][$num2]))  unset($entry["entries"][$key]);
							else  $levelmap[$num2] = $level + 1;
						}

						// Sort nodes.
						usort($entry["entries"], array($this, "Internal_SortPEResourceDirEntries"));

						$diroffsets[$level] += 16 + (count($entry["entries"]) * 8);
					}
					else
					{
						// Zero the RVA for any existing entries in the resource directory.
						if ($nextrva !== false && $entry["rva"] >= $nextrva && $entry["rva"] < $nextrva + $bytesleft)  $entry["rva"] = 0;

						$diroffsets[$level] += 16 + 8;

						$leaves[] = $num;

						$leafbytes += $this->AlignValue(strlen($entry["data"]), 4);
					}

					if (isset($entry["name"]))  $namesize += 2 + strlen($entry["name"]);

					unset($levelmap[$num]);
				}
			}

			// Finalize offset calculations.
			$prevval = 0;
			foreach ($diroffsets as $num => $val)
			{
				$nextval = $prevval + $val;

				$diroffsets[$num] = array("start" => $prevval, "next" => $nextval);

				$prevval = $nextval;
			}

			$leafoffset = $prevval;
			$stroffset = $leafoffset + (count($leaves) * 16);

			// Verify that at least the directory fits into existing space.
			// Align to 16 bytes.  No particular reason other than fact the Microsoft resource compiler appears to do this too.
			$y = $this->AlignValue($stroffset + $namesize, 16);

			if ($nextrva !== false)
			{
				if ($y > $bytesleft)  $nextrva = false;
				else  $secnum = false;
			}

			// Doesn't fit/exist.  Prepare an entirely new space for the resource directory.
			if ($nextrva === false)
			{
				// Check the last section in the file if it has resource directory-compatible flags OR create a new section.
				$secnum = $this->GetLastPESectionIfAtEnd($data, self::IMAGE_SCN_CNT_INITIALIZED_DATA | self::IMAGE_SCN_MEM_READ);
				if ($secnum === false)
				{
					$result = $this->CreateNewPESection($data, ".rsrc", 0, self::IMAGE_SCN_CNT_INITIALIZED_DATA | self::IMAGE_SCN_MEM_READ);
					if (!$result["success"])  return $result;

					$secnum = $result["num"];
				}

				// Calculate the starting RVA and position in the data.
				$size = max($this->pe_sections[$secnum]["virtual_size"], $this->pe_sections[$secnum]["raw_data_size"]);
				$nextrva = $this->pe_sections[$secnum]["rva"] + $size;
				$nextpos = $this->pe_sections[$secnum]["raw_data_ptr"] + $size;

				// Might as well store everything in here.
				$result = $this->ExpandLastPESection($data, $y + $leafbytes);

				$bytesleft = $result["size"];

				// Zero data and reset RVAs for all leaf nodes.
				foreach ($leaves as $num)
				{
					$result = $this->GetExclusiveResourceRVARefAndZero($data, $num);
					if ($result !== false)  $this->pe_data_dir["resources"]["dir_entries"][$num]["rva"] = 0;
				}

				// Update the data directory reference.
				$this->pe_data_dir["resources"]["rva"] = $nextrva;
				$this->pe_data_dir["resources"]["size"] = $bytesleft;

				// Save the updated headers.
				$this->SaveHeaders($data);
			}

			// Adjust various values to skip over the reserved directory space.
			$dirpos = $nextpos;
			$nextrva += $y;
			$nextpos += $y;
			$bytesleft -= $y;


			// Pass 2:  Generate the data directory.  Write out resources as needed.
			$dirdata = str_repeat("\x00", $y);
			$levelmap = array(0 => 0);
			$leafoffset2 = $leafoffset;
			while (count($levelmap))
			{
				foreach ($levelmap as $num => $level)
				{
					$entry = &$this->pe_data_dir["resources"]["dir_entries"][$num];

					if ($entry["type"] === "node")
					{
						// Determine how many named entries are in the list.
						$named = 0;
						foreach ($entry["entries"] as $num2)
						{
							if (isset($this->pe_data_dir["resources"]["dir_entries"][$num2]["name"]))  $named++;
							else  break;
						}

						// Write out the directory header.
						self::SetUInt32($dirdata, $diroffsets[$level]["start"], $entry["flags"]);
						self::SetUInt32($dirdata, $diroffsets[$level]["start"], $entry["created"]);
						self::SetUInt16($dirdata, $diroffsets[$level]["start"], $entry["major_ver"]);
						self::SetUInt16($dirdata, $diroffsets[$level]["start"], $entry["minor_ver"]);
						self::SetUInt16($dirdata, $diroffsets[$level]["start"], $named);
						self::SetUInt16($dirdata, $diroffsets[$level]["start"], count($entry["entries"]) - $named);

						// Write out the directory entries.
						foreach ($entry["entries"] as $num2)
						{
							$entry2 = &$this->pe_data_dir["resources"]["dir_entries"][$num2];

							if (isset($entry2["name"]))
							{
								self::SetUInt32($dirdata, $diroffsets[$level]["start"], 0x80000000 | $stroffset);

								$size = (int)((strlen($entry2["name"]) + 1) / 2);
								self::SetUInt16($dirdata, $stroffset, $size);
								self::SetBytes($dirdata, $stroffset, $entry2["name"], $size * 2);
							}
							else
							{
								self::SetUInt32($dirdata, $diroffsets[$level]["start"], 0x7FFFFFFF & $entry2["id"]);
							}

							if ($entry2["type"] === "node")
							{
								self::SetUInt32($dirdata, $diroffsets[$level]["start"], 0x80000000 | $diroffsets[$level]["next"]);

								$diroffsets[$level]["next"] += 16 + (count($entry2["entries"]) * 8);
							}
							else
							{
								self::SetUInt32($dirdata, $diroffsets[$level]["start"], 0x7FFFFFFF & $leafoffset2);

								$leafoffset2 += 16;
							}

							$levelmap[$num2] = $level + 1;
						}
					}
					else
					{
						// Assign a RVA to the resource and write out the resource item data.
						if (!$entry["rva"])
						{
							// Acquire sufficient resource table storage.
							$x = strlen($entry["data"]);
							$y = $this->AlignValue($x, 4);
							if ($bytesleft < $y)
							{
								// Ran out of storage in the resource directory.  Move onto a new section.
								if ($secnum === false)
								{
									// Check the last section in the file if it has resource directory-compatible flags OR create a new section.
									$secnum = $this->GetLastPESectionIfAtEnd($data, self::IMAGE_SCN_CNT_INITIALIZED_DATA | self::IMAGE_SCN_MEM_READ);
									if ($secnum === false)
									{
										$result = $this->CreateNewPESection($data, ".rsrc", 0, self::IMAGE_SCN_CNT_INITIALIZED_DATA | self::IMAGE_SCN_MEM_READ);
										if (!$result["success"])  return $result;

										$secnum = $result["num"];
									}

									// Calculate the starting RVA and position in the data.
									$size = max($this->pe_sections[$secnum]["virtual_size"], $this->pe_sections[$secnum]["raw_data_size"]);
									$nextrva = $this->pe_sections[$secnum]["rva"] + $size;
									$nextpos = $this->pe_sections[$secnum]["raw_data_ptr"] + $size;

									$bytesleft = 0;
								}

								$result = $this->ExpandLastPESection($data, $y - $bytesleft);

								$bytesleft += $result["size"];
							}

							// Write the data and update information.
							self::SetBytes($data, $nextpos, $entry["data"], $y);

							$entry["rva"] = $nextrva;
							$entry["size"] = $x;

							$nextrva += $y;
							$bytesleft -= $y;
						}

						self::SetUInt32($dirdata, $leafoffset, $entry["rva"]);
						self::SetUInt32($dirdata, $leafoffset, $entry["size"]);
						self::SetUInt32($dirdata, $leafoffset, $entry["code_page"]);
						self::SetUInt32($dirdata, $leafoffset, $entry["reserved"]);
					}

					unset($levelmap[$num]);
				}
			}

			// Write the data and update information.
			self::SetBytes($data, $dirpos, $dirdata, strlen($dirdata));

			return array("success" => true);
		}

		public function CalculateHashes(&$data)
		{
			// Expand to the standard number of directories if needed.
			$result = $this->ExpandPEDataDirectories($data);
			if (!$result["success"])  return $result;

			// Create three Authenticode-compatible hashes:  MD5, SHA-1, and SHA-256.
			$md5 = hash_init("md5");
			$sha1 = hash_init("sha1");
			$sha256 = hash_init("sha256");

			// Add bytes from the beginning of the file to the PE checksum position.
			$data2 = substr($data, 0, $this->pe_opt_header["checksum_pos"]);
			hash_update($md5, $data2);
			hash_update($sha1, $data2);
			hash_update($sha256, $data2);

			// Add bytes from after the PE checksum to the Certificate data directory position.
			$x = $this->pe_opt_header["checksum_pos"] + 4;
			$y = $this->pe_opt_header["data_directories_pos"] + (4 * 8);
			$data2 = substr($data, $x, $y - $x);
			hash_update($md5, $data2);
			hash_update($sha1, $data2);
			hash_update($sha256, $data2);

			// Add bytes from after the Certificate data directory position until either the Certificate data position or the end of the file.
			$x = $y + 8;
			$y = ($this->pe_data_dir["certificates"]["pos"] ? $this->pe_data_dir["certificates"]["pos"] : strlen($data));
			$data2 = substr($data, $x, $y - $x);
			hash_update($md5, $data2);
			hash_update($sha1, $data2);
			hash_update($sha256, $data2);

			// Add bytes that follow the Certificate data.
			if ($this->pe_data_dir["certificates"]["pos"] && $this->pe_data_dir["certificates"]["size"])
			{
				$x = $this->pe_data_dir["certificates"]["pos"] + $this->pe_data_dir["certificates"]["size"];
				$y = strlen($data);
				if ($x < $y)
				{
					$data2 = substr($data, $x, $y - $x);
					hash_update($md5, $data2);
					hash_update($sha1, $data2);
					hash_update($sha256, $data2);
				}
			}

			// Finalize.
			$result = array(
				"success" => true,
				"md5" => strtoupper(hash_final($md5)),
				"sha1" => strtoupper(hash_final($sha1)),
				"sha256" => strtoupper(hash_final($sha256))
			);

			return $result;
		}

		public function ClearCertificates(&$data)
		{
			if (!isset($this->pe_opt_header))  return array("success" => false, "error" => "Not a suitable PE file.  The PE optional header is missing.", "errorcode" => "missing_pe_opt_header");

			if ($this->pe_data_dir["certificates"]["pos"] && $this->pe_data_dir["certificates"]["size"])
			{
				// Shrink the data if the last thing in the file (most likely) or just zero it out.
				$x = $this->pe_data_dir["certificates"]["pos"];
				$y = $x + $this->pe_data_dir["certificates"]["size"];
				if ($y >= strlen($data))  $data = substr($data, 0, $x);
				else  self::SetBytes($data, $x, "", $y - $x);

				// Reset the data directory.
				$this->pe_data_dir["certificates"]["pos"] = 0;
				$this->pe_data_dir["certificates"]["size"] = 0;
				unset($this->pe_data_dir["certificates"]["certs"]);

				// Save the updated headers.
				$this->SaveHeaders($data);
			}

			return array("success" => true);
		}

		public function ClearBoundImports(&$data)
		{
			if (!isset($this->pe_opt_header))  return array("success" => false, "error" => "Not a suitable PE file.  The PE optional header is missing.", "errorcode" => "missing_pe_opt_header");

			if ($this->pe_data_dir["bound_imports"]["pos"] && $this->pe_data_dir["bound_imports"]["size"])
			{
				// Shrink the data if the last thing in the file or just zero it out.
				$x = $this->pe_data_dir["bound_imports"]["pos"];
				$y = $x + $this->pe_data_dir["bound_imports"]["size"];
				if ($y >= strlen($data))  $data = substr($data, 0, $x);
				else  self::SetBytes($data, $x, "", $y - $x);

				// Reset the data directory.
				$this->pe_data_dir["bound_imports"]["pos"] = 0;
				$this->pe_data_dir["bound_imports"]["size"] = 0;
				unset($this->pe_data_dir["bound_imports"]["dir_entries"]);

				// Save the updated headers.
				$this->SaveHeaders($data);
			}

			return array("success" => true);
		}

		public function ClearDebugDirectory(&$data)
		{
			if (!isset($this->pe_opt_header))  return array("success" => false, "error" => "Not a suitable PE file.  The PE optional header is missing.", "errorcode" => "missing_pe_opt_header");

			if ($this->pe_data_dir["debug"]["rva"] && $this->pe_data_dir["debug"]["size"])
			{
				// Zero out directory entries.
				if (isset($this->pe_data_dir["debug"]["dir_entries"]))
				{
					foreach ($this->pe_data_dir["debug"]["dir_entries"] as &$entry)
					{
						$x = $entry["data_ptr"];
						$y = $entry["size"];

						self::SetBytes($data, $x, "", $y - $x);
					}
				}

				// Zero out the directory.
				$dirinfo = $this->RVAToPos($this->pe_data_dir["debug"]["rva"]);
				if ($dirinfo !== false)
				{
					$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
					$x = $pos + $dirinfo["pos"];
					$y = min($pos + $this->pe_sections[$dirinfo["section"]]["size"], $x + $this->pe_data_dir["debug"]["size"]);

					self::SetBytes($data, $x, "", $y - $x);
				}

				// Reset the data directory.
				$this->pe_data_dir["debug"]["pos"] = 0;
				$this->pe_data_dir["debug"]["size"] = 0;
				unset($this->pe_data_dir["debug"]["dir_entries"]);

				// Save the updated headers.
				$this->SaveHeaders($data);
			}

			return array("success" => true);
		}

		public function SanitizeDOSStub(&$data)
		{
			if (!isset($this->pe_opt_header))  return array("success" => false, "error" => "Not a suitable PE file.  The PE optional header is missing.", "errorcode" => "missing_pe_opt_header");

			// Shrink the DOS stub if possible.
			if (strlen($this->dos_stub) >= strlen(self::$defaultDOSstub))
			{
				$lastpos = $this->GetRealImageHeadersSize();

				$this->dos_header = self::GetDefaultDOSHeader();
				$this->dos_stub = self::$defaultDOSstub;

				$tempheader = self::GetDefaultPEOptHeader($this->pe_opt_header["signature"]);
				$this->pe_opt_header["checksum_pos"] = $tempheader["checksum_pos"];
				$this->pe_opt_header["data_directories_pos"] = $tempheader["data_directories_pos"];

				// Zero out the header space.  It'll get saved in just a moment but there might be cruft floating around.
				$x = 0;
				self::SetBytes($data, $x, "", $lastpos);

				// Save the updated headers.
				$this->SaveHeaders($data);
			}
			else
			{
				// Use the PE section insert function to create the necessary space.
				$result = $this->PrepareForNewPESection($data, strlen(self::$defaultDOSstub) - strlen($this->dos_stub));
				if (!$result["success"])  return $result;
			}

			return array("success" => true);
		}

		// Maps a Relative Virtual Address (RVA) to a section and relative position within the section.
		public function RVAToPos($rva)
		{
			foreach ($this->pe_sections as $num => &$info)
			{
				if ($info["rva"] <= $rva && $info["rva"] + max($info["virtual_size"], $info["raw_data_size"]) > $rva)  return array("section" => $num, "pos" => $rva - $info["rva"]);
			}

			return false;
		}

		public function GetRVAString(&$data, $rva)
		{
			$dirinfo = $this->RVAToPos($rva);
			if ($dirinfo === false)  return false;

			$pos = $this->pe_sections[$dirinfo["section"]]["raw_data_ptr"];
			$x = $pos + $dirinfo["pos"];

			if ($x >= strlen($data))  return "";

			$pos = strpos($data, "\x00", $x);
			if ($pos === false)  return substr($data, $x);

			return substr($data, $x, $pos - $x);
		}

		public static function GetUInt8(&$data, &$x, $y)
		{
			return ord(self::GetBytes($data, $x, $y, 1));
		}

		public static function GetUInt16(&$data, &$x, $y)
		{
			return unpack("v", self::GetBytes($data, $x, $y, 2))[1];
		}

		// Technically not a UInt32 on 32-bit PHP.  The library adapts automatically for most scenarios.
		public static function GetUInt32(&$data, &$x, $y)
		{
			return unpack("V", self::GetBytes($data, $x, $y, 4))[1];
		}

		public static function GetUInt64(&$data, &$x, $y)
		{
			if (PHP_INT_SIZE >= 8)  $val = unpack("P", self::GetBytes($data, $x, $y, 8))[1];
			else
			{
				// Returns a float on 32-bit PHP for large values (rare).  Irrelevant though for all use-cases.
				$val4 = unpack("v", self::GetBytes($data, $x, $y, 2))[1];
				$val3 = unpack("v", self::GetBytes($data, $x, $y, 2))[1];
				$val2 = unpack("v", self::GetBytes($data, $x, $y, 2))[1];
				$val = unpack("v", self::GetBytes($data, $x, $y, 2))[1];

				$val = $val * 0x10000 + $val2;
				$val = $val * 0x10000 + $val3;
				$val = $val * 0x10000 + $val4;
			}

			return $val;
		}

		public static function GetBytes(&$data, &$x, $y, $size)
		{
			if ($size < 0)  return "";

			if ($x >= $y)  $result = str_repeat("\x00", $size);
			else if ($x + $size >= $y)  $result = (string)substr($data, $x, $y - $x) . str_repeat("\x00", $x + $size - $y);
			else  $result = (string)substr($data, $x, $size);

			$x += $size;

			return $result;
		}

		public static function SetUInt8(&$data, &$x, $val)
		{
			self::SetBytes($data, $x, chr($val), 1);
		}

		public static function SetUInt16(&$data, &$x, $val)
		{
			self::SetBytes($data, $x, pack("v", $val), 2);
		}

		public static function SetUInt32(&$data, &$x, $val)
		{
			self::SetBytes($data, $x, pack("V", $val), 4);
		}

		public static function SetUInt64(&$data, &$x, $val)
		{
			if (PHP_INT_SIZE >= 8)  self::SetBytes($data, $x, pack("P", $val), 8);
			else
			{
				$val2 = (int)($val % 0x10000);
				$val = ($val - $val2) / 0x10000;
				self::SetBytes($data, $x, pack("v", $val2), 2);

				$val2 = (int)($val % 0x10000);
				$val = ($val - $val2) / 0x10000;
				self::SetBytes($data, $x, pack("v", $val2), 2);

				$val2 = (int)($val % 0x10000);
				$val = ($val - $val2) / 0x10000;
				self::SetBytes($data, $x, pack("v", $val2), 2);

				$val2 = (int)($val % 0x10000);
				self::SetBytes($data, $x, pack("v", $val2), 2);

				return;
			}
		}

		public static function SetBytes(&$data, &$x, $val, $size)
		{
			for ($x2 = 0; $x2 < $size; $x2++)
			{
				$data[$x] = (isset($val[$x2]) ? $val[$x2] : "\x00");

				$x++;
			}
		}
	}
?>