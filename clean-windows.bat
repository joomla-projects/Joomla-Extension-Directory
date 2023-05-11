rem Script to Clear Old Build Data on Windows
del /q composer.lock
rmdir /Q /S dist
rmdir /Q /S vendor
del /q src\administrator\components\com_jed\composer.lock
del /s /q src\administrator\components\com_jed\vendor\*.*
rmdir /Q /S src\administrator\components\com_jed\vendor

