echo 'Building com_jed4'
composer install
composer -d src/administrator/components/com_jed install
./vendor/bin/robo build


