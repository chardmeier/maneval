# maneval - Simple tool for pairwise comparison of MT output

This is a simple tool to run a pairwise comparison of the output of two MT systems.

**Warning:** This is not very polished. Use at your own risk. Contact me if you
have any questions.

## Installation

Put the script `index.php` from the `public_html` directory in a publicly
accessible web location. The scripts in the `setup` directory should _not_
be publicly accessible.

Decide where to put your database file. This should not be in a public web directory
either, but the webserver needs to have permission to access it. I'll use the
variable `$dbfile` for the path and file name of your database in this document.

Each of the PHP scripts in the `public_html` and `setup` directories contains a line
that looks like this:
```
$db = new PDO("sqlite:/home/staff/ch/maneval/maneval.db");
```
Replace the path in this expression with the location of your DB file.

## Setup

1. Create a database file by running
   ```
   sqlite $dbfile <setup/create-db.maneval.sql
   ```

2. Upload your MT input and output into the database. The translations
   should be in NIST-XML format. You can use
   [this script](https://github.com/chardmeier/docent/blob/master/scripts/txt2mteval.pl)
   to create the correct format if necessary. Then run
   ```
   setup/upload-nistxml.php file.xml
   ```

3. Define the evaluation tasks. To do this, open the database file with
   ```
   sqlite $dbfile
   ```
   Find out the corpus IDs of your MT input and output files by running
   ```
   select * from corpora;
   ```
   Define your task by running
   ```
   insert into tournament (id, source, corpus1, corpus2) values ($id, $srcid, $mt1id, $mt2id);
   ```
   where you should replace `$id` with a task ID that doesn't occur in the `tournament`
   table yet and `$srcid`, `$mt1id` and `$mt2id` with the corpus IDs of you MT input
   and the two outputs to compare, respectively.

   Set the current task like this:
   ```
   delete from current_task;
   insert into current_task values ($id);
   ```
   where `$id` is the task ID defined in the previous step.

4. Open `public_html/index.php` in your web browser. If everything went well, you
   should see the evaluation interface.

