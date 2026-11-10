-- items
CREATE TABLE IF NOT EXISTS `PREFIX_lwa_fixture_item` (
    `id_item` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    PRIMARY KEY (`id_item`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

INSERT INTO `PREFIX_lwa_fixture_item` (`name`) VALUES ('hello; world');
