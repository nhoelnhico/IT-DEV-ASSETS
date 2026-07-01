-- Migration: add OS Version and MAC Address to `assets`
-- Date:      2026-07-01
-- Author:    IT-DEV
--
-- Apply this on the on-premise main PC (phpMyAdmin > it_inventory_assets > SQL,
-- or `mysql -u root it_inventory_assets < this_file.sql`) AFTER the base schema
-- (it_inventory_assets.sql) has already been imported.
--
-- Adds two optional columns used by inventory.php:
--   os_version  - operating system / firmware version (e.g. "Windows 11 Pro 23H2")
--   mac_address - primary network MAC address (e.g. "00:1A:2B:3C:4D:5E")
-- Both are nullable: devices such as monitors or printers may not have either.

ALTER TABLE `assets`
    ADD COLUMN `os_version`  VARCHAR(100) DEFAULT NULL AFTER `serial_number`,
    ADD COLUMN `mac_address` VARCHAR(50)  DEFAULT NULL AFTER `os_version`;
