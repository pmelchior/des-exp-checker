-- files database structure
CREATE TABLE files (
 expname TEXT,
 ccd integer,
 band TEXT,
 name TEXT
);
CREATE INDEX files_band_idx ON "files" (band);
CREATE INDEX files_ccd_idx ON "files" (ccd);
CREATE INDEX files_expname_idx ON "files" (expname);

CREATE TABLE qa (
 fileid INT NOT NULL,
 userid INT NOT NULL,
 problem INT NOT NULL,
 x INT,
 y INT,
 detail TEXT,
 timestamp TEXT NOT NULL DEFAULT current_timestamp
);
CREATE INDEX qa_fileid_idx ON "qa" (fileid);
CREATE INDEX qa_problem_idx ON "qa" (problem);
CREATE INDEX qa_userid_idx ON "qa" (userid);

CREATE TABLE runs (
 expname TEXT PRIMARY KEY ON CONFLICT IGNORE,
 runname TEXT
);

-- users database structure
CREATE TABLE seeds (
 seed TEXT PRIMARY KEY ON CONFLICT IGNORE,
 timestamp TEXT NOT NULL DEFAULT current_timestamp,
);

CREATE TABLE sessions (
  sid TEXT PRIMARY KEY ON CONFLICT IGNORE,
  uid INT NOT NULL,
  ip TEXT NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
);

CREATE TABLE users (
  username TEXT NOT NULL,
  email TEXT NOT NULL,
  password TEXT NOT NULL,
  total_files INT NOT NULL DEFAULT 0,
  flagged_files INT NOT NULL DEFAULT 0,
  timestamp TEXT NOT NULL default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX users_email_ids ON users(email); -- enforce username & email unique
CREATE UNIQUE INDEX users_username_idx ON users(username);

