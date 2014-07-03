-- files database structure
-- execute this on a new file
CREATE TABLE files (
 fileid INTEGER PRIMARY KEY ASC,
 expname TEXT,
 ccd INT,
 band TEXT,
 name TEXT
);
CREATE INDEX files_ccd_idx ON "files" (ccd);
CREATE INDEX files_expname_idx ON "files" (expname);

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
CREATE INDEX qa_fileid_idx ON "qa" (fileid);
CREATE INDEX qa_problem_idx ON "qa" (problem);
CREATE INDEX qa_userid_idx ON "qa" (userid);

CREATE TABLE runs (
 expname text PRIMARY KEY ON CONFLICT IGNORE,
 runname text
);

-- add/append information from BNL run
ATTACH DATABASE "eye_se004grizY.db" as bnl;
INSERT INTO files (expname, ccd, band, name) SELECT expname, ccd, band, SUBSTR(field, 50) FROM bnl.files ORDER BY bnl.files.rowid ASC;
INSERT INTO runs (expname, runname) SELECT expname, run FROM bnl.files ORDER BY bnl.files.rowid ASC;