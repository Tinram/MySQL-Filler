<?php

declare(strict_types=1);

namespace Filler;

final class Filler
{
    /**
        * Populate a database and create pseudo foreign key relationships.
        *
        * @author          Martin Latter
        * @copyright       Martin Latter 22/10/2021
        * @version         0.28
        * @license         GNU GPL version 3.0 (GPL v3); http://www.gnu.org/licenses/gpl.html
        * @link            https://github.com/Tinram/MySQL_Filler.git
        * @package         Filler
    */

    /** @var boolean $bDebug, debug output toggle */
    private $bDebug = false;

    /** @var object $db */
    private $db;

    /** @var integer $iNumRows, number of rows to insert */
    private $iNumRows = 1;

    /** @var boolean $bJumbleForeignKeys, jumble foreign keys */
    private $bJumbleForeignKeys = false;

    /** @var integer $iForeignKeyPercentReplace, % of number of rows added FKs to replace */
    private $iForeignKeyPercentReplace = 20;

    /** @var integer $iCLIRowCounter, CLI usage: rows of SQL generated before displaying progress percentage */
    private $iCLIRowCounter = 1000;

    /** @var array<string> $aIgnoreTables, names of problem tables to skip processing */
    private $aIgnoreTables = [];

    /** @var array<array> $aFKs, foreign keys */
    private $aFKs = [];

    /** @var bool $bTruncate, toggle to truncate all database tables */
    private $bTruncate = false;

    /** @var array<string|null> $aMessages */
    private $aMessages = [];

    /** @var bool $bErrors, error flag */
    private $bErrors = false;

    /** @var string $sLineBreak */
    private $sLineBreak = PHP_EOL;

    /**
        * Constructor: set-up configuration class variables.
        *
        * @param   array<mixed> $aConfig, configuration details
    */
    public function __construct(Connect $oDB, array $aConfig)
    {
        $this->db = $oDB;

        if (isset($aConfig['debug']))
        {
            $this->bDebug = (bool) $aConfig['debug'];
        }

        if (isset($aConfig['num_rows']))
        {
            $this->iNumRows = (int) $aConfig['num_rows'];
        }

        if (isset($aConfig['FK_jumble']))
        {
            $this->bJumbleForeignKeys = (bool) $aConfig['FK_jumble'];
        }

        if (isset($aConfig['FK_percent_replacement']))
        {
            $this->iForeignKeyPercentReplace = (int) $aConfig['FK_percent_replacement'];
        }

        if (isset($aConfig['truncate']))
        {
            $this->bTruncate = (bool) $aConfig['truncate'];
        }

        if (isset($aConfig['row_counter_threshold']))
        {
            $this->iCLIRowCounter = (int) $aConfig['row_counter_threshold'];
        }

        if (isset($aConfig['ignore_tables']))
        {
            $this->aIgnoreTables = $aConfig['ignore_tables'];
        }

        $sTableQ = '
            SELECT
                table_name
            FROM
                information_schema.tables
            WHERE
                TABLE_SCHEMA = "' . $this->db->dbname . '"
            AND
                TABLE_TYPE = "BASE TABLE"';

        $rR = $this->db->conn->query($sTableQ);

        if ($rR->num_rows === 0)
        {
            $this->aMessages[] = "The '" . $this->db->dbname . "' database appears to contain no tables!";
            return;
        }

        $aT = $rR->fetch_all();

        $aTables = array_merge(...$aT);

        $this->db->conn->query('SET SESSION foreign_key_checks = OFF');
        $this->db->conn->query('SET SESSION unique_checks = OFF');

        if ($this->bTruncate === true)
        {
            $this->truncateTables($aTables);
            return;
        }
        else
        {
            $this->getForeignKeys();

            $this->processTables($aTables);

            if ($this->bJumbleForeignKeys === true)
            {
                $this->jumbleForeignKeys();
            }
        }
    }

