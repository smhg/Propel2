#!/bin/sh

DIR=`dirname $0`;
path=`realpath ${DB_FILE-"$DIR/../test.sq3"}`;
echo "using db file $path (set DB_FILE env variable to change)"

rm -f $path;

dsn="sqlite:$path";
php $DIR/../../bin/propel test:prepare --vendor="sqlite" --dsn="$dsn";
