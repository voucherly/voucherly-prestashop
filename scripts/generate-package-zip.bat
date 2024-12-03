@echo off
rem This script is used to generate a zip file for deploy to Wordpress 

echo 🏁 Start generate zip for deploy

cd ..
if exist _cache_voucherly rmdir /s /q _cache_voucherly
mkdir "_cache_voucherly"

xcopy /E /I voucherly-prestashop "_cache_voucherly" /EXCLUDE:voucherly-prestashop\scripts\list-of-excluded-files.txt
del /q _cache_voucherly\.gitignore
del /q _cache_voucherly\.DS_Store
del /q _cache_voucherly\.nvmrc
cd _cache_voucherly

powershell Compress-Archive -Path * -DestinationPath voucherly-prestashop.zip -Force
rem call 7z a -tzip voucherly-prestashop.zip * -xr!*.DS_Store
move voucherly-prestashop.zip ..
cd ..
rmdir /s /q _cache_voucherly
echo 🚀 End generate zip for deploy
pause