    /**
        * Truncate all tables of database.
        *
        * @param   array<string> $aTables, tables
        *
        * @return  void
    */
    private function truncateTables(array $aTables): void
    {
        foreach ($aTables as $sT)
        {
            $sQ = 'TRUNCATE TABLE ' . $sT;
            $rE = $this->db->conn->query($sQ);
        }

        $this->aMessages[] ="Truncated all tables of '" . $this->db->dbname . "' database!";
    }

    /**
        * Get foreign key relationships of database.
        *
        * @return  void
    */
    private function getForeignKeys(): void
    {
        $sFKs = '
            SELECT
                COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM
                information_schema.KEY_COLUMN_USAGE
            WHERE
                REFERENCED_TABLE_SCHEMA = "' . $this->db->dbname . '"';

        $rR = $this->db->conn->query($sFKs);
        $aFK = $rR->fetch_all(MYSQLI_ASSOC);

        foreach ($aFK as $aRow)
        {
            $this->aFKs[$aRow['COLUMN_NAME']] =
            [
                'table' => $aRow['REFERENCED_TABLE_NAME'],
                'column' => $aRow['REFERENCED_COLUMN_NAME']
            ];
        }
    }

    /**
        * Process tables, filling with junk data.
        *
        * @param   array<string> $aTables, tables
        *
        * @return  void
    */
    private function processTables(array $aTables): void
    {
        for ($i = 1; $i <= $this->iNumRows; $i++)
        {
            foreach ($aTables as $sTable)
            {
                if ($this->bDebug === true)
                {
                    echo 'processing table ' . $sTable . ' ...' . PHP_EOL;
                }

                if (count($this->aIgnoreTables) !== 0)
                {
                    if (in_array($sTable, $this->aIgnoreTables))
                    {
                        if ($this->bDebug === true)
                        {
                            echo '** ignoring table ' . $sTable . ' **' . PHP_EOL;
                        }

                        continue;
                    }
                }

                # get column attributes
                $aColAttributes = [];

                $sColAtts = '
                    SELECT
                        COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, COLUMN_TYPE, COLUMN_KEY, EXTRA
                    FROM
                        information_schema.COLUMNS
                    WHERE
                        TABLE_SCHEMA = "' . $this->db->dbname . '"
                    AND
                        TABLE_NAME = "' . $sTable . '"';

                $rResult = $this->db->conn->query($sColAtts);

                while ($aRow = $rResult->fetch_assoc())
                {
                    $iMaxLen = 0;
                    $aEnumFields = [];
                    $sSign = (strpos($aRow['COLUMN_TYPE'], 'unsigned') !== false) ? '+' : '';

                    switch ($aRow['DATA_TYPE'])
                    {
                        case 'int':
                            $iMaxLen = 9;
                        break;

                        case 'tinyint':
                            $iMaxLen = 2;
                        break;

                        case 'smallint':
                            $iMaxLen = 4;
                        break;

                        case 'mediumint':
                            $iMaxLen = 6;
                        break;

                        case 'bigint':
                            $iMaxLen = 19;
                        break;

                        case 'tinytext':
                        case 'char':
                            $iMaxLen = ((int) $aRow['CHARACTER_MAXIMUM_LENGTH']);
                        break;

                        # limit large text datatype length
                        case 'varchar':
                            if (((int) $aRow['CHARACTER_MAXIMUM_LENGTH']) > 255)
                            {
                                $iMaxLen = 255;
                            }
                            else
                            {
                                $iMaxLen = ((int) $aRow['CHARACTER_MAXIMUM_LENGTH']);
                            }
                        break;

                        case 'text':
                        case 'mediumtext':
                        case 'longtext':
                            $iMaxLen = 255;
                        break;
                        ##

                        case 'date':
                        case 'datetime':
                        case 'timestamp':
                            $iMaxLen = 0;
                        break;

                        case 'time':
                            $iMaxLen = 10;
                        break;

                        case 'year':
                            $iMaxLen = 4;
                        break;

                        case 'decimal':
                            $iMaxLen = (int) ($aRow['NUMERIC_PRECISION']);
                        break;

                        case 'float':
                        case 'double':
                            $iMaxLen = ((int) $aRow['NUMERIC_PRECISION'] - (int) $aRow['NUMERIC_SCALE']);
                        break;

                        case 'enum':
                        case 'set':
                            $rxEnum = preg_match_all("/'([\w\-\s]+)'/", $aRow['COLUMN_TYPE'], $aEnums);
                            $aEnumFields = $aEnums[1];
                        break;

                        case 'tinyblob':
                        case 'blob':
                        case 'mediumblob':
                        case 'longblob':
                            $iMaxLen = 39;
                        break;

                        case 'binary':
                        case 'varbinary':
                            if (((int) $aRow['CHARACTER_MAXIMUM_LENGTH']) < 39)
                            {
                                $iMaxLen = ((int) $aRow['CHARACTER_MAXIMUM_LENGTH']);
                            }
                            else
                            {
                                $iMaxLen = 39;
                            }
                        break;

                        case 'json':
                            $iMaxLen = 0;
                        break;

                        case 'bit':
                            $iMaxLen = 0;
                        break;

                        default:
                            $this->bErrors = true;
                            $this->aMessages[] = '*** UNSUPPORTED DATATYPE *** : ' . $aRow['DATA_TYPE'];
                            $iMaxLen = 0;
                    }

                    $aColAttributes[$aRow['COLUMN_NAME']] =
                    [
                        'data_type' => $aRow['DATA_TYPE'],
                        'max_length' => $iMaxLen,
                        'precision' => (int) $aRow['NUMERIC_SCALE'],
                        'sign' => $sSign,
                        'key' => $aRow['COLUMN_KEY'],
                        'extra' => $aRow['EXTRA'],
                        'enum_vals' => $aEnumFields
                    ];
                }

                ############################################################

                $lt = $this->db->conn->query('LOCK TABLES ' . $sTable . ' WRITE');

                $this->db->conn->begin_transaction();

                $aDBCols = [];
                $aData = [];
                $aPlaceholders = [];
                $aColumnNames = [];

                foreach ($aColAttributes as $sColumnName => $v)
                {
                    if ($v['key'] === 'PRI')
                    {
                        if ($v['extra'] === 'auto_increment' && count($aColAttributes) !== 1) # skip auto-incrementing PK column
                        {
                            continue;
                        }

                        if ($v['extra'] === 'DEFAULT_GENERATED')
                        {
                            continue;
                        }
                    }

                    $sType = 's'; # default parameter type

                    # act on column name hints first
                    if (strpos($sColumnName, 'name') !== false && strpos($v['data_type'], 'int') === false)
                    {
                        if ((strpos($sColumnName, 'first') !== false) || (strpos($sColumnName, 'last') !== false) || (strpos($sColumnName, 'user') !== false)) # avoid style_name etc
                        {
                            if ($v['max_length'] > 14)
                            {
                                $aDBCols[$sColumnName] = CharGenerator::generateName(14, 'gibberish');
                            }
                            else
                            {
                                $aDBCols[$sColumnName] = CharGenerator::generateName($v['max_length'], 'gibberish');
                            }
                        }
                        else
                        {
                            $aDBCols[$sColumnName] = CharGenerator::generateText($v['max_length'], 'alpha');
                        }
                    }
                    else if (strpos($sColumnName, 'email') !== false && strpos($v['data_type'], 'char') !== false)
                    {
                        $aDBCols[$sColumnName] = CharGenerator::generateEmail(10, 'gibberish');
                    }
                    else if (strpos($sColumnName, 'phone') !== false && strpos($v['data_type'], 'int') === false)
                    {
                        $aDBCols[$sColumnName] = CharGenerator::generateNumber(14);
                    }
                    else if ((strpos($sColumnName, 'update') === false) && (strpos($sColumnName, 'date') !== false))
                    {
                        $aDBCols[$sColumnName] = CharGenerator::generateDate(5, 'date', 'day');
                    }
                    else if (strpos($v['data_type'], 'int') !== false)
                    {
                        $sType = 'i';

                        if (isset($this->aFKs[$sColumnName])) # in FK array
                        {
                            $sLastFKValue = '
                                SELECT `' . $this->aFKs[$sColumnName]['column'] . '`
                                FROM `' . $this->aFKs[$sColumnName]['table'] . '`
                                ORDER BY `' . $this->aFKs[$sColumnName]['column'] . '` DESC
                                LIMIT 1';

                            $rR = $this->db->conn->query($sLastFKValue);

                            if ($rR->num_rows === 0)
                            {
                                $aDBCols[$sColumnName] = 1;
                            }
                            else
                            {
                                $aR = $rR->fetch_row();
                                $aDBCols[$sColumnName] = ((int) $aR[0]) + 1;
                            }
                        }
                        else
                        {
                            $aDBCols[$sColumnName] = (int) CharGenerator::generateNumber($v['max_length']);
                        }
                    }
                    else if ($v['data_type'] === 'char' || $v['data_type'] === 'varchar')
                    {
                        $sStyleType = ($v['max_length'] < 4) ? 'alpha_upper' : 'alpha';

                        if ($v['key'] === 'PRI') # character primaries
                        {
                            $aDBCols[$sColumnName] = $this->avoidDupes($sColumnName, $sTable, $v['max_length'], $sStyleType);
                        }
                        else if (isset($this->aFKs[$sColumnName]))
                        {
                            $sLastFKValue = '
                                SELECT `' . $this->aFKs[$sColumnName]['column'] . '`
                                FROM `' . $this->aFKs[$sColumnName]['table'] . '`
                                ORDER BY `' . $this->aFKs[$sColumnName]['column'] . '` DESC
                                LIMIT 1';

                            $rR = $this->db->conn->query($sLastFKValue);

                            if ($rR->num_rows === 0)
                            {
                                $aDBCols[$sColumnName] = CharGenerator::generateText($v['max_length'], $sStyleType);
                            }
                            else
                            {
                                $aDBCols[$sColumnName] = $this->avoidDupes($sColumnName, $sTable,$v['max_length'], $sStyleType);
                            }
                        }
                        else if ($v['key'] === 'UNI')
                        {
                            $aDBCols[$sColumnName] = $this->avoidDupes($sColumnName, $sTable, $v['max_length'], $sStyleType);
                        }
                        else
                        {
                            $aDBCols[$sColumnName] = CharGenerator::generateText($v['max_length'], $sStyleType);
                        }
                    }
                    else if (strpos($v['data_type'], 'text') !== false)
                    {
                        $aDBCols[$sColumnName] = CharGenerator::generateText($v['max_length'], 'alpha');
                    }
                    else if ($v['data_type'] === 'decimal')
                    {
                         $aDBCols[$sColumnName] = round(lcg_value() * ($v['max_length'] * 10), $v['precision']);
                    }
                    else if ($v['data_type'] === 'float' || $v['data_type'] === 'double')
                    {
                        $sType = 'd';

                        if ($v['max_length'] < 6)
                        {
                            $aDBCols[$sColumnName] = round(lcg_value() * $v['max_length'], $v['precision']);
                        }
                        else
                        {
                            $aDBCols[$sColumnName] = round(lcg_value() * ($v['max_length'] * (1000 - 1)), $v['precision']);
                        }
                    }
                    else if ($v['data_type'] === 'date')
                    {
                        $aDBCols[$sColumnName] = CharGenerator::generateDate(5, 'date', 'day');
                    }
                    else if ($v['data_type'] === 'datetime')
                    {
                        $aDBCols[$sColumnName] = CharGenerator::generateDate(11, 'date');
                    }
                    else if ($v['data_type'] === 'timestamp')
                    {
                        $aDBCols[$sColumnName] = CharGenerator::generateDate(11, 'date');
                    }
                    else if ($v['data_type'] === 'time')
                    {
                        $sTS = date('H:i:s', mt_rand(strtotime('yesterday'), time()));
                        $aDBCols[$sColumnName] = $sTS;
                    }
                    else if ($v['data_type'] === 'year')
                    {
                        $aDBCols[$sColumnName] = CharGenerator::generateYear();
                    }
                    else if ($v['data_type'] === 'enum' || $v['data_type'] === 'set')
                    {
                        $k = array_rand($v['enum_vals'], 1);
                        $aDBCols[$sColumnName] = $v['enum_vals'][$k];
                    }
                    else if (strpos($v['data_type'], 'blob') !== false)
                    {
                        $aDBCols[$sColumnName] = 'BLOB 💩';
                    }
                    else if (strpos($v['data_type'], 'varbinary') !== false)
                    {
                        $aDBCols[$sColumnName] = 'VARBINARY';
                    }
                    else if (strpos($v['data_type'], 'binary') !== false)
                    {
                        $aDBCols[$sColumnName] = 'BINARY';
                    }
                    else if (strpos($v['data_type'], 'json') !== false)
                    {
                        $aDBCols[$sColumnName] = json_encode(['json' => 'foobar']);
                    }
                    else if (strpos($v['data_type'], 'bit') !== false)
                    {
                        $sType = 'i';
                        $iBitmask = bindec("b'1'"); # to incorporate BIT(1)
                        $aDBCols[$sColumnName] = $iBitmask;
                    }
                    else
                    {
                        $aDBCols[$sColumnName] = CharGenerator::generateText($v['max_length'], 'alpha');
                    }

                    $aColumnNames[] = '`' . $sColumnName . '`';
                    $aData[] = [$sType, $aDBCols[$sColumnName]];
                    $aPlaceholders[] = '?';
                }

                $aBindParams = [];
                $aBindParams[0] = '';

                $sInsert = '
                    INSERT INTO `' . $sTable . '`
                        (' . join(',', $aColumnNames) . ')
                    VALUES
                        (' . join(',', $aPlaceholders) . ')';

                if ($this->bDebug === true)
                {
                    $this->aMessages[] = $sInsert;
                    $this->aMessages[] = $aDBCols[$sColumnName];
                }

                # bind params
                $oStmt = $this->db->conn->stmt_init();
                $oStmt->prepare($sInsert);

                $sTypes = '';
                $aValues = [];

                foreach ($aData as $d)
                {
                    $sTypes .= $d[0];
                    $aValues[] = $d[1];
                }

                $oStmt->bind_param($sTypes, ...$aValues);
                ##

                $oStmt->execute();
                $oStmt->close();

                $rWarnings = $this->db->conn->query('SHOW WARNINGS');
                $aWarnings = $rWarnings->fetch_all();

                if (count($aWarnings) !== 0)
                {
                    $this->aMessages[] = 'Failed to populate table ' . $sTable . '.';

                    if ($this->bDebug === true)
                    {
                        var_dump($aWarnings);
                    }

                    $this->bErrors = true;
                }

                $this->db->conn->commit();

                $ut = $this->db->conn->query('UNLOCK TABLES');
            }

            # progress indicator
            if ($this->iNumRows > $this->iCLIRowCounter)
            {
                if ($i % $this->iCLIRowCounter === 0)
                {
                    printf("%02d%%" . $this->sLineBreak . "\x1b[A", ($i / $this->iNumRows) * 100);
                }
            }
        }

        if ($this->bErrors === false)
        {
            $this->aMessages[] = "Tables of '" . $this->db->dbname . "' populated with $this->iNumRows rows.";
        }
    }

