#!/usr/bin/env php
<?php

/**
    * Example to set-up and call MySQL-Filler
    * Martin Latter, 22/10/2021
*/

declare(strict_types=1);

date_default_timezone_set('Europe/London');

use Filler\Connect;
use Filler\CharGenerator;
use Filler\Filler;

require 'src/autoloader.php';


$config =
[
    # number of rows to add to all database tables
    'num_rows' => 10,

    # foreign key jumble
    'FK_jumble' => true,
    'FK_percent_replacement' => 25,

    # database credentials
    'host'     => 'localhost',
    'database' => 'world',
    'username' => 'general',
    'password' => 'P@55w0rd',
    'charset'  => 'utf8',

    # row progress percentage
    'row_counter_threshold' => 10,

    'debug' => false,

    'truncate' => false
];


$db = Connect::getInstance($config);
$fill = new Filler($db, $config);
echo $fill->displayMessages();