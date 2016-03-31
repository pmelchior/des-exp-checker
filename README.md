# DES Exposure Checker

Crowdsourced image quality control for the Dark Energy Survey (DES). The code available here is for a web-app that asks users to identify flaws in survey images. The app loads and displays FITS image directly in the browser and works on desktop and mobile devices.

More details can be found in the associated paper (submitted to Astronomy & Computing): 

http://arxiv.org/abs/1511.03391

A live demo version of the exposure checker in action can be found here:

http://des-exp-checker.pmelchior.net

The license is MIT. Feel free to use and modify, but please cite our paper if you do.

## Installation

The app requires a webserver (tested on apache and nginx) with PHP and SQLite support. Then:

1. Clone the repository and go into the new directory.
2. Copy `htaccess-minimum.txt` to `.htaccess` and append as needed for your installation. Make sure not to overwrite an existing file.
3. Execute `sqlite3 sql/files.sql` and `sqlite3 sql/users.sql`. This step creates two files `files.db` and `users.db`, move them into a directory the webserver can access but that is hidden from direct access. Because of `.htaccess`, files and directories that start with a `.` are inaccessible when requested through the webserver, so you can e.g. choose `.db`.
4. Copy `config.php.sample` to `config.php.inc` and edit as needed (see below).
5. On a production environment: Remove `.git`, `htaccess-minimum.txt`, `config.php.sample`.

## Configuration

###Config.php.inc

This file contains almost all of the particular configuration for the server and the location of files. Its content is:

```php
$config = array(
    "adminemail" => T_STRING,   // email address used for contact
    "adminname" => T_STRING,    // name used for admin emails
    "domain" => T_STRING,       // webserver domain name
    "releases" => T_ARRAY,	    // list of available releases
    "userdb" => T_STRING,       // path to the user database
    "filedb" => T_ARRAY,        // release => path to file database
    "fovpath" => T_ARRAY,       // release => path to FoV images
    "fitspath" => T_ARRAY       // release => path to fits files
    "images_per_fp" => T_INT,   // size of focal plane, used for congrats
    "release" => NULL,          // empty, set later
    "problem_code" => T_ARRAY   // problem label => numeric code
);

function sendEmail($email, $subject, $message) {
    global $config;
    $header = "From: " . $config['adminname'] . " <". $config['adminemail'] .">\n";
    $header .= "Reply-To: <". $config['adminemail'] .">\n";
    $header .= "Content-Type: text/plain; charset=UTF-8\n";
    $header .= "Content-Transfer-Encoding: 8bit\n";
    $message .= "\n\n" . $config['adminname'] ." --";
    $message .= "\nhttp://" . $config['domain'];
    return mail($email,$subject,$message,$header);
}
```

The code has a mechanism to swich between data releases. For that define a list of releases, e.g. 

```php
 "releases" => array("SVA1", "Y1A1")
```

 Set the arrays for `filedb`, `fovpath`,`fitspath` with one key-value pair for each release, e.g. 

```php
"filedb" => array("SVA1" => ".db/files.sva1.db", 
                  "Y1A1" => ".db/files.y1a1.db")
```

###Problem classes

The problem classes are defined in two different locations: As textual labels for frontend users and as numbers for the server (the reduce overhead and storage requirements in the file databases).

1. Open `problem_selector.shtml` and modify as needed. For a label to be working as intended, it needs to be in an HTML environment with `class="problem"`, e.g.

   ```html
   <a class="problem" href="#">Column mask</a>
   ```

2. Open `config.php.inc` again and make sure each problem label has a numeric code in `$config['problem_code']`, e.g.

   ```php
   $config['problem_code'] = array(
   	"OK" => 0, // DO NOT CHANGE!
       "Other..." => 999, // DO NOT CHANGE!
       "Awesome!" => 1000,  // DO NOT CHANGE!
       "Column mask" => 1,
       ...
       )
   ```

## API

In addition to the frontend statistics pages, user generated reports can be queried with an API. The path is

```
api.php?release=SVA1&problem=Column%20mask
```

The parameter `release` needs to be from `$config['releases']` and problem needs to be a key in `$config['problem_codes']`. If necessary, both need to be urlencoded.

The API returns JSON of the following form:

```json
[
  {"qa_id": int,
  "expname":string,
  "ccd":int,
  "band":string,
  "problem":string,
  "x":int,
  "y":int
  "detail": [null, string]
  "false_positive":bool,
  "release":string
  }
]
```

The list has one of dictionary per reported problem that matches the request. `qa_id` is a unique identifier of the report in the given `release`, `x/y` are the CCD coordinates of the center of the problem marker, `detail` (only set for labels "Other…" and "Awesome!") is a user-generated text to describe the report.