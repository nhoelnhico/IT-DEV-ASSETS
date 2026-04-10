ALTER TABLE assets
MODIFY status ENUM('In Use', 'Available', 'Broken', 'Repairing')
NOT NULL DEFAULT 'Available';


ALTER TABLE transmittals
MODIFY transaction_type ENUM('IN', 'OUT', 'Repair')
NOT NULL;



ALTER TABLE assets
MODIFY status ENUM('In Use', 'Available', 'Broken', 'Repairing')
NOT NULL DEFAULT 'Available';

ALTER TABLE transmittals
MODIFY transaction_type ENUM('IN', 'OUT', 'Repair')
NOT NULL;