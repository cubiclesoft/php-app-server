@echo off
rem This runs WiX candle and light to perform a build.

for %%f in (*.wxs) do (
  "%WIX%\bin\candle.exe" "%%f"
  "%WIX%\bin\light.exe" -ext WixUtilExtension.dll "%%~dpnf.wixobj"
  del "%%~dpnf.wixobj"
  del "%%~dpnf.wixpdb"
)
