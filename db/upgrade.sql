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


-- upgrade old table to innodb
alter table `bookmarks` ENGINE=InnoDB;
alter table `bookmarks_items` ENGINE=InnoDB;
alter table `cashsales` ENGINE=InnoDB;
alter table `courier_record` ENGINE=InnoDB;
alter table `lzd_fee_statements` ENGINE=InnoDB;
alter table `orders` ENGINE=InnoDB;
alter table `orders_insert_log` ENGINE=InnoDB;
alter table `orders_temp` ENGINE=InnoDB;
alter table `seller_sku` ENGINE=InnoDB;
