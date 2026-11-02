#!/bin/sh
set -eu

BASE=https://windows.php.net/downloads/releases
mkdir -p /work/.tools

apk add --no-cache curl ca-certificates >/dev/null 2>&1

echo "== composer.phar =="
curl -fsSL -o /work/.tools/composer.phar https://getcomposer.org/download/latest-stable/composer.phar
ls -l /work/.tools/composer.phar

echo "== resolving PHP 8.4 NTS build =="
SCRAPED=$(curl -fsSL "$BASE/latest/" 2>/dev/null \
  | grep -o 'php-8\.4[^"]*nts-Win32-vs17-x64[^"]*\.zip' \
  | head -n 1 || true)
echo "scraped: ${SCRAPED:-<none>}"

FOUND=0
for cand in "$SCRAPED" \
            "php-8.4-nts-Win32-vs17-x64-latest.zip" \
            "php-8.4.17-nts-Win32-vs17-x64.zip" \
            "php-8.4.16-nts-Win32-vs17-x64.zip" \
            "php-8.4.15-nts-Win32-vs17-x64.zip" \
            "php-8.4.14-nts-Win32-vs17-x64.zip" ; do
  [ -n "$cand" ] || continue
  echo "trying $cand"
  if curl -fsSL -o /work/.tools/php.zip "$BASE/latest/$cand" \
     || curl -fsSL -o /work/.tools/php.zip "$BASE/$cand"; then
    echo "GOT $cand"
    FOUND=1
    break
  fi
done

if [ "$FOUND" -ne 1 ]; then
  echo "!! could not download PHP zip"
  exit 2
fi

ls -l /work/.tools/php.zip
echo "== done =="
