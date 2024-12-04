vendor\bin\php-cs-fixer fix -vvv --diff
vendor\bin\phpcs --extensions=php -p --standard=ruleset.xml src/
vendor\bin\phpcbf --extensions=php -p --standard=ruleset.xml src/
