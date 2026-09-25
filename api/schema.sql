-- Import once into a private MySQL 8 / MariaDB database. Never store exports in public_html.
CREATE TABLE IF NOT EXISTS bookings (
  id VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  recovery_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  route VARCHAR(16) NOT NULL,
  direction VARCHAR(8) NOT NULL,
  departure BIGINT NOT NULL,
  valid_from BIGINT NOT NULL,
  valid_until BIGINT NOT NULL,
  amount INT NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'creating',
  provider_order VARCHAR(128) NULL,
  payment_id VARCHAR(128) UNIQUE NULL,
  checkout_url TEXT NULL,
  created_at BIGINT NOT NULL,
  updated_at BIGINT NOT NULL,
  last_checked BIGINT NOT NULL DEFAULT 0,
  used_at BIGINT NULL,
  used_by VARCHAR(64) NULL,
  INDEX reconcile (status, last_checked),
  INDEX departures (departure)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS rate_limits (
  bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  hits INT NOT NULL DEFAULT 0,
  expires_at BIGINT NOT NULL,
  INDEX expiry (expires_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS operations (
  id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  booking_id VARCHAR(40) NOT NULL,
  action VARCHAR(40) NOT NULL,
  actor VARCHAR(64) NOT NULL,
  created_at BIGINT NOT NULL
) ENGINE=InnoDB;
