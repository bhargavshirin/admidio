<?php
/**
 ***********************************************************************************************
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * Class to implement useful method for installation and update process.
 */
class InstallationUtils
{
    /**
     * Checks whether the minimum requirements for PHP and MySQL have been met.
     * @param Database $database Object of the database that should be checked. A connection should be established.
     * @return string Returns an error text if the database doesn't meet the necessary requirements.
     */
    public static function checkDatabaseVersion(Database $database): string
    {
        global $gL10n;

        // check database version
        if (version_compare($database->getVersion(), $database->getMinimumRequiredVersion(), '<')) {
            return $gL10n->get('SYS_DATABASE_VERSION') . ': <strong>' . $database->getVersion() . '</strong><br /><br />' .
                $gL10n->get('INS_WRONG_MYSQL_VERSION', array(ADMIDIO_VERSION_TEXT, $database->getMinimumRequiredVersion(),
                    '<a href="' . ADMIDIO_HOMEPAGE . 'download.php">', '</a>'));
        }

        return '';
    }

    /**
     * @param Database $db
     */
    public static function disableSoundexSearchIfPgSql(Database $db)
    {
        if (DB_ENGINE === Database::PDO_ENGINE_PGSQL) {
            // soundex is not a default function in PostgresSQL
            $sql = 'UPDATE ' . TBL_PREFERENCES . '
                   SET prf_value = false
                 WHERE prf_name = \'system_search_similar\'';
            $db->queryPrepared($sql);
        }
    }

    /**
     * Get the url of the Admidio installation with all subdirectories, a forwarded host
     * and a port. e.g. https://www.admidio.org/playground
     * @param bool $checkForwardedHost If set to true the script will check if a forwarded host is set and add him to the url
     * @return string The url of the Admidio installation
     */
    public static function getAdmidioUrl(bool $checkForwardedHost = true): string
    {
        $ssl      = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $sp       = strtolower($_SERVER['SERVER_PROTOCOL']);
        $protocol = substr($sp, 0, strpos($sp, '/')) . ($ssl ? 's' : '');
        $port     = (int) $_SERVER['SERVER_PORT'];
        $port     = ((!$ssl && $port === 80) || ($ssl && $port === 443)) ? '' : ':' . $port;
        $host     = ($checkForwardedHost && isset($_SERVER['HTTP_X_FORWARDED_HOST'])) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : ($_SERVER['HTTP_HOST'] ?? null);
        $host     = $host ?? $_SERVER['SERVER_NAME'] . $port;
        $fullUrl  = $protocol . '://' . $host . $_SERVER['REQUEST_URI'];
        return substr($fullUrl, 0, strpos($fullUrl, 'adm_program') - 1);
    }

    /**
     * Read data from sql file and execute all statements to the current database
     * @param Database $db
     * @param string $sqlFileName
     * @return true|string Returns true no error occurs ales error message is returned
     */
    public static function querySqlFile(Database $db, string $sqlFileName)
    {
        global $gL10n;

        $sqlPath = ADMIDIO_PATH . FOLDER_INSTALLATION . '/db_scripts/';
        $sqlFilePath = $sqlPath . $sqlFileName;

        if (!is_file($sqlFilePath)) {
            return $gL10n->get('INS_DATABASE_FILE_NOT_FOUND', array($sqlFileName, $sqlPath));
        }

        try {
            $sqlStatements = Database::getSqlStatementsFromSqlFile($sqlFilePath);
        } catch (RuntimeException $exception) {
            return $gL10n->get('INS_ERROR_OPEN_FILE', array($sqlFilePath));
        }

        foreach ($sqlStatements as $sqlStatement) {
            $db->queryPrepared($sqlStatement);
        }

        return true;
    }
}
