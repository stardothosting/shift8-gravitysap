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
# NOTE: removed the mkdir/rsync into tags/$2 to avoid svn cp conflict
rsync -ravzp --exclude-from './push.exclude' ./ ./svn/trunk
rsync -ravzp ./assets/ ./svn/assets

cd svn
svn --username shift8 add trunk/*
svn --username shift8 add assets/*

# commit trunk changes
svn commit -m "$1"

# create the tag from trunk (let svn create versioned tags/$2)
svn cp trunk tags/$2

# commit the tag
svn --username shift8 ci -m "$1 version $2"

# back to original dir (kept exactly as you had)
cd ../

