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

STRICT_INSERT = False                      # toggle INSERT IGNOREs for duplicate hits (warnings rather than fails)

PROCESS_INT_FKS = True                     # default: True;  False for TPCC with tinyints etc in primary keys
COMPOSITE_PK_INCREMENT = False             # default: False; True for TPCC example of composite primary keys

BYTES_DECODE = 'utf-8'                     # character set used for byte data type conversion
MAX_PACKET = False                         # when root user, maximise the packet size

DEBUG = False                              # debug toggle
EXTENDED_DEBUG = False                     # further debug toggle

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
