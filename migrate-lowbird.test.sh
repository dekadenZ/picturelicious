#!/bin/bash
set -e
cd picturelicious/data

mysql -B picturelicious <<< 'SELECT LOWER(HEX(`hash`)) AS `hash` FROM pl_images;' | {
	read
	while read -r h; do
		f="${h:0:2}/${h:2:2}/${h}"
		img="`echo "images/$f".*`"
		[ -e "$img" ] || echo "$img"
		
		#for thumbtype in thumbs/*; do
		#	t="$thumbtype/$f.jpg"
		#	[ "$t" -nt "$img" ] || echo "$t"
		#done
	done
}
