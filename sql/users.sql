-- users database structure
CREATE TABLE seeds (
 seed TEXT PRIMARY KEY,
 timestamp TEXT NOT NULL DEFAULT current_timestamp,
);

CREATE TABLE sessions (
  sid TEXT PRIMARY KEY,
  uid INT NOT NULL,
  ip TEXT NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
);

CREATE TABLE submissions (
 userid INTEGER PRIMARY KEY ASC,
 release TEXT NOT NULL,
 total_files INT NOT NULL DEFAULT 0,
 flagged_files INT NOT NULL DEFAULT 0
);

CREATE TABLE users (
 userid INTEGER PRIMARY KEY ASC,
 username TEXT NOT NULL,
 email TEXT NOT NULL,
 password TEXT NOT NULL,
 timestamp TEXT NOT NULL default CURRENT_TIMESTAMP
);
-- enforce username & email unique
CREATE UNIQUE INDEX users_email_ids ON users(email);
CREATE UNIQUE INDEX users_username_idx ON users(username);