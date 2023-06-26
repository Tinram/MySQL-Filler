#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
    Populate MySQL database tables.
"""


import binascii
import datetime
import json
import math
import multiprocessing as mp
import random
import re
import string
import sys
import time
import uuid

from config import *


class MySQLFiller():

    """
        MySQLFiller
        Populate all tables of a MySQL database and optionally jumble foreign keys.

        Author         Martin Latter
        Copyright      Martin Latter 16/12/2021
        Version        0.31
        License        GPL version 3.0 (GPL v3); https://www.gnu.org/licenses/gpl.html
        Link           https://github.com/Tinram/MySQL-Filler.git
    """


    foreign_keys = []
    start_year = 1970
    end_year = datetime.date.today().year


    def __init__(self):

        """ Initialise and execute methods. """
        self.get_foreign_keys()
        self.process()


    def process(self):

        """ Query and start allocate processing of database tables. """

        start = time.time()
        results = ()
        tables = []

        tables_query = """
            SELECT
                table_name
            FROM
                information_schema.TABLES
            WHERE
                TABLE_SCHEMA = '%s'
            AND
                TABLE_TYPE = 'BASE TABLE'
            """ % (DB_CONFIG['db'])

        with CONN.cursor() as cursor:
            cursor.execute(tables_query)
            results = cursor.fetchall()

        if not results:
            print('The `' + DB_CONFIG['db'] + '` database appears to contain no tables!')
            sys.exit(1)

        for table_name in results:
            for k in table_name:
                tables.append(table_name[k])

        if TRUNCATE_TABLES:

            print('Truncating all tables of `' + DB_CONFIG['db'] + '` database ...')
            with CONN.cursor() as cursor:
                for table in tables:
                    try:
                        trc = cursor.execute('TRUNCATE TABLE `' + table + '`')
                        if trc != 0:
                            print('truncation failed for `' + table + '`')
                        else:
                            print('truncated table `' + table + '`')
                    except MySQLdb.Error as err:
                        print(err)
        else:

            print(DB_CONFIG['host'])
            print(DB_CONFIG['db'])
            print('+' + str(NUM_ROWS) + ' rows\n')

            with mp.Pool(processes=PROCS) as pool:
                results = pool.map(self.worker, tables)

            if JUMBLE_FKS:
                if self.foreign_keys:
                    self.jumble_foreign_keys()
                else:
                    print('\nno explicit foreign keys to jumble')

        print('\n' + format((time.time() - start), ".3f") + 's')

        if CONN is not None:
            CONN.close()


    def worker(self, table):

        """ Worker process. """

        column_query = """
            SELECT
                TABLE_NAME,
                COLUMN_NAME,
                DATA_TYPE,
                CHARACTER_MAXIMUM_LENGTH,
                NUMERIC_PRECISION,
                NUMERIC_SCALE,
                DATETIME_PRECISION,
                COLUMN_TYPE,
                COLUMN_KEY,
                EXTRA
            FROM
                information_schema.COLUMNS
            WHERE
                TABLE_SCHEMA = '%s'
            AND
                TABLE_NAME = '%s'
            """ % (DB_CONFIG['db'], table)

        with CONN.cursor() as cursor:
            cursor.execute(column_query)
            column_results = cursor.fetchall()

        cols = []
        placeholder = []
        params = []

        table_name = ''

        for tab_dat in column_results:

            table_name = tab_dat['TABLE_NAME']
            data_type = tab_dat['DATA_TYPE']

            if isinstance(data_type, bytes): # MySQL 8
                data_type = data_type.decode(BYTES_DECODE)

            # skip auto-generated primary keys
            if tab_dat['COLUMN_KEY'] == 'PRI':

                if tab_dat['EXTRA'] == 'auto_increment' and len(column_results) != 1:
                    continue
                if tab_dat['EXTRA'] == 'DEFAULT_GENERATED':
                    continue

            # skip spatial types
            if data_type in ['geometry', 'point', 'linestring', 'polygon', 'multipoint', 'multilinestring', 'multipolygon', 'geometrycollection']:
                continue

            cols.append(tab_dat['COLUMN_NAME'])
            placeholder.append('%s')
            dtype = ()

            # strings
            if data_type in ['char', 'varchar']:

                length = 255 if tab_dat['CHARACTER_MAXIMUM_LENGTH'] > 255 else tab_dat['CHARACTER_MAXIMUM_LENGTH']

                if tab_dat['COLUMN_KEY'] == 'PRI' or tab_dat['COLUMN_KEY'] == 'UNI': # character primaries
                    dtype = ('ck', length, [tab_dat['TABLE_NAME'], tab_dat['COLUMN_NAME']])
                else:
                    dtype = ('s', length)

            elif data_type in ['text', 'tinytext', 'mediumtext', 'longtext']:
                dtype = ('s', 255)

            # integers
            elif 'int' in data_type:

                if PROCESS_INT_FKS and tab_dat['COLUMN_NAME'] in self.foreign_keys:

                    last_fk_value = """
                        SELECT `%s`
                        FROM `%s`
                        ORDER BY `%s`
                        DESC LIMIT 1
                        """ % (
                            self.foreign_keys[tab_dat['COLUMN_NAME']]['column'],
                            self.foreign_keys[tab_dat['COLUMN_NAME']]['table'],
                            self.foreign_keys[tab_dat['COLUMN_NAME']]['column']
                        )

                    with CONN.cursor() as cursor:
                        cursor.execute(last_fk_value)
                        fk_result = cursor.fetchall()

                    if not fk_result:
                        if tab_dat['COLUMN_KEY'] == 'PRI':
                            dtype = ('ipk', 0)
                        else:
                            dtype = ('ifk1', 1)
                    else:
                        val = int(fk_result[0][self.foreign_keys[tab_dat['COLUMN_NAME']]['column']])

                        if tab_dat['COLUMN_KEY'] == 'PRI':
                            dtype = ('ipk', val)
                        else:
                            dtype = ('ifkm', val)

                else:
                    if data_type == 'int':
                        if 'unsigned' in tab_dat['COLUMN_TYPE']:
                            dtype = ('i', 0, 4294967295)
                        else:
                            dtype = ('i', -2147483648, 2147483647)

                    elif data_type == 'tinyint':
                        if 'unsigned' in tab_dat['COLUMN_TYPE']:
                            dtype = ('i', 0, 255)
                        else:
                            dtype = ('i', -127, 127)

                    elif data_type == 'smallint':
                        if 'unsigned' in tab_dat['COLUMN_TYPE']:
                            dtype = ('i', 0, 65535)
                        else:
                            dtype = ('i', -32768, 32767)

                    elif data_type == 'mediumint':
                        if 'unsigned' in tab_dat['COLUMN_TYPE']:
                            dtype = ('i', 0, 16777215)
                        else:
                            dtype = ('i', -8388608, 8388607)

                    elif data_type == 'bigint':
                        if 'unsigned' in tab_dat['COLUMN_TYPE']:
                            dtype = ('i', 0, 18446744073709551615)
                        else:
                            dtype = ('i', -9223372036854775808, 9223372036854775807)

            # floats
            elif data_type in ['float', 'double']:

                dec_pl = int(tab_dat['NUMERIC_SCALE']) if tab_dat['NUMERIC_SCALE'] else 1
                sign = False if 'unsigned' in tab_dat['COLUMN_TYPE'] else True

                if isinstance(data_type, float): # catch Joomla's 'NoneType'
                    length = int(tab_dat['NUMERIC_PRECISION']) - int(tab_dat['NUMERIC_SCALE'])
                    if length > 6:
                        length = length * (1000 - 1)
                    dtype = ('f', length, dec_pl, sign)

                elif 'unsigned' in tab_dat['COLUMN_TYPE']:
                    length = 1
                    if tab_dat['NUMERIC_PRECISION'] and tab_dat['NUMERIC_SCALE']:
                        length = int(tab_dat['NUMERIC_PRECISION']) - int(tab_dat['NUMERIC_SCALE'])
                    dtype = ('f', length, dec_pl, sign)

                else:
                    dtype = ('f', 10, dec_pl, False)

            elif data_type == 'decimal':
                dtype = ('dc', tab_dat['NUMERIC_SCALE'])

            # date-time
            elif data_type == 'date':
                dtype = ('d', 0)
            elif data_type == 'year':
                dtype = ('y', 0)
            elif data_type == 'datetime':
                dtype = ('dt', tab_dat['DATETIME_PRECISION'])
            elif data_type == 'timestamp':
                dtype = ('ts', 0)
            elif data_type == 'time':
                dtype = ('tt', tab_dat['DATETIME_PRECISION'])

            # others
            elif data_type in ['enum', 'set']:
                ef_choices = re.findall(r"\'([\w\-\s]+)\'", tab_dat['COLUMN_TYPE'])
                dtype = ('enum', ef_choices)
            elif data_type == 'bit':
                dtype = ('bit', 0)
            elif data_type == 'json':
                dtype = ('json', 0)
            elif data_type in ['tinyblob', 'blob', 'mediumblob', 'longblob']:
                dtype = ('blob', 0)
            elif data_type in ['binary', 'varbinary']:
                # uuid
                if tab_dat['CHARACTER_MAXIMUM_LENGTH'] == 16:
                    dtype = ('uuid', 0)
                else:
                    length = tab_dat['CHARACTER_MAXIMUM_LENGTH']
                    dtype = ('bin', length)

            # not supported
            else:
                print('** unknown data type: ' + data_type)

            params.append(dtype)

        ignore = 'IGNORE' if not STRICT_INSERT else ''

        insert = """
            INSERT %s INTO `%s`
                (%s)
            VALUES
                (%s)
            """ % (ignore, table_name, ','.join(['`{0}`'.format(c) for c in cols]), ','.join(['{0}'.format(d) for d in placeholder]))

        if DEBUG:
            print(insert)
            print(params)

        if table_name != '':

            with CONN.cursor() as cursor:

                values = []
                i_val = 0
                inc = False

                for _ in range(NUM_ROWS):

                    row = []

                    for param in params:

                        if param[0] == 's':
                            val = self.gen_string(param[1])

                        elif param[0] == 'uuid':
                            val = uuid.uuid4().bytes # big endian (else: .bytes_le)

                        elif param[0] == 'ifk1':
                            val = param[1]

                        elif param[0] == 'ipk':
                            if not inc:
                                i_val = self.gen_inc_int(param[1])
                                inc = True
                            else:
                                i_val = self.gen_inc_int(i_val)

                            val = i_val

                        elif param[0] == 'ifkm':

                            if not COMPOSITE_PK_INCREMENT:
                                val = param[1]
                            else:
                                if not inc:
                                    i_val = param[1]
                                    inc = True
                                else:
                                    i_val = self.gen_inc_int(i_val)

                                val = i_val

                        elif param[0] == 'ck': # char key
                            val = self.gen_char_key(param[1], param[2])
                        elif param[0] == 'i':
                            val = self.gen_int(param[1], param[2])
                        elif param[0] == 'f':
                            val = self.gen_float(param[1], param[2], param[3])
                        elif param[0] == 'dc':
                            val = self.gen_decimal(param[1])
                        elif param[0] == 'd':
                            val = self.gen_date()
                        elif param[0] == 'y':
                            val = self.gen_year()
                        elif param[0] == 'dt':
                            val = self.gen_datetime(param[1])
                        elif param[0] == 'ts':
                            val = self.gen_datetime(param[1])
                        elif param[0] == 'tt':
                            val = self.gen_time(param[1])
                        elif param[0] == 'enum':
                            val = random.choice(param[1])
                        elif param[0] == 'bit':
                            val = '\x01'
                        elif param[0] == 'blob':
                            val = bin(291)
                        elif param[0] == 'bin':
                            binv = self.gen_bin(param[1])
                            val = binv
                        elif param[0] == 'json':
                            if not COMPLEX_JSON:
                                val = json.dumps({'json':'foobar'})
                            else:
                                json_tmp = {
                                    'city': self.gen_city_json(12),
                                    'state': self.gen_state_json(2),
                                    'zips': self.gen_zip_json(1000, 99950, 5)
                                }
                                val = json.dumps(json_tmp, sort_keys=True)

                        row.append(val)

                    values.append(row)

                    if EXTENDED_DEBUG:
                        print(row)

                try:
                    cursor.executemany(insert, values)
                    CONN.commit()
                    print('`' + table + '`')

                except MySQLdb.Error as err:
                    CONN.rollback()
                    if STRICT_INSERT:
                        print('** `' + table + '` not populated')
                        print(err)
                    if DEBUG:
                        print('rolled back `' + table + '`')
                        print(err)


    def gen_string(self, length):
        """ Generate random character string of specified length. """
        return ''.join(random.choice(string.ascii_uppercase + string.ascii_lowercase) for _ in range(length)) # 3.5-
        # ''.join(random.choices(string.ascii_lowercase + string.ascii_lowercase, k=length)) # 3.6+


    def gen_int(self, start, end):
        """ Generate integer to length. """
        return random.randint(start, end)


    def gen_inc_int(self, val):
        """ Generate incremental integer. """
        return val + 1


    def gen_decimal(self, dec_places=2):
        """ Generate float to DP. """
        return round(random.uniform(10, 99), dec_places) # for world DB
        # return round(random.uniform(-100, 2000), dp)


    def gen_float(self, end, dec_places, signed):
        """ Generate un/signed float to DP. """
        start = -99 if signed else 0
        return round(random.uniform(start, end), dec_places)


    def gen_year(self):
        """ Generate random year. """
        return random.randint(self.start_year, self.end_year)


    def gen_date(self):
        """ Generate random date. """
        return datetime.datetime(random.randint(self.start_year, self.end_year), random.randint(1, 12), random.randint(1, 28))


    def gen_datetime(self, length):
        """ Generate random datetime, and timestamp fraction if specified. """
        fraction = 0
        if length > 0:
            # create reversed zero-filled string for fraction format
            fra1 = ''.join(random.choice(string.digits) for _ in range(length))
            fra2 = fra1.zfill(6)
            fra3 = fra2[::-1]
            fraction = int(fra3)
        return datetime.datetime(random.randint(self.start_year, self.end_year), random.randint(1, 12), random.randint(1, 28), random.randint(1, 23), random.randint(0, 59), random.randint(0, 59), fraction)


    def gen_time(self, length):
        """ Generate random timestamp, and timestamp fraction if specified. """
        fraction = 0
        if length > 0:
            fra1 = ''.join(random.choice(string.digits) for _ in range(length))
            fra2 = fra1.zfill(6)
            fra3 = fra2[::-1]
            fraction = int(fra3)
        return datetime.time(random.randint(1, 23), random.randint(0, 59), random.randint(0, 59), fraction)


    def gen_bin(self, length):
        """ Generate binary-compatible string to length. """
        if length > 255:
            length = 255
        chars = ''.join(random.choice(string.punctuation) for _ in range(length))
        return binascii.a2b_qp(chars)


    def gen_char_key(self, length, cols):
        """ Generate string keys while avoiding duplicates. """

        new_key = ''.join(random.choice(string.ascii_uppercase + string.ascii_lowercase) for _ in range(length))

        sql_keys = """
            SELECT `%s`
            FROM `%s`
            WHERE `%s` = '%s'
            LIMIT 1
            """ % (
                cols[1],
                cols[0],
                cols[1],
                new_key
            )

        with CONN.cursor() as cursor:
            cursor.execute(sql_keys)
            key_lookup = cursor.fetchall()

        if key_lookup:
            # not bullet-proof, processes can collide
            while True:
                new_key = self.gen_string(length)
                if new_key != key_lookup[0][cols[1]]:
                    break

        return new_key


    def gen_city_json(self, length):
        """ Generate gibberish city names for JSON string. """
        return ''.join(random.choice(string.ascii_lowercase) for _ in range(length)).title()


    def gen_state_json(self, length):
        """ Generate pseudo state acronyms for JSON string. """
        return ''.join(random.choice(string.ascii_uppercase) for _ in range(length))


    def gen_zip_json(self, start, end, num):
        """ Generate pseudo zips for JSON string. """
        zips = []
        for _ in range(num):
            zips.append(random.randint(start, end))
        return zips


    def get_foreign_keys(self):

        """ Populate foreign key array from database tables. """

        fk_query = """
            SELECT
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM
                information_schema.KEY_COLUMN_USAGE
            WHERE
                REFERENCED_TABLE_SCHEMA = '%s'
            """ % (DB_CONFIG['db'])

        with CONN.cursor() as cursor:
            cursor.execute(fk_query)
            fks_results = cursor.fetchall()

        fks = {}

        for tab_attrib in fks_results:
            fks[tab_attrib['COLUMN_NAME']] = {
                'table': tab_attrib['REFERENCED_TABLE_NAME'],
                'column': tab_attrib['REFERENCED_COLUMN_NAME']
            }

        self.foreign_keys = fks


    def jumble_foreign_keys(self):

        """ Jumble foreign keys. """

        fk_query = """
            SELECT
                TABLE_NAME 'table',
                COLUMN_NAME 'fk_column',
                REFERENCED_TABLE_NAME 'ref_table',
                REFERENCED_COLUMN_NAME 'ref_table_key'
            FROM
                information_schema.KEY_COLUMN_USAGE
            WHERE
                REFERENCED_TABLE_SCHEMA = '%s'
            """ % (DB_CONFIG['db'])

        with CONN.cursor() as cursor:
            cursor.execute(fk_query)
            key_results = cursor.fetchall()

        table_keys = {}

        for k_data in key_results:
            table_keys[k_data['table']] = {
                'fk_column': k_data['fk_column'],
                'ref_table': k_data['ref_table'],
                'ref_table_key': k_data['ref_table_key']
            }

        uk_query = """
            SELECT
                CONSTRAINT_NAME
            FROM
                information_schema.TABLE_CONSTRAINTS
            WHERE
                CONSTRAINT_SCHEMA = '%s'
            AND
                CONSTRAINT_TYPE = 'UNIQUE'
            """ % (DB_CONFIG['db'])

        with CONN.cursor() as cursor:
            cursor.execute(uk_query)
            unique_results = cursor.fetchall()

        unique_keys = []

        for col_val in unique_results:
            for _, u_key in col_val.items():
                unique_keys.append(u_key)

        limit = str(math.ceil(NUM_ROWS * (FK_PCT_REPLACE / 100)))
        error = False
        error_tables = []

        for table_name, tdata in table_keys.items():

            key_query = """
                SELECT `%s`
                FROM `%s`
                ORDER BY RAND()
                LIMIT %s
                """ % (
                    tdata['ref_table_key'],
                    tdata['ref_table'],
                    limit
                )

            if EXTENDED_DEBUG:
                print(key_query)

            with CONN.cursor() as cursor:
                cursor.execute(key_query)
                results = cursor.fetchall()

            if not results:
                print('\njumble_foreign_keys() failed for table: `' + table_name + '`')
                continue

            with CONN.cursor() as cursor:

                for col_val in results:

                    for _, val in col_val.items():

                        if tdata['fk_column'] in unique_keys: # avoid duplicates (re:shipapp)
                            continue

                        key_update = """
                            UPDATE `%s`
                            SET `%s` = '%s'
                            ORDER BY RAND()
                            LIMIT %s
                            """ % (
                                table_name,
                                tdata['fk_column'],
                                str(val),
                                limit
                            )

                        if EXTENDED_DEBUG:
                            print(key_update)

                        upresult = cursor.execute(key_update)
                        if not upresult:
                            error = True
                            error_tables.append(table_name)

        if error:
            print('\nforeign key jumbling denied in tables: ' + ','.join(error_tables) + ' (check UPDATE GRANT for user)')
        else:
            print('\nforeign keys jumbled')

# end class


# Ugly database connect – for spawned processes requiring shared global object.
try:
    CONN = None
    CONN = MySQLdb.connect(**DB_CONFIG)
    with CONN.cursor() as cursor:
        cursor.execute('SET SESSION foreign_key_checks = OFF')
        cursor.execute('SET SESSION unique_checks = OFF')
        # cursor.execute('SET sql_mode=(SELECT CONCAT(@@session.sql_mode, ",ALLOW_INVALID_DATES"))')
        if DB_CONFIG['user'] == 'root' and MAX_PACKET:
            cursor.execute('SET GLOBAL max_allowed_packet = 268435456')
except MySQLdb.Error as err:
    print('Failed to connect to database: (%d) %s' % (err.args[0], err.args[1]))
    print('Check database name and database access privileges.')
    sys.exit(1)


def main():

    """ Set multiprocessing type then invoke class. """

    mp.set_start_method('spawn') # 'fork' overrides 'spawn' default of MacOS Py3.8
    MySQLFiller()


if __name__ == '__main__':
    main()
