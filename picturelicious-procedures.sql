DELIMITER $$

DROP PROCEDURE IF EXISTS pl_register_user $$
CREATE PROCEDURE pl_register_user (
  IN _name VARCHAR(63),
  IN _email VARCHAR(255),
  IN _pass VARBINARY(255),
  IN validationToken BINARY(16),
  IN gracePeriod INT UNSIGNED
)
  READS SQL DATA
  MODIFIES SQL DATA
  SQL SECURITY INVOKER
BEGIN
  DECLARE _id BIGINT UNSIGNED;
  DECLARE success BOOL DEFAULT FALSE;
  DECLARE nameInUse BOOL DEFAULT FALSE;
  DECLARE emailInUse BOOL DEFAULT FALSE;
  DECLARE updateAllowed BOOL DEFAULT TRUE;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION ROLLBACK;

  START TRANSACTION;

  SELECT
    BIT_OR(u.`name` = _name),
    BIT_OR(u.`email` = _email OR v.`email` = _email),
    NOT BIT_OR(IF(u.`name` = _name, u.`valid` OR v.`time` > gracePeriod, u.`email` = _email OR v.`email` = _email))
  INTO
    nameInUse, emailInUse, updateAllowed
  FROM
    `pl_users` AS u
      LEFT OUTER JOIN
    `pl_user_validation_requests` AS v ON u.`id` = v.`user`
  WHERE
    u.`name` = _name OR
    u.`email` = _email OR
    v.`email` = _email;

  IF updateAllowed THEN
    IF NOT nameInUse THEN
      INSERT INTO
        `pl_users` (`name`, `email`, `valid`, `registered`)
      VALUES
        (_name, _email, FALSE, UNIX_TIMESTAMP());
      SELECT LAST_INSERT_ID() INTO _id;
    ELSE
      SELECT `id` INTO _id FROM `pl_users` WHERE `name` = _name;
      UPDATE `pl_users`
      SET
        `email` = _email,
        `pass` = NULL,
        `remember` = NULL,
        `valid` = FALSE
      WHERE
        `id` = _id;
    END IF;

    INSERT INTO
      `pl_user_validation_requests`
    VALUES
      (_id, validationToken, _pass, _email, UNIX_TIMESTAMP())
    ON DUPLICATE KEY UPDATE
      `token` = validationToken,
      `pass` = _pass,
      `email` = _email,
      `time` = UNIX_TIMESTAMP();
    SET success = TRUE;
  END IF;

  COMMIT;

  SELECT nameInUse, emailInUse, success;
END $$


DROP PROCEDURE IF EXISTS pl_validate_user_change $$
CREATE PROCEDURE pl_validate_user_change (
  IN _token BINARY(16),
  IN selectUserInfo BOOL
)
  READS SQL DATA
  MODIFIES SQL DATA
  SQL SECURITY INVOKER
BEGIN
  DECLARE userId BIGINT UNSIGNED;
  DECLARE _email VARCHAR(255);
  DECLARE passwordHash VARBINARY(255);

  DECLARE EXIT HANDLER FOR SQLEXCEPTION, NOT FOUND
  BEGIN
    ROLLBACK;
    SET userId = NULL;
    SET _email = NULL;
    SET passwordHash = NULL;
  END;

  START TRANSACTION;

  SELECT
    `user`, `email`, `pass`
  INTO
    userId, _email, passwordHash
  FROM `pl_user_validation_requests`
  WHERE
    `token` = _token;

  DELETE FROM `pl_user_validation_requests` WHERE `token` = _token;

  UPDATE `pl_users`
  SET
    `valid` = `valid` OR (_email IS NOT NULL AND passwordHash IS NOT NULL),
    `email` = IFNULL(_email, `email`),
    `pass` = IFNULL(passwordHash, `pass`),
    `remember` = IF(passwordHash IS NULL, `remember`, NULL)
  WHERE
    `id` = userId;

  IF NOT selectUserInfo THEN
    SELECT userId AS `id`, _email AS `email`, passwordHash;
  ELSE
    SELECT
      `id`, `name`, `admin`, `website`, `email`
    FROM `pl_users`
    WHERE
      `id` = userId;
  END IF;

  COMMIT;
