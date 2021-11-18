
# MySQL Filler

#### Populate a MySQL database and create fake foreign keys between tables.


## Purpose

Fill all the tables of a MySQL database with a limited number of rows, and jumble the foreign keys to create sufficient fake key relationships between tables for SQL joins.


## Background

Some work requires populating empty database schemas with junk data. Real data would be too copious, bandwidth-stealing, or *verboten* (i.e. contains Personally Identifiable Information). My [old script](https://github.com/Tinram/Database-Filler) fills database tables but a few foreign keys needed to be added afterwards for joins &ndash; annoying.


## Example Databases

database | support | notes |
:- | :-: | :- |
[*basketball*](https://github.com/Tinram/Database-Filler/blob/master/basketball.sql) | :heavy_check_mark: | substantial intersection table |
[*classicmodels*](https://www.mysqltutorial.org/wp-content/uploads/2018/03/mysqlsampledatabase.zip) | :heavy_check_mark: | |
[employees](https://github.com/ronaldbradford/schema/blob/master/employees.sql) <small>(old)</small> | :heavy_check_mark: | |
[Joomla](https://github.com/ronaldbradford/schema/blob/master/joomla.sql) <small>(old)</small> | :heavy_check_mark: | |
[*Sakila*](https://dev.mysql.com/doc/index-other.html) | :heavy_multiplication_x: | |
[*world*](https://dev.mysql.com/doc/index-other.html) | :heavy_check_mark: | |
[WordPress](https://github.com/ronaldbradford/schema/blob/master/wordpress.sql) <small>(old)</small> | :heavy_check_mark: | |

Sakila uses sophisticated datatypes such as Geo data.

[Old schemas](https://github.com/ronaldbradford/schema) reference.


## Requirements

+ An empty/truncated or throwaway database already present in the MySQL server.
+ Sufficient privileges granted for the connecting user.


## Usage

Import a database schema-only file into MySQL or use an existing 'throwaway' database already active within MySQL.

**Do not use this package on a database that you care about: MySQL Filler will so surely trash it.**

Ensure `SELECT, INSERT, UPDATE, DROP` privileges for the connecting user (`DROP` is required for table wiping.)

In *runner.php*, edit the database credentials, and the MySQL Filler options required, then:

```bash
php runner.php
```


## Options

option | value | &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; | description
:- | :- | :-: | :-
*debug* | true/false | &nbsp; | toggle verbose output
*num_rows* | integer | | number of rows to add to each database table
*row_counter_threshold* | integer | | progress indicator, increase as number of rows considerably increases
*FK_jumble* | true/false | | toggle foreign key jumbling to acquire valid joins on junk data
*FK_percent_replacement* &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; | integer | | percentage of foreign keys to replace
*ignore_tables* | array | | names of problematic/crazy-schema tables to skip processing
*truncate* | true/false | | toggle to wipe the database data conveniently, preserving the empty schema; also useful to reset primary key AUTO_INCREMENT row positions from an imported active schema


## Example Run

Using the simple MySQL [*world*](https://dev.mysql.com/doc/index-other.html) database:

```bash
$ tar -xzOf world-db.tar.gz | mysql -h localhost -u root -p
```

```sql
mysql> GRANT SELECT, INSERT, UPDATE, DROP ON world.* TO 'general'@'localhost' IDENTIFIED BY 'P@55w0rd';
mysql> FLUSH PRIVILEGES;
```

Wipe all data that *world* ships with:

```php
$config = [
    ...
    'truncate' => true,
    'num_rows' => 20,
    'FK_jumble' => false,
```

```bash
php runner.php
```

    Truncated all tables of 'world' database!

Change `truncate` to true, execute *runner.php* again:

    Tables of 'world' populated with 20 rows.


```sql
mysql> USE world;
mysql> SELECT ID, CountryCode, country.Code, Code2 FROM city INNER JOIN country ON country.Code = city.CountryCode;
```

Unlikely to be any results returned by adding just 20 rows; perhaps one 'lucky' row returned for this database in 100 rows added.

```php
$config = [
    ...
    'num_rows' => 1,
    'FK_jumble' => true,
```

Execute *runner.php*

    Tables of 'world' populated with 1 rows.
    CountryCode key jumbled in table 'city'
    CountryCode key jumbled in table 'countrylanguage'


```sql
mysql> SELECT ID, CountryCode, country.Code FROM city INNER JOIN country ON country.Code = city.CountryCode;

+----+-------------+------+
| ID | CountryCode | Code |
+----+-------------+------+
|  8 | AWO         | AWO  |
| 16 | MGU         | MGU  |
+----+-------------+------+
```

And for a hundred rows added with `FK_jumble` enabled, there will be a few more matches:

```sql
SELECT ID, CountryCode, country.Code FROM city INNER JOIN country ON country.Code = city.CountryCode WHERE CountryCode = 'AZP';
+----+-------------+------+
| ID | CountryCode | Code |
+----+-------------+------+
|  2 | AZP         | AZP  |
|  8 | AZP         | AZP  |
...
+----+-------------+------+
25 rows in set (0.00 sec)
```

Meaningless data, but the foreign keys are starting to gain relationships, and so SQL joins between tables are now realised. More rows can be added to increase the number of results.


## Speed

There is almost no optimisation. Speed never was on the agenda, and if it were, I would have chosen the wrong language.

I load 100 to 1,000 database rows and the key jumble facilitates SQL joins in the junk data.

For serious speed, there's Percona's Go-based [mysql_random_data_load](https://github.com/Percona-Lab/mysql_random_data_load). Currently, this tool fills one table at a time &ndash; fast &ndash; yet somewhat laboriously for databases with lots of tables, whereas I wanted all database tables populated with one command.


## MariaDB

MariaDB has limited support.  
Simple schemas such as [*world*](https://dev.mysql.com/doc/index-other.html) and [*basketball*](https://github.com/Tinram/Database-Filler/blob/master/basketball.sql) work fine in MariaDB.  
However, other schemas, ranging from simple to complex, which import and run seamlessly in MySQL versions 5.7 and 8.0, can really throw MariaDB.


## Other

Tested using MySQL 5.7 and 8.0

This package is a beta. It's fit for my purpose (I have run it on interesting proprietary schemas (e.g. with single-bit columns), as well as the example databases). But it cannot hope to support all variations (good and bad) of MySQL schemas.


## License

MySQL Filler is released under the [GPL v.3](https://www.gnu.org/licenses/gpl-3.0.html).
