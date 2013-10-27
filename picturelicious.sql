CREATE DATABASE `picturelicious` DEFAULT CHARACTER SET utf8 DEFAULT COLLATE=utf8_unicode_ci;
USE `picturelicious`;


CREATE TABLE `pl_users` (
  `id` SERIAL,
  `registered` BIGINT NOT NULL,
  `name` VARCHAR(63) NOT NULL,
  `pass` VARBINARY(255),
  `valid` BOOL NOT NULL,
  `remember` BINARY(16),
  `admin` BOOL NOT NULL DEFAULT FALSE,
  `avatar` VARCHAR(255),
  `website` VARCHAR(1023),
  `email` VARCHAR(255),
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`name`) USING BTREE,
  UNIQUE KEY `remember` (`remember`) USING HASH
) ENGINE=InnoDB;

# Legacy user scores
CREATE TABLE `pl_users_legacy` (
  `user` SERIAL,
  `score` BIGINT NOT NULL,
  PRIMARY KEY (`user`),
  FOREIGN KEY (`user`) REFERENCES `pl_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;


# Separate table for validation requests to keep the original values and
# restore them if necessary
CREATE TABLE `pl_user_validation_requests` (
  `user` BIGINT UNSIGNED NOT NULL,
  `token` BINARY(16),
  `pass` VARBINARY(255),
  `email` VARCHAR(255),
  `time` BIGINT NOT NULL,
  PRIMARY KEY (`user`),
  FOREIGN KEY (`user`) REFERENCES `pl_users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `token` (`token`) USING HASH
) ENGINE=InnoDB;


CREATE TABLE `pl_images` (
  `id` SERIAL,
  `logged` BIGINT NOT NULL,
  `user` BIGINT UNSIGNED NOT NULL,
  `keyword` VARCHAR(255) NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `thumb` VARCHAR(255) NOT NULL,
  `hash` BINARY(20) NOT NULL,
  `source` VARCHAR(1023),
  `delete_reason` ENUM('', 'other', 'repost', 'low quality', 'copyright', 'spam', 'hardcore porn/gore', 'illegal') NOT NULL DEFAULT '',
  `width` INT UNSIGNED,
  `height` INT UNSIGNED,
  PRIMARY KEY  (`id`),
  FOREIGN KEY (`user`) REFERENCES `pl_users` (`id`),
  INDEX `user` (`user`, `logged` DESC) USING BTREE,
  INDEX `keyword` (`keyword`) USING HASH,
  INDEX `hash` (`hash`) USING HASH,
  INDEX `logged` (`logged` DESC) USING BTREE
) ENGINE=InnoDB;

CREATE VIEW `plv_uploadlimit` AS
SELECT `user`, IF(COUNT(`logged`) >= 10, UNIX_TIMESTAMP() - MIN(`logged`) + 2*3600, 0) AS next_upload_time
FROM `pl_images`
WHERE `logged` > UNIX_TIMESTAMP() - 2*3600
GROUP BY `user`;


# Legacy image ratings and tags
CREATE TABLE `pl_images_legacy` (
  `image` SERIAL,
  `votecount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `rating` DOUBLE NOT NULL DEFAULT 0,
  `tags` TEXT,
  PRIMARY KEY (`image`),
  FOREIGN KEY (`image`) REFERENCES `pl_images` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE `pl_imageratings` (
  `image` BIGINT UNSIGNED NOT NULL,
  `user` BIGINT UNSIGNED NOT NULL,
  `rating` TINYINT NOT NULL,
  PRIMARY KEY (`image`, `user`),
  FOREIGN KEY (`image`) REFERENCES `pl_images` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user`) REFERENCES `pl_users` (`id`),
  INDEX `user` (`user`) USING HASH
) ENGINE=InnoDB;

