INSERT INTO `pl_users`
	(`id`, `registered`, `name`, `valid`, `admin`, `avatar`, `website`, `email`)
SELECT `id`, UNIX_TIMESTAMP(`registered`), `name`,
	`valid` <> 0, `admin` <> 0,
	NULLIF(`avatar`, 'data/avatars/default.png'),
	NULLIF(`website`, ''),
	NULLIF(`email`, '')
FROM `lowbird`.`lb_users`
WHERE `valid`;

INSERT INTO `pl_users_legacy`
SELECT `id`, `score`
FROM `lowbird`.`lb_users`
WHERE `score` IS NOT NULL AND `score` <> 0;


INSERT INTO `pl_images`
	(`id`, `logged`, `user`, `keyword`, `image`, `thumb`, `source`)
SELECT i.`id`, UNIX_TIMESTAMP(i.`logged`), i.`user`, i.`keyword`, i.`image`, i.`thumb`, NULLIF(i.`source`, '')
FROM `pl_users` AS u INNER JOIN `lowbird`.`lb_images` AS i
ON i.`user` = u.`id`;

INSERT INTO `pl_images_legacy`
SELECT old.`id`, GREATEST(old.`votes`, 0), GREATEST(old.`score`, 0), NULLIF(old.`tags`, '')
FROM `pl_images` AS new INNER JOIN `lowbird`.`lb_images` AS old
ON new.`id` = old.`id`
WHERE old.`votes` > 0 OR (old.`tags` IS NOT NULL AND old.`tags` <> '');


INSERT INTO `pl_comments`
	(`id`, `image`, `author`, `created`, `content`)
SELECT c.`id`, c.`imageId`, c.`userId`, UNIX_TIMESTAMP(c.`created`), c.`content`
FROM `pl_images` AS i INNER JOIN `lowbird`.`lb_comments` AS c
ON i.`id` = c.`imageId`;


INSERT INTO `pl_imagecolors`
	(`imageId`, `value`, `saturation`, `hue`)
SELECT `imageId`,
	(@max := GREATEST(r, g, b)) / 255 AS `value`,
	IF(@max <> 0, (@max - (@min := LEAST(r, g, b))) / @max, 0) AS `saturation`,
	(@hue := (CASE @max
		WHEN @min THEN 0
		WHEN r THEN 0 + (CAST(g AS SIGNED) - CAST(b AS SIGNED)) / (@max - @min)
		WHEN g THEN 2 + (CAST(b AS SIGNED) - CAST(r AS SIGNED)) / (@max - @min)
		WHEN b THEN 4 + (CAST(r AS SIGNED) - CAST(g AS SIGNED)) / (@max - @min)
	END) / 6) + (@hue < 0) AS `hue`
FROM `pl_images` AS i INNER JOIN `lowbird`.`lb_imagecolors`
ON i.`id` = `imageId`;


INSERT INTO `pl_tags_legacy`
