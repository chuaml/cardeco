CREATE TABLE if not exists `bigseller_sku_map` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bigseller_sku` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `item_id` int NOT NULL,
  `date_created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bigseller_sku_map_un` (`bigseller_sku`,`item_id`),
  KEY `bigseller_sku_map_FK` (`item_id`),
  CONSTRAINT `bigseller_sku_map_FK` FOREIGN KEY (`item_id`) REFERENCES `stock_items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Safely upgrade legacy MyISAM tables to InnoDB ONLY if they are still on MyISAM
DROP PROCEDURE IF EXISTS `upgrade_myisam_to_innodb`;
DELIMITER $$
CREATE PROCEDURE `upgrade_myisam_to_innodb`()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE tbl_name VARCHAR(64);
    DECLARE cur CURSOR FOR
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND ENGINE = 'MyISAM'
          AND TABLE_NAME IN (
              'bookmarks',
              'bookmarks_items',
              'cashsales',
              'courier_record',
              'lzd_fee_statements',
              'orders',
              'orders_insert_log',
              'orders_temp',
              'seller_sku'
          );
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO tbl_name;
        IF done THEN
            LEAVE read_loop;
        END IF;
        SET @sql = CONCAT('ALTER TABLE `', tbl_name, '` ENGINE=InnoDB');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    CLOSE cur;
END$$
DELIMITER ;

CALL `upgrade_myisam_to_innodb`();
DROP PROCEDURE IF EXISTS `upgrade_myisam_to_innodb`;
