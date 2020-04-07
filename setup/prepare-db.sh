#! /bin/bash

if [ -a maneval.db ]; then
	echo "Database already exists. Please delete first." 1>&2
	exit 1
fi

sqlite3 maneval.db <create-db.maneval.sql

cd system-outputs

php ../upload-4sent.php general/source.txt
php ../upload-4sent.php general/baseline.txt
php ../upload-4sent.php general/docrepair.txt
php ../upload-4sent.php general/transference.txt

php ../upload-4sent.php discourse/source.txt
php ../upload-4sent.php discourse/baseline.txt
php ../upload-4sent.php discourse/docrepair.txt
php ../upload-4sent.php discourse/transference.txt

sqlite3 ../maneval.db 'update corpora set srctgt=0 where name like "%source.txt";'