    /**
        * Avoid duplicate character combinations for keys.
        *
        * @param   string $sColumnName,
        * @param   string $sTable, table name
        * @param   integer $iMaxLen, max length of string
        * @param   string $sStyleType, string pattern
        *
        * @return  string
    */
    private function avoidDupes(string $sColumnName, string $sTable, int $iMaxLen, string $sStyleType): string
    {
        dupe:

        $sChars = CharGenerator::generateText($iMaxLen, $sStyleType);

        $sUn = '
            SELECT `' . $sColumnName . '`
            FROM `' . $sTable . '`
            WHERE `' . $sColumnName . '` = "' . $sChars . '"
            LIMIT 1';

        $rR = $this->db->conn->query($sUn);

        if ($rR !== false)
        {
            if ($rR->num_rows === 0)
            {
                return $sChars;
            }
            else
            {
                $sT = $rR->fetch_row()[0];

                if ($sChars === $sT)
                {
                    goto dupe;
                }
                else
                {
                    return $sChars;
                }
            }
        }
        else
        {
            return 'ERROR';
        }
    }

    /**
        * Jumble foreign keys.
        *
        * @return  void
    */
    private function jumbleForeignKeys(): void
    {
        $this->db->conn->query('USE information_schema');

        $sFKQuery = '
            SELECT
                kcu.TABLE_NAME "table", kcu.COLUMN_NAME "fk_column",
                kcu.REFERENCED_TABLE_NAME "ref_table",
                kcu.REFERENCED_COLUMN_NAME "ref_table_key",
                UNIQUE_CONSTRAINT_NAME "key_type"
            FROM
                KEY_COLUMN_USAGE AS kcu
            INNER JOIN
                REFERENTIAL_CONSTRAINTS ON REFERENTIAL_CONSTRAINTS.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
            WHERE
                kcu.REFERENCED_TABLE_SCHEMA = "' . $this->db->dbname . '"';

        $rR = $this->db->conn->query($sFKQuery);

        $aKeys = $rR->fetch_all(MYSQLI_ASSOC);

        $aTableKeys = [];

        foreach ($aKeys as $aRow)
        {
            $aT = $aRow['table'];

            $aTableKeys[$aT][] =
            [
                'fk_column' => $aRow['fk_column'],
                'ref_table' => $aRow['ref_table'],
                'ref_table_key' => $aRow['ref_table_key'],
                'key_type' => (($aRow['key_type'] === 'PRIMARY') ? 'PK' : 'FK')
            ];
        }

        $this->db->conn->query('USE ' . $this->db->dbname);

        $iLimit = ceil($this->iNumRows * ($this->iForeignKeyPercentReplace / 100));

        foreach ($aTableKeys as $sTable => $aKey)
        {
            foreach ($aKey as $k)
            {
                $aK = [];

                $sQ = '
                    SELECT `' . $k['ref_table_key'] . '`
                    FROM `' . $k['ref_table'] . '`
                    ORDER BY RAND()
                    LIMIT ' . $iLimit;

                if ($this->bDebug === true)
                {
                    $this->aMessages[] = $sQ;
                }

                $rR = $this->db->conn->query($sQ);

                if ($rR !== false)
                {
                    $aK = $rR->fetch_all();
                }

                if (count($aK) === 0)
                {
                    $this->aMessages[] = __METHOD__ . ' failed for table: ' . $sTable;
                    continue;
                }
                else
                {
                    $aK2 = array_merge(...$aK);
                }

                foreach ($aK2 as $r)
                {
                    $sUpdate = '
                        UPDATE `' . $sTable  . '`
                        SET `' . $k['fk_column'] . '` = "' . $r . '"
                        ORDER BY RAND()
                        LIMIT ' . $iLimit;
                }

                if ($this->bDebug === true)
                {
                    $this->aMessages[] = $sUpdate;
                }

                $rR = $this->db->conn->query($sUpdate);

                if ($rR === true)
                {
                    $this->aMessages[] = $k['fk_column'] . " key jumbled in table '" . $sTable . "'";
                }
            }
        }
    }

    /**
        * Getter for class array of messages.
        *
        * @return  string
    */
    public function displayMessages(): string
    {
        return $this->sLineBreak . join($this->sLineBreak, $this->aMessages) . $this->sLineBreak;
    }
}
