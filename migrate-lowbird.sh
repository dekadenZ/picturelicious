#!/bin/bash
set -e -o pipefail
oldschema=lowbird
newschema=picturelicious

if [ ! -d 2013/01 ]; then
	echo 'Are you sure »data/images« is the current working directory? If yes, please create a directory »2013/01« to overide this sanity check.' >&2
	exit 2
fi

mysql -B "$newschema" < "${0%.sh}.sql"

# image checksums
checksumfile="/tmp/$newschema-sha1sums.txt"
( if [ -s "$checksumfile.xz" ]; then
	exec xz -dc "$checksumfile.xz"
else
	find -type f -exec sha1sum -b -- \{\} + |
	tee >(exec xz -zc -9e > "$checksumfile.xz")
fi; ) |
sed -re 's|^(\S+)\s\*\./(.+)\.[^\./]*$|UPDATE `pl_images` SET `hash`=UNHEX(\x27\1\x27) WHERE `keyword`=\x27\2\x27;|' |
tee >(exec mysql -B "$newschema")
echo "If everything went well, you can delete »$checksumfile.xz«." >&2

# image widths and heights
find -type f -exec gm identify -format '%w %h %d/%f\n' \{\} + |
uniq -f 2 | # skip lines generated by gm for additional GIF frames
sed -nre 's|^([0-9]+) ([0-9]+) \./(.+)\.[^\./]*$|UPDATE `pl_images` SET `width`=\1, `height`=\2 WHERE `keyword`=\x27\3\x27;|p' |
tee >(exec mysql -B "$newschema")

# split tags
mysql -B -r "$oldschema" <<-'EOF' |
SELECT CONCAT(
		REPLACE(`tags`, ' ', CONCAT(' ', `id`, '\n')), ' ', `id`, '\n',
		REPLACE(SUBSTRING(`keyword`, 9), '-', CONCAT(' ', `id`, '\n')), ' ', `id`
	) AS `tag_id`
FROM `lb_images`;
EOF
sed -nre '
	1 a SET max_heap_table_size = 4<<30; SET tmp_table_size = 4<<30; CREATE TEMPORARY TABLE `pl_tags_migrate` (`image` BIGINT UNSIGNED NOT NULL, `tag` VARCHAR(127) NOT NULL) ENGINE=MEMORY;
	s/,+//g
	/^[^a-zA-Z\s]* /d
	s/([\\\x27])/\\\1/g
	s/^(\S{2,127}) ([0-9]+)$/INSERT INTO `pl_tags_migrate` (`image`, `tag`) VALUES (\2, \x27\L\1\x27);/p
	$ a DELETE FROM `pl_tags`; INSERT IGNORE INTO `pl_tags` (`image`, `tag`) SELECT DISTINCT t.`image`, t.`tag` FROM pl_tags_migrate AS t INNER JOIN pl_images AS i ON t.image = i.id WHERE CHAR_LENGTH(t.`tag`) >= 2;
' | #cat; exit
uniq |
tee >(exec mysql -B "$newschema")