CREATE TABLE `pl_favorite_images` (
  `user` BIGINT UNSIGNED NOT NULL,
  `image` BIGINT UNSIGNED NOT NULL,
  `time` BIGINT NOT NULL,
  PRIMARY KEY (`user`, `image`),
  FOREIGN KEY (`user`) REFERENCES `pl_users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`image`) REFERENCES `pl_images` (`id`) ON DELETE CASCADE,
  KEY `image` (`image`) USING HASH
) ENGINE=InnoDB;


# Image ratings excluding votes by respective owners, including legacy ratings, aggregated by image
CREATE VIEW `plv_images` AS
SELECT i.*,
  COUNT(r.`rating`) + IFNULL(l.`votecount`, 0) AS `votecount`,
  (IFNULL(SUM(r.`rating`), 0) + IFNULL(l.`rating`, 0) * IFNULL(l.`votecount`, 0)) / (COUNT(r.`rating`) + IFNULL(l.`votecount`, 0)) AS `rating`,
  COUNT(f.`user`) AS `favorited_count`
FROM `pl_images` AS i
  LEFT OUTER JOIN `pl_images_legacy` AS l ON i.`id` = l.`image`
  LEFT OUTER JOIN `pl_imageratings` AS r FORCE INDEX (PRIMARY) ON i.`id` = r.`image`
  LEFT OUTER JOIN `pl_favorite_images` AS f ON i.`id` = f.`image`
WHERE r.`user` IS NULL OR i.`id` <> r.`user`
GROUP BY i.`id`;


CREATE TABLE `pl_comments` (
  `id` SERIAL,
  `parent` BIGINT UNSIGNED,
  `image` BIGINT UNSIGNED NOT NULL,
  `user` BIGINT UNSIGNED NOT NULL,
  `created` BIGINT NOT NULL,
  `edited` BIGINT NOT NULL,
  `content` TEXT NOT NULL,
  `delete_reason` ENUM('', 'other', 'spam', 'illegal') NOT NULL DEFAULT '',
  PRIMARY KEY  (`id`),
  FOREIGN KEY (`userId`) REFERENCES `pl_users` (`id`),
  FOREIGN KEY (`imageId`) REFERENCES `pl_images` (`id`) ON DELETE CASCADE,
  INDEX `image` (`imageId`) USING HASH,
  INDEX `user` (`userId`) USING HASH,
  INDEX `parent` (`parent`) USING HASH
) ENGINE=InnoDB;


CREATE TABLE `pl_commentratings` (
  `comment` BIGINT UNSIGNED NOT NULL,
  `user` BIGINT UNSIGNED NOT NULL,
  `rating` TINYINT NOT NULL,
  PRIMARY KEY (`comment`, `user`),
  FOREIGN KEY (`user`) REFERENCES `pl_users` (`id`),
  FOREIGN KEY (`comment`) REFERENCES `pl_comments` (`id`) ON DELETE CASCADE,
  INDEX `user` (`user`) USING HASH
) ENGINE=InnoDB;


# Up and down votes excluding respective authors, aggregated by comment
CREATE VIEW `plv_comments` AS
SELECT c.*, SIGN(r.`rating`) AS `rating_type`, COUNT(r.`rating`) as `count`
FROM `pl_comments` AS c LEFT OUTER JOIN `pl_commentratings` AS r
ON c.`id` = r.`comment`
WHERE c.`userId` <> r.`user`
GROUP BY c.`id`, `rating_type`;


CREATE TABLE `pl_imagecolors` (
  `imageId` BIGINT UNSIGNED NOT NULL,
  `hue` FLOAT UNSIGNED NOT NULL,
  `saturation` FLOAT UNSIGNED NOT NULL,
  `value` FLOAT UNSIGNED NOT NULL,
  FOREIGN KEY (`imageId`) REFERENCES `pl_images` (`id`) ON DELETE CASCADE,
  INDEX `image` (`imageId`) USING HASH
) ENGINE=InnoDB;


CREATE TABLE `pl_tags` (
  `image` BIGINT UNSIGNED NOT NULL,
  `tag` VARCHAR(127) NOT NULL,
  `author` BIGINT UNSIGNED,
  `time` BIGINT,
  `delete_reason` ENUM('', 'other', 'spam') NOT NULL DEFAULT '',
  PRIMARY KEY (`image`, `tag`),
  FOREIGN KEY (`author`) REFERENCES `pl_users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`image`) REFERENCES `pl_images` (`id`) ON DELETE CASCADE,
  INDEX `tag` (`tag`) USING BTREE,
  INDEX `author` (`author`) USING HASH
) ENGINE=InnoDB;


