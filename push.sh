#!/bin/sh
# Usage: ./push.sh "commit message" 1.2.3

if [ $# -lt 2 ]; then echo "Usage: $0 \"message\" version"; exit 1; fi
MSG="$1"; VER="$2"

# Git push
git add .
git commit -m "$MSG"
git push origin main

# SVN push
rsync -ravzp --exclude-from './push.exclude' ./ ./svn/trunk
rsync -ravzp ./assets/ ./svn/assets

cd svn
svn add --force trunk assets >/dev/null 2>&1 || true
svn status | awk '/^!/{print $2}' | xargs -r svn rm
svn commit -m "$MSG"

svn cp trunk "tags/$VER"
svn commit -m "Tagging version $VER"

cd ../

