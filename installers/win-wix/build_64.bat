@echo off
rem This runs WiX candle and light to perform a 64-bit only build.

for %%f in (*.wxs) do (
  "%WIX%\bin\candle.exe" -arch x64 "%%f"
  "%WIX%\bin\light.exe" -ext WixUtilExtension.dll "%%~dpnf.wixobj"
  del "%%~dpnf.wixobj"
  del "%%~dpnf.wixpdb"
)
