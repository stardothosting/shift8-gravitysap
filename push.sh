#!/bin/sh

if [ $# -eq 0 ]
then
	echo "No push argument was provided"
	exit 1
fi

# push git first
git add .
git commit -m "$1"
git push origin main

# rsync to svn
mkdir ./svn/tags/$2
rsync -ravzp --exclude-from './push.exclude' ./ ./svn/trunk
rsync -ravzp --exclude-from './push.exclude' ./ ./svn/tags/$2
rsync -ravzp ../wordpress-org-assets/ ./svn/assets
cd svn
svn --username shift8 add trunk/*
svn --username shift8 add assets/*
svn commit -m "$1"
svn cp trunk tags/$2
svn commit -m "Tagging verison $2"
#svn --username shift8 add tags/$2/*
#svn --username shift8 ci -m "$1 version $2"
cd ../
