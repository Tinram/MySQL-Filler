
# MySQL Filler

#### Fill all MySQL database tables.


## Purpose

Populate all the tables of a MySQL database with a specified number of rows of junk data, and optionally jumble the foreign keys to create sufficient fake key relationships between tables for SQL joins.


## Background

Some tasks require populating databases for testing. Database dumps are huge and the data usually *verboten* without anonymisation (i.e. contains Personally Identifiable Information). My [old script](https://github.com/Tinram/Database-Filler) fills database tables &ndash; useful for schema design but not queries.


## Example Databases

database | support | notes |
:- | :-: | :- |
[*basketball*](https://github.com/Tinram/Database-Filler/blob/master/basketball.sql) | :heavy_check_mark: | substantial intersection table |
[*classicmodels*](https://www.mysqltutorial.org/wp-content/uploads/2018/03/mysqlsampledatabase.zip) | :heavy_check_mark: | |
[employees](https://github.com/ronaldbradford/schema/blob/master/employees.sql) <small>(old)</small> | :heavy_check_mark: | |
[Joomla](https://github.com/ronaldbradford/schema/blob/master/joomla.sql) <small>(old)</small> | :heavy_check_mark: | 60 tables |
[*Sakila*](https://dev.mysql.com/doc/index-other.html) | :heavy_multiplication_x: | |
tpcc | :heavy_multiplication_x: | partial with option fudges |
[*world*](https://dev.mysql.com/doc/index-other.html) | :heavy_check_mark: | |
[WordPress](https://github.com/ronaldbradford/schema/blob/master/wordpress.sql) <small>(old)</small> | :heavy_check_mark: | |

*Sakila* uses sophisticated spatial data types.

[Old schemas](https://github.com/ronaldbradford/schema) reference.


## Requirements

+ An empty or truncated or throwaway database schema already present in the MySQL server.
+ Sufficient privileges granted for the connecting user.


## Limitations

+ No support of MySQL spatial data types.
+ Capped length of longer data types.
+ Composite primary keys can be troublesome.


## Usage

Import a database schema-only file into MySQL or use an existing 'throwaway' database already active within MySQL.

**Do not use this package on a database that you care about: *MySQL Filler* will so surely trash it.**

Ensure `SELECT, INSERT, UPDATE, DROP` privileges for the connecting user (`DROP` is required for table wiping.)

In *config.py*, edit the database credentials, and the *MySQL Filler* options required, then:

```bash
python3 main.py
```

For multiprocessing support (`PROCS = <num_cpu_cores>`) and a significant speed increase, copy the *config.py* file imports and global variables into *src/mysql_filler.py* and run as a standalone script.  
It's un-pythonic and ugly, but executes multiprocessing more reliably.


## Options

```python

NUM_ROWS = 10                     # number of rows to add per table
PROCS = 1                         # number of multithreading processes

JUMBLE_FKS = True                 # foreign key jumbling toggle
FK_PCT_REPLACE = 25               # percentage (of row number added) of foreign keys to jumble

STRICT_INSERT = False             # INSERT IGNORE toggle to bypass strict SQL mode (warnings rather than errors)

PROCESS_INT_FKS = True            # process or skip integer foreign keys toggle (tpcc schema)
COMPOSITE_PK_INCREMENT = False    # skip or increment composite primary keys toggle (tpcc schema)

BYTES_DECODE = 'utf-8'            # character set for binary decoding
MAX_PACKET = False                # increased maximum packet size toggle (root user only)

DEBUG = False                     # debug output toggle
EXTENDED_DEBUG = False            # verbose debug output toggle

TRUNCATE_TABLES = False           # truncate all database tables toggle (instead of populating)

```


## Example Run

Using the simple MySQL [*world*](https://dev.mysql.com/doc/index-other.html) database,  
import the database from the compressed file:

```bash
$ tar -xzOf world-db.tar.gz | mysql -h localhost -u root -p
```

Allocate a user with the required privileges:

```sql
mysql> GRANT SELECT, INSERT, UPDATE, DROP ON world.* TO 'general'@'localhost' IDENTIFIED BY 'P@55w0rd';
mysql> FLUSH PRIVILEGES;
```

Edit the *config.py* file and wipe all data that the *world* database ships with by setting `TRUNCATE_TABLES` to `True`:


```python
TRUNCATE_TABLES = True
```

```bash
python3 main.py
```

    Truncating all tables of `world` database ...

Change `TRUNCATE_TABLES = False` and execute *main.py* again:

```bash
python3 main.py
```

    localhost
    world
    +10 rows ...

```sql
mysql> USE world;
mysql> SELECT ID, CountryCode, country.Code, Code2 FROM city INNER JOIN country ON country.Code = city.CountryCode;
```

Unlikely to be any results returned by adding just 10 rows; perhaps one 'lucky' row returned for this database in 100 rows added.

```python
NUM_ROWS = 200
...
JUMBLE_FKS = True
```

Execute *main.py*

    localhost
    world
    +200 rows

    `city`
    `country`
    `countrylanguage`

    foreign keys jumbled

A few results now returned from the previous query, and a CountryCode can be selected:

```sql
SELECT ID, CountryCode, country.Code FROM city INNER JOIN country ON country.Code = city.CountryCode WHERE CountryCode = 'ewu';
+----+-------------+------+
| ID | CountryCode | Code |
+----+-------------+------+
| 74 | ewu         | EWU  |
+----+-------------+------+
1 row in set (0.00 sec)
```

Meaningless data, but the foreign keys are starting to gain relationships, and so SQL joins between tables are now realised. More rows can be added to increase the number of results.


## Speed

Speed with multiprocessing (combining *config.py* into one script) is okay. Speed never was on the agenda.

For serious speed, there's Percona's Go-based [mysql_random_data_load](https://github.com/Percona-Lab/mysql_random_data_load). Currently, this tool fills one table at a time &ndash; fast &ndash; yet somewhat laborious for databases with lots of tables, whereas I wanted all database tables populated with one command.


## MariaDB

MariaDB has limited support. It has more restrictions on key constraints and is less forgiving than MySQL 5.7 or 8.0


## Other

Tested using MySQL 5.7 and 8.0, and MariaDB 10.4

This package cannot hope to support all variations (good and bad) of MySQL schemas.

For example, adding 1,000 rows to the following real-world table is not going to run smoothly:

```sql
+----------------+------------+------+-----+---------+
| Field          | Type       | Null | Key | Default |
+----------------+------------+------+-----+---------+
| google_channel | char(3)    | YES  |     | NULL    |
| locale_id      | tinyint(3) | YES  | UNI | NULL    |
+----------------+------------+------+-----+---------+
```

 &ndash; because of the restricted range of TINYINT values, the number of rows, and the unique key.

Composite primary keys can cause trouble. However, the *config.py* options allow overrides to enable at least basic table population.


## License

*MySQL Filler* is released under the [GPL v.3](https://www.gnu.org/licenses/gpl-3.0.html).
