ALTER TABLE quote_items ADD COLUMN cost DECIMAL(18,4) NULL CHECK(cost IS NULL OR cost>=0);
ALTER TABLE pc_projects ADD COLUMN board_revision VARCHAR(40), ADD COLUMN bios_version VARCHAR(40);
ALTER TABLE service_order_consents ADD COLUMN signature_png MEDIUMBLOB;
CREATE TABLE product_bios_support (
  motherboard_id BIGINT UNSIGNED NOT NULL,
  cpu_id BIGINT UNSIGNED NOT NULL,
  board_revision VARCHAR(40) COLLATE utf8mb4_0900_as_cs NOT NULL,
  bios_version VARCHAR(40) COLLATE utf8mb4_0900_as_cs NOT NULL,
  compatible BOOLEAN NOT NULL,
  source VARCHAR(240) NOT NULL,
  PRIMARY KEY (motherboard_id,cpu_id,board_revision,bios_version),
  FOREIGN KEY (motherboard_id) REFERENCES products(id),
  FOREIGN KEY (cpu_id) REFERENCES products(id)
);
