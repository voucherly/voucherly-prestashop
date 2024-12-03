@echo off
rem This script is used to generate a zip file for deploy to Wordpress 

echo 🏁 Start generate zip for deploy

call composer install

rem Fix code
call vendor/bin/php-cs-fixer fix
call vendor/bin/autoindex prestashop:add:index ./
call vendor/bin/header-stamp --license=assets\gpl.txt --exclude=vendor,node_modules

cd ..
if exist _cache_voucherly rmdir /s /q _cache_voucherly
mkdir "_cache_voucherly"

xcopy /E /I voucherly-prestashop "_cache_voucherly" /EXCLUDE:voucherly-prestashop\scripts\list-of-excluded-files.txt
del /q _cache_voucherly\.gitignore
del /q _cache_voucherly\.php-cs-fixer.cache
del /q _cache_voucherly\.php-cs-fixer.dist.php
cd _cache_voucherly

powershell Compress-Archive -Path * -DestinationPath voucherly-prestashop.zip -Force
move voucherly-prestashop.zip ..
cd ..
rmdir /s /q _cache_voucherly
echo 🚀 End generate zip for deploy
pause