# Log of "quick tag" actions to prevent repetition
CREATE TABLE `pl_taglog` (
  `tagged` BIGINT NOT NULL,
  `userId` BIGINT UNSIGNED NOT NULL,
  `imageId` BIGINT UNSIGNED NOT NULL,
  `locked` BOOL NOT NULL,
  PRIMARY KEY  (`userId`, `imageId`),
  FOREIGN KEY (`userId`) REFERENCES `pl_users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`imageId`) REFERENCES `pl_images` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;


# Image tags sorted by overall frequency
CREATE VIEW `pl_tag_frequency` AS
SELECT `tag`, COUNT(`image`) AS `frequency`
FROM `pl_tags`
WHERE `delete_reason` = ''
GROUP BY `tag`;


# User scores from posting images, aggregated by user
CREATE VIEW `plv_score_user_images` AS
SELECT `user`, SUM(`delete_reason` = '') AS `count`,
  SUM(IF(`delete_reason` = '', 10,
    IF(CAST(`delete_reason` AS UNSIGNED) >= 6, -50, 0))) AS `score`
FROM `pl_images`
GROUP BY `user`;

# User scores from rating images, aggregated by user
CREATE VIEW `plv_score_user_imagevotes` AS
SELECT `user`, COUNT(`image`) AS `count`, COUNT(`image`) * 1 AS `score`
FROM `pl_imageratings`
GROUP BY `user`;

# User scores from image ratings by other users, aggregated by user
CREATE VIEW `plv_score_user_imageratings` AS
SELECT i.`user` AS `user`, (AVG(r.`rating`) - 2) * 1 AS `score`
FROM `pl_images` AS i INNER JOIN `pl_imageratings` AS r
ON i.`id` = r.`image`
WHERE i.`user` <> r.`user` AND `delete_reason` = ''
GROUP BY `user`;

# User scores from comment ratings by other users, aggregated by user
CREATE VIEW `plv_score_user_comments` AS
SELECT r.`user`, COUNT(r.`rating`) AS `count`, SUM(r.`rating`) * 1 AS `score`
FROM `pl_commentratings` AS r
  INNER JOIN `pl_comments` AS c ON c.`id` = r.`comment`
WHERE r.`user` <> c.`author` AND c.`delete_reason` = ''
GROUP BY r.`user`;

# Support view for subquery in `plv_score_user_tags`
CREATE VIEW `plv_score_user_image_tags` AS
SELECT `author` AS `user`,
  SUM(`delete_reason` = '') AS `count`,
  SUM(IF(`delete_reason` = '', CHAR_LENGTH(`tag`), 0)) AS `length`,
  GREATEST(LEAST(SUM(
      IF(`delete_reason` = '', 2.5,
        IF(CAST(`delete_reason` AS UNSIGNED) > 1, -5, 0))
    ), 20), -5) AS `score`
FROM `pl_tags`
WHERE `author` IS NOT NULL
GROUP BY `user`, `image`;

# User score from tagging images, aggregated by user, limited to the interval [-5, 20] per image and user
CREATE VIEW `plv_score_user_tags` AS
SELECT `user`, SUM(`count`) AS `count`, SUM(`length`) AS `length`, SUM(`score`) AS `score`
FROM `plv_score_user_image_tags`
GROUP BY `user`;

# Combined user score, aggregated by user
CREATE VIEW `plv_score_user` AS
SELECT u.`id`,
  IFNULL(l.`score`, 0) + IFNULL(i.`score`, 0) + IFNULL(iv.`score`, 0) + IFNULL(ir.`score`, 0) + IFNULL(c.`score`, 0) + IFNULL(t.`score`, 0) AS `score`
FROM `pl_users` AS u
  LEFT OUTER JOIN `pl_users_legacy` AS l ON u.`id` = l.`user`
  LEFT OUTER JOIN `plv_score_user_images` AS i ON u.`id` = i.`user`
  LEFT OUTER JOIN `plv_score_user_imagevotes` AS iv ON u.`id` = iv.`user`
  LEFT OUTER JOIN `plv_score_user_imageratings` AS ir ON u.`id` = ir.`user`
  LEFT OUTER JOIN `plv_score_user_comments` AS c ON u.`id` = c.`user`
  LEFT OUTER JOIN `plv_score_user_tags` AS t ON u.`id` = t.`user`;


CREATE TABLE `pl_absuses` (
  `type` ENUM('user', 'image', 'comment', 'tag') NOT NULL,
  `abusedId` BIGINT UNSIGNED NOT NULL,
  `author` BIGINT UNSIGNED,
  `ctime` BIGINT NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `comment` TEXT DEFAULT NULL,
  PRIMARY KEY (`type`, `abusedId`, `author`)
  #FOREIGN KEY (`author`) REFERENCES `pl_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB;