END $$


DROP PROCEDURE IF EXISTS pl_find_image_prev_next $$
CREATE PROCEDURE pl_find_image_prev_next (
  IN _keyword VARCHAR(255),
  IN _user BIGINT UNSIGNED
)
  READS SQL DATA
  SQL SECURITY INVOKER
BEGIN
  DECLARE _id BIGINT UNSIGNED;

  SELECT `id` INTO _id
    FROM `pl_images`
    WHERE `keyword` = _keyword AND IFNULL(`user` = _user, TRUE);

  SELECT
    `id`, `keyword`, `hash`,
    CONCAT(DATE_FORMAT(FROM_UNIXTIME(`logged`), '%Y/%m/'), `image`) AS `path`,
    `width`, `height`,
    `user` AS `uploader`,
    `logged` AS `uploadtime`,
    `tags`, `votecount`, `rating`, `favorited_count`
  FROM ((
    SELECT
      `id`, `keyword`, `hash`, `image`, `width`, `height`, `user`, `logged`,
      NULL AS `tags`, NULL AS `votecount`, NULL AS `rating`, NULL AS `favorited_count`
    FROM `pl_images`
    WHERE `id` < _id AND `delete_reason` = '' AND IFNULL(`user` = _user, TRUE)
    ORDER BY `id` DESC LIMIT 1
  ) UNION ALL (
    SELECT
      i.`id`, i.`keyword`, i.`hash`,
      i.`image`, i.`width`, `height`,
      i.`user`, i.`logged`,
      GROUP_CONCAT(t.`tag` SEPARATOR '\0') AS `tags`,
      COUNT(r.`rating`) + IFNULL(l.`votecount`, 0) AS `votecount`,
      (IFNULL(SUM(r.`rating`), 0) + IFNULL(l.`rating`, 0) * IFNULL(l.`votecount`, 0)) / (COUNT(r.`rating`) + IFNULL(l.`votecount`, 0)) AS `rating`,
      COUNT(f.`user`) AS `favorited_count`
    FROM
      `pl_images` AS i
        LEFT OUTER JOIN
      `pl_tags` AS t ON i.`id` = t.`image`
        LEFT OUTER JOIN
      `pl_images_legacy` AS l ON i.`id` = l.`image`
        LEFT OUTER JOIN
      `pl_imageratings` AS r FORCE INDEX (PRIMARY) ON i.`id` = r.`image`
        LEFT OUTER JOIN
      `pl_favorite_images` AS f ON i.`id` = f.`image`
    WHERE
      i.`id` = _id
    GROUP BY i.`id`
  ) UNION ALL (
    SELECT
      `id`, `keyword`, `hash`, `image`, `width`, `height`, `user`, `logged`,
      NULL AS `tags`, NULL AS `votecount`, NULL AS `rating`, NULL AS `favorited_count`
    FROM `pl_images`
    WHERE `id` > _id AND `delete_reason` = '' AND IFNULL(`user` = _user, TRUE)
    ORDER BY `id` ASC LIMIT 1
  )) temp
  ORDER BY `id` DESC;
END $$


DROP FUNCTION IF EXISTS pl_get_next_upload_time $$
CREATE FUNCTION pl_get_next_upload_time (
  _user BIGINT UNSIGNED,
  threshold BIGINT UNSIGNED,
  timespan BIGINT
)
  RETURNS BIGINT
  READS SQL DATA
  SQL SECURITY INVOKER
BEGIN
  DECLARE _count BIGINT UNSIGNED;
  DECLARE _logged BIGINT;
  DECLARE c_img CURSOR FOR
    SELECT
      `logged`
    FROM
      `pl_images`
    WHERE
      `user` = _user AND
      `logged` > UNIX_TIMESTAMP() - timespan
    ORDER BY `logged` ASC;

  OPEN c_img;
  SET _count = FOUND_ROWS();
  WHILE _count >= threshold DO
    FETCH NEXT FROM c_img INTO _logged;
    SET _count = _count - 1;
  END WHILE;

  RETURN _logged + timespan;
END $$

DELIMITER ;
