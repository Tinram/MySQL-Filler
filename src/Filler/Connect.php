<?php

declare(strict_types=1);

namespace Filler;

use mysqli;

final class Connect
{
    /**
        * Create MySQLi database connection.
        * Return MySQLi object of configuration connection.
        *
        * @author          Martin Latter
        * @author          Aaron Saray (getInstance())
        * @copyright       Martin Latter 2016
        * @version         0.05
        * @license         GNU GPL version 3.0 (GPL v3); http://www.gnu.org/licenses/gpl.html
        * @link            https://github.com/Tinram/MySQL_Filler.git
        * @package         Filler
    */

    /** @var object $conn, mysqli connection */
    public $conn = null;

    /** @var string $dbname, database name */
    public $dbname = '';

    /** @var boolean $bActiveConnection, active connection */
    private $bActiveConnection = false;

    /** @var object $_instance, instance of class */
    private static $_instance = null;

    /**
        * Constructor: create MySQLi database object.
        *
        * @param   array<string> $aConfig, configuration parameters
    */
    private function __construct(array $aConfig)
    {
        if ( ! isset($aConfig['host']) || ! isset($aConfig['database']) || ! isset($aConfig['username']) || ! isset($aConfig['password']))
        {
            $sMessage = 'Database connection details are not fully specified in the configuration array.';
            die($sMessage);
        }

        $this->conn = @new mysqli($aConfig['host'], $aConfig['username'], $aConfig['password'], $aConfig['database']);

        if ($this->conn->connect_errno > 0)
        {
            $sMessage = 'Connection failed: ' . $this->conn->connect_error . ' (' . $this->conn->connect_errno . ')' . PHP_EOL;
            die($sMessage);
        }
        else
        {
            $this->bActiveConnection = true;
            $this->dbname = $aConfig['database'];
            $this->conn->set_charset($aConfig['charset']);
        }
    }

    /**
        * Close DB connection on script termination.
    */
    public function __destruct()
    {
        if ($this->bActiveConnection)
        {
            $this->conn->close();
        }
    }

    /**
        * Public API.
        *
        * @param   array<string> $aConfig, configuration parameters
        *
        * @return  Connect, MySQLi connection
    */
    public static function getInstance(array $aConfig): self
    {
        if ( ! self::$_instance instanceof self)
        {
            self::$_instance = new self($aConfig);
        }

        return self::$_instance;
    }
}
