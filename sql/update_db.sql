-- Update the database structure
ATTACH DATABASE "users.db" as old;
DROP TABLE qa;
CREATE TABLE qa (
 release TEXT NOT NULL,
 fileid INT NOT NULL,
 userid INT NOT NULL,
 problem INT NOT NULL,
 x INT,
 y INT,
 detail TEXT,
 timestamp TEXT NOT NULL DEFAULT current_timestamp);
INSERT INTO qa (rowid, release, fileid, userid, problem, detail, timestamp) SELECT rowid, "SVA1", fileid, userid, problem, comment, timestamp FROM old.qa;

-- Update to new problem numbers
UPDATE qa SET problem=54 WHERE problem=1;
UPDATE qa SET problem=32 WHERE problem=2;
UPDATE qa SET problem=55 WHERE problem=3;
UPDATE qa SET problem=41 WHERE problem=4;
UPDATE qa SET problem=-5 WHERE problem=5;

-- Remove entries with no fileid
DELETE FROM qa WHERE fileid='';

-- Re-organize the DB structure: qa is now in file db
-- files db's filename contains the release name, making it easy to switch
-- Open file db:
ATTACH DATABASE "users.db" as users;
CREATE TABLE qa (
 qaid INTEGER PRIMARY KEY ASC,
 fileid INT NOT NULL,
 userid INT NOT NULL,
 problem INT NOT NULL,
 x INT,
 y INT,
 detail TEXT,
 timestamp TEXT NOT NULL DEFAULT current_timestamp
);
CREATE INDEX qa_problem_idx ON "qa" (problem);
CREATE INDEX qa_fileid_idx ON "qa" (fileid);
CREATE INDEX qa_userid_idx ON "qa" (userid);

INSERT INTO qa (qaid, fileid, userid, problem, x, y, detail, timestamp) SELECT rowid, fileid, userid, problem, x, y, detail, timestamp FROM users.qa;
UPDATE qa SET problem=1000 WHERE problem=-1; -- new awesome
UPDATE qa SET problem=1005 WHERE problem=-5; -- old readout
UPDATE qa SET problem=1006 WHERE problem=-6; -- old other
UPDATE qa SET problem=1007 WHERE problem=-7; -- old sky
UPDATE qa SET problem=1008 WHERE problem=-52; -- old maks dots

-- make fileid safe for vacuum by declaring an explicit integer primary key
CREATE TABLE files (
 fileid INTEGER PRIMARY KEY ASC,
 expname TEXT,
 ccd integer,
 band TEXT,
 name TEXT
);
INSERT INTO files (fileid, expname, ccd, band, name) SELECT rowid, expname, ccd, band, name FROM SVA1;
DROP INDEX files_band_idx;
DROP INDEX files_expname_idx;
DROP INDEX files_ccd_idx;
CREATE INDEX files_expname_idx ON "files" (expname);
CREATE INDEX files_ccd_idx ON "files" (ccd);
DROP TABLE SVA1;
-- rename file db now to contain release name

-- This has to be done on the users db!
-- Move user submission statistics into own table
CREATE TABLE submissions (
 userid INTEGER NOT NULL,
 release TEXT NOT NULL,
 total_files INT NOT NULL DEFAULT 0,
 flagged_files INT NOT NULL DEFAULT 0
);
INSERT INTO submissions (userid, release, total_files, flagged_files) SELECT rowid, "SVA1", total_files, flagged_files FROM users;
CREATE INDEX submission_release_idx ON submissions(release);
CREATE INDEX submission_userid_idx ON submissions(userid);

ALTER TABLE users RENAME TO backup;
CREATE TABLE users (
 userid INTEGER PRIMARY KEY ASC,
 username TEXT NOT NULL,
 email TEXT NOT NULL,
 password TEXT NOT NULL,
 timestamp TEXT NOT NULL default CURRENT_TIMESTAMP
);
INSERT INTO users (userid, username, email, password, timestamp) SELECT rowid, username, email, password, timestamp FROM backup;
DROP INDEX users_email_ids;
DROP INDEX users_username_idx;
CREATE UNIQUE INDEX users_email_idx ON users(email); -- enforce username & email unique
CREATE UNIQUE INDEX users_username_idx ON users(username);
DROP TABLE backup;
DROP TABLE qa;
VACUUM;

-- This has to be done on the qa tables in all files db's to account for the flip of the y axis to show actual sky orientation
UPDATE qa SET y=512-y WHERE problem!=0;

-- To use filepath as part of the files db `name` field; no need to specify it in config
-- in SVA1
UPDATE files SET name="eye_se001grizt/" || name;
-- in first batch of Y1A1
UPDATE files SET name="eye_se004grizY/" || name;

