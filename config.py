#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import MySQLdb
import MySQLdb.cursors


""" Configuration options for MySQL-Filler. """

                                           # POPULATE

NUM_ROWS = 10                              # number of rows to add to all database tables
PROCS = 1                                  # number of processes to spawn

JUMBLE_FKS = True                          # toggle random jumbling of foreign keys for joins
FK_PCT_REPLACE = 25                        # percentage of NUM_ROWS of foreign keys to jumble

STRICT_INSERT = False                      # toggle INSERT IGNOREs for duplicate hits / bypass strict SQL mode (warnings versus errors)

PROCESS_INT_FKS = True                     # default: True; process (True) or skip (False) integer foreign keys (TPCC schema with tinyint PKs)
COMPOSITE_PK_INCREMENT = False             # default: False; skip (False) or increment (True) composite primary keys (TPCC schema)

COMPLEX_JSON = False                       # False for simple fixed JSON data; True to generate variable JSON data

BYTES_DECODE = 'utf-8'                     # character set used for byte data type conversion
MAX_PACKET = False                         # True maximises the packet size (root user only)

DEBUG = False                              # debug output toggle
EXTENDED_DEBUG = False                     # verbose debug output toggle

TRUNCATE_TABLES = False                    # toggle truncation of all database tables


                                           # DATABASE
DB_CONFIG = dict(
    user    = 'general',                   # USERNAME
    passwd  = 'P@55w0rd',                  # USER PASSWORD
    host    = 'localhost',                 # HOST
    db      = 'basketball',                # DATABASE NAME
    port    = 3306,
    #charset = 'utf8mb4',                  # force character set (beware binary data types)
    cursorclass=MySQLdb.cursors.DictCursor
)
