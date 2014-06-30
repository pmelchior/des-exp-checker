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
INSERT INTO qa (rowid, release, fileid, userid, problem, detail, timestamp) SELECT rowid, "SVA1", fileid, userid, problem, comment, timestamp FROM old.qa ORDER BY rowid ASC;

-- Update to new problem numbers
UPDATE qa SET problem=54 WHERE problem=1;
UPDATE qa SET problem=32 WHERE problem=2;
UPDATE qa SET problem=55 WHERE problem=3;
UPDATE qa SET problem=41 WHERE problem=4;
UPDATE qa SET problem=-5 WHERE problem=5;

-- Remove entries with no fileid
DELETE FROM qa WHERE fileid='';

-- Re-organize the DB structure: qa is now in file db
-- file db is named after release
ATTACH DATABASE "users.dev.db" as users;
CREATE TABLE qa (
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

INSERT INTO qa (rowid, fileid, userid, problem, x, y, detail, timestamp) SELECT rowid, fileid, userid, problem, x, y, detail, timestamp FROM users.qa ORDER BY rowid ASC;
UPDATE qa SET problem=1000 WHERE problem=-1; -- new awesome
UPDATE qa SET problem=1005 WHERE problem=-5; -- old readout
UPDATE qa SET problem=1006 WHERE problem=-6; -- old other
UPDATE qa SET problem=1007 WHERE problem=-7; -- old sky
UPDATE qa SET problem=1008 WHERE problem=-52; -- old maks dots

