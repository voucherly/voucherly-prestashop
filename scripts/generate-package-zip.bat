@echo off
rem This script is used to generate a zip file for deploy to Wordpress 

echo 🏁 Start generate zip for deploy

call composer install

rem Fix code
call php vendor/bin/autoindex prestashop:add:index ./
call php vendor/bin/php-cs-fixer fix
call php vendor/bin/header-stamp --license=assets\gpl.txt --exclude=vendor,node_modules

cd ..
if exist _cache_voucherly rmdir /s /q _cache_voucherly
mkdir "_cache_voucherly"
mkdir "_cache_voucherly\voucherly"

xcopy /E /I voucherly-prestashop "_cache_voucherly\voucherly" /EXCLUDE:voucherly-prestashop\scripts\list-of-excluded-files.txt
del /q _cache_voucherly\voucherly\.gitignore
del /q _cache_voucherly\voucherly\.php-cs-fixer.dist.php
cd _cache_voucherly\voucherly

for /r %%i in (*.DS_Store) do del "%%i"
call composer install --no-dev --optimize-autoloader

cd ..
powershell Compress-Archive -Path * -DestinationPath voucherly-prestashop.zip -Force
move voucherly-prestashop.zip ..
cd ..
rmdir /s /q _cache_voucherly
echo 🚀 End generate zip for deploy

pause