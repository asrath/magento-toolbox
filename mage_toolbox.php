<?php
    //customizable vars
    $magentoRoot = ''; //relative to this script location

    
    /**********************************************/
    /********* DON'T TOUCH THE CODE BELOW *********/
    /**********************************************/
    
    class MageToolbox
    {
        const DS = DIRECTORY_SEPARATOR;
        const SECOND_COLUMN_LEFT = 35;
        
        /**
         * Magento Root path
         *
         * @var string
         */
        protected $_rootPath;
        
        /**
         * Magento local.xml filepath
         * 
         * @var string
         */
        protected $_configPath;
        
        /**
         * Magento var directory fallback path
         *
         * @var string
         */
        protected $_pathVarFallback;
        
        /**
         * Is include Mage and initialize application
         *
         * @var bool
         */
        protected $_includeMage = true;
        
        /**
         * Initialize application with code (store, website code)
         *
         * @var string
         */
        protected $_appCode = 'admin';

       /**
         * Initialize application code type (store, website, store_group)
         *
         * @var string
         */
        protected $_appType = 'store';
        
        /**
         * Input arguments
         *
         * @var array
         */
        protected $_args = array();
        
        /**
         * Association between an arg and the method that receives it
         *
         * @var array
         */
        protected $_argMethodMap;
        
        /**
         * Holds an assoc array ready to extract in order to setup a common set
         * of variables in each method
         * 
         * @var array
         */
        protected $_commonVars;
        
        /**
         *
         * @var string
         */
        protected $_scope;
        
        /**
         *
         * @var int
         */
        protected $_scopeId;
        
        /**
         * Initialize application and parse input parameters
         *
         */
        public function __construct($magentoRoot = '')
        {
            if ($this->_includeMage) {
                require_once $this->_getRootPath($magentoRoot).'app'.self::DS.'Mage.php';
                Mage::app($this->_appCode, $this->_appType);
            }
            $this->_configPath = $this->_getRootPath().'app'.DS.'etc'.DS.'local.xml';
            $this->_scope = 'default';
            $this->_scopeId = Mage_Core_Model_App::ADMIN_STORE_ID;
            
            $this->_pathVarFallback = DS.'tmp'.DS.'magento'.DS;
           
            $this->_applyPhpVariables();
            $this->_mapArgToMethod();
            $this->_parseArgs();
            $this->_mapArgToMethod();
            $this->_setCommonVars();
            $this->_construct();
            //$this->_validate();
            $this->_showHelp();
        }
        
        /**
         * Get the magento root directory searching from the current script location (with last directory separator)
         *
         * @param string $magentoRoot
         * @return string Absolute path to magento root
         */
        protected function _getRootPath($magentoRoot = '')
        {
            $n = PHP_EOL;
            $this->_rootPath;
            $pwd = realpath(dirname(__FILE__)).self::DS;
            $fileInclude = 'app'.self::DS.'Mage.php';
            
            if (empty($this->_rootPath)) {
                if (empty($magentoRoot))
                    $magePath = $pwd.$fileInclude;
                else
                    $magePath = $pwd.$magentoRoot.self::DS.$fileInclude;

                if (!file_exists($magePath))
                {
                    //find first ocurrence of Mage.php exclude .svn directories to speed up the search
                    //$results = shell_exec("find $pwd -name Mage.php -type f | sed 1q");
                    $results = shell_exec('find '.$pwd.' -name Mage.php -a ! -name *.svn* -type f | sed 1q');
                    $results = str_replace($n, '', $results); //strip line ends
                    $pathArray = explode(self::DS, $results);
                    $pwdArray = explode(self::DS, $pwd);
                    //we remove the last 2 elements because Mage.php it's always in app/Mage.php
                    array_splice($pathArray, count($pathArray)-2);

                    $magentoRoot = array_diff($pathArray, $pwdArray);
                    if (count($magentoRoot) > 0) $magentoRoot = implode(self::DS, $magentoRoot);
                    else $magentoRoot = '';

                    //self modify the $magentoRoot declaration line at the begining of this script
                    $data = file(__FILE__, FILE_IGNORE_NEW_LINES);
                    $data[2] = "    \$magentoRoot = '$magentoRoot'; //relative to this script location";
                    file_put_contents(__FILE__, implode($n, $data));
                    
                }
                
                if (empty($magentoRoot))
                    $this->_rootPath = $pwd;
                else
                    $this->_rootPath = $pwd.$magentoRoot.self::DS;
                
                if (!file_exists($this->_rootPath.'app'.self::DS.'Mage.php'))
                {
                    die('ERROR!!!! The Magento root filepath provided is not correct ('.$magentoRoot.').'.$n.'Edit the file and provide a '.$magentoRoot.' relative to this file path'.$n.$n);
                }
            }
            
            return $this->_rootPath;
        }
        
        protected function _mapArgToMethod()
        {
            $this->_argMethodMap = array(
                'c' => 'cleanCache',
                'clean' => 'cleanCache',
                'i' => 'reindex',
                'index' => 'reindex',
                'm' => 'setIndexMode',
                'index-mode' => 'setIndexMode',
                'd' => 'setDatabaseInfo',
                'database' => 'setDatabaseInfo',
                'u' => 'setPasswordForUser',
                'user' => 'setPasswordForUser',
                'l' => 'setUnsecureBaseUrl',
                'url' => 'setUnsecureBaseUrl',
                's' => 'setSecureBaseUrl',
                'secure-url' => 'setSecureBaseUrl',
                'h' => 'toggleTemplateHints',
                'hints' => 'toggleTemplateHints',
                'e' => 'exec',
                'exec' => 'exec',
                'fix-perms' => 'fixPerms',
                //'scope' => 'setScope',
                'status' => 'status',
                //'help' => '',
            );
        }
        
        /**
         * Parse .htaccess file and apply php settings to shell script
         *
         */
        protected function _applyPhpVariables()
        {
            $htaccess = $this->_getRootPath() . '.htaccess';
            if (file_exists($htaccess)) {
                // parse htaccess file
                $data = file_get_contents($htaccess);
                $matches = array();
                preg_match_all('#^\s+?php_value\s+([a-z_]+)\s+(.+)$#siUm', $data, $matches, PREG_SET_ORDER);
                if ($matches) {
                    foreach ($matches as $match) {
                        @ini_set($match[1], str_replace("\r", '', $match[2]));
                    }
                }
                preg_match_all('#^\s+?php_flag\s+([a-z_]+)\s+(.+)$#siUm', $data, $matches, PREG_SET_ORDER);
                if ($matches) {
                    foreach ($matches as $match) {
                        @ini_set($match[1], str_replace("\r", '', $match[2]));
                    }
                }
            }
        }
        
        /**
         * Parse input arguments
         *
         * @return MageToolbox
         */
        protected function _parseArgs()
        {
            $current = null;
            
            foreach ($_SERVER['argv'] as $arg) {
                $match = array();
                if (preg_match('#^--([\w\d_-]{1,})$#', $arg, $match) || preg_match('#^-([\w\d_]{1,})$#', $arg, $match)) {
                    $current = $match[1];
                    $this->_args[$current] = true;
                } else {
                    if ($current) {
                        $this->_args[$current] = $arg;
                    } else if (preg_match('#^([\w\d_]{1,})$#', $arg, $match)) {
                        $this->_args[$match[1]] = true;
                    }
                }
            }
            
            return $this;
        }
        
        /**
         * Generates an array with common variables used across the
         * different methods
         */
        protected function _setCommonVars()
        {
            $this->_commonVars = array(
                '_script' => basename(__FILE__),
                'n' => PHP_EOL,
            );
        }
        
        /**
         * Additional initialize instruction
         *
         * @return MageToolbox
         */
        protected function _construct()
        {
            if (array_key_exists('scope', $this->_args)) {
                $this->setScope($this->_args['scope']);
            }
            
            return $this;
        }
        
        /**
         * Check is show usage help
         *
         */
        protected function _showHelp()
        {
            if (empty($this->_args) || isset($this->_args['help'])) {
                die($this->usageHelp());
            }
        }
        
        /**
         * Retrieve Usage Help Message
         */
        public function usageHelp()
        {
            extract($this->_commonVars);
            $helpMessages = array(
                OutputCLI::columnize('status', 2, 'Shows a quick summary of magento status.', self::SECOND_COLUMN_LEFT),
                OutputCLI::columnize('-c, --clean', 2, 'Clean magento caches.', self::SECOND_COLUMN_LEFT),
                OutputCLI::columnize('-i, --index', 2, 'Magento reindex. Format is: "php '.$_script.' -i [all|index]". You can specify which index should be reindexed, use ? to show help ("php '.$_script.' -i ?").', self::SECOND_COLUMN_LEFT),
                OutputCLI::columnize('-m, --index-mode', 2, 'Change Magento index mode. Format is: "php '.$_script.' -i [all|index] [realtime|manual]". You can specify which index should be reindexed, use ? to show help ("php '.$_script.' -m ?").', self::SECOND_COLUMN_LEFT),
                OutputCLI::columnize('-d, --database', 2, 'Sets user, password, database and host in the magento local.xml. Format is: "dbuser;dbpassword;dbname[;dbhost]"', self::SECOND_COLUMN_LEFT),
                OutputCLI::columnize('-u, --user', 2, 'Especify the user to change password.', self::SECOND_COLUMN_LEFT),
                OutputCLI::columnize('-p, --password', 2, 'Especify the new password (if no password is entered or if this option is missing "admin" will be used). Only works if user is specified.', self::SECOND_COLUMN_LEFT),
                OutputCLI::columnize('-l, --url', 2, 'Especify an URL to be used as the magento base url (remember that the url must begin with "http://" or  "https://" and end with "/"). If the url is "" the value will be deleted.', self::SECOND_COLUMN_LEFT),
                OutputCLI::columnize('-s, --secure-url', 2, 'Especify an URL to be used as the magento secure base url (remember that the url must begin with "http://" or  "https://" and end with "/"). If the url is "" the value will be deleted.', self::SECOND_COLUMN_LEFT),
                OutputCLI::columnize('-h, --hints', 2, 'Enables and disables magento template hints for the specified store. If ? is used, a list of the store codes will be shown.', self::SECOND_COLUMN_LEFT),
                OutputCLI::columnize('-e, --exec', 2, 'Executes the php file in the provided path. Takes a relative file path as an argument.', self::SECOND_COLUMN_LEFT),
                OutputCLI::columnize('--fix-perms', 6, 'Fix permissions for development enviroment for the specified OS, on the project directory. Supported OS: debian, centos', self::SECOND_COLUMN_LEFT),
                OutputCLI::columnize('--scope', 6, 'Tries to perform the current action in the selected scope (this option does not always have effect). Format is: "website:code" or "store:code"', self::SECOND_COLUMN_LEFT),
                OutputCLI::columnize('--help', 6, 'This help.', self::SECOND_COLUMN_LEFT),
            );
            
            echo 'Usage: php ', $_script, ' [OPTION]', $n, $n;
            echo 'List of options:', $n;

            foreach ($helpMessages as $msg)
            {
                echo $msg, $n;
            }
        }
        
        /**
         * Main method of the class it executes the different callbacks based
         * on the recevied args
         */
        public function run()
        {
            extract($this->_commonVars);
            
            foreach ($this->_args as $arg => $value) {
                if (array_key_exists($arg, $this->_argMethodMap)) {
                    call_user_func_array(array($this, $this->_argMethodMap[$arg]), array($value));
                }
            }
            
            echo $n;
        }
        
        /**
         * Parses the scope info and returns and array with the following keys:
         *   - scope
         *   - code
         *   - error
         * 
         * @param string $scopeValue Scope info in the form: "&lt;scope&gt;:&lt;scope_id&gt;"
         * @return array
         */
        protected function _parseScopeValue($scopeValue)
        {
            extract($this->_commonVars);
            
            $result = array();
            
            $incorrectFormatMsg = 'Scope format is incorrect.'.$n.'Format is: "website:code" or "store:code'.$n;
            if (strpos($scopeValue, ':') !== FALSE) {
                $scopeArray = explode(':', $scopeValue);
                if (count($scopeArray) == 2) {
                    list($scope, $code) = $scopeArray;
                    if (!empty($scope) && !empty($code)) {
                        $result['scope'] = $scope;
                        $result['code'] = $code;
                    }
                    elseif (empty($scope)) {
                        $result['error'] = 'Scope cannot be empty'.$n;
                    }
                    elseif (empty($code)) {
                        $result['error'] = 'Scope code cannot be empty'.$n;
                    }
                }
                else {
                    $result['error'] = $incorrectFormatMsg;
                }
            }
            else {
                $result['error'] = $incorrectFormatMsg;
            }
            
            return $result;
        }
        
        /**
         * Sets the scope for some of the script operations
         * 
         * @param string $arg Scope info in the form: "&lt;scope&gt;:&lt;scope_id&gt;"
         */
        public function setScope($arg)
        {
            extract($this->_commonVars);
            
            if ($arg !== TRUE) {
                $parsedScope = $this->_parseScopeValue($arg);
                if (isset($parsedScope['error']) && !empty($parsedScope['error'])) {
                    echo $parsedScope['error'];
                }
                else {
                    if (!empty($parsedScope['scope']) && $parsedScope['scope'] == 'website') {
                        try
                        {
                            //get the website and its config values in a try catch block
                            //in order to avoid errors if the scope code is incorrect
                            $this->_scopeId = Mage::app()->getWebsite($parsedScope['code'])->getId();
                            $this->_scope = $parsedScope['scope'].'s';
                        }
                        catch (Exception $e)
                        {
                            echo 'The website code does not exist', $n;
                        }
                    } elseif (!empty($parsedScope['scope']) && $parsedScope['scope'] == 'store') {
                        try
                        {
                            //get the store and its config values in a try catch block
                            //in order to avoid errors if the scope code is incorrect
                            $this->_scopeId = Mage::app()->getStore($parsedScope['code'])->getId();
                            $this->_scope = $parsedScope['scope'].'s';
                        }
                        catch (Exception $e)
                        {
                            echo 'The store code does not exist', $n;
                        }
                    }
                }
            }
            else {
                echo 'Scope cannot be empty', $n;
            }
        }
        
        /**
         * Outputs info about the indexes, template hints, URLs and DB settings
         */
        public function status()
        {
            extract($this->_commonVars);
            
            if (file_exists($this->_configPath))
            {
                //get database info
                $dom = new DOMDocument();
                $dom->load($this->_configPath);
                //we use firstChild because the value is enclosed inside CDATA
                $username = (string)$dom->getElementsByTagName('username')->item(0)->firstChild->nodeValue;
                $password = (string)$dom->getElementsByTagName('password')->item(0)->firstChild->nodeValue;
                $dbName = (string)$dom->getElementsByTagName('dbname')->item(0)->firstChild->nodeValue;
                $host = (string)$dom->getElementsByTagName('host')->item(0)->firstChild->nodeValue;

                //get hints and url info for each store
                $backendUrl = (string)$dom->getElementsByTagName('frontName')->item(0)->firstChild->nodeValue;
                $stores = Mage::app()->getStores(TRUE);
                $hints = '';

                //to retrieve base urls from the config
                $urls = OutputCLI::columnize('Backend URL:', 0, OutputCLI::format(Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_SECURE_BASE_URL).$backendUrl, NULL, NULL, 'underline'), self::SECOND_COLUMN_LEFT).$n.$n;
                foreach ($stores as $store) {
                    $templateHints = $store->getConfig('dev/debug/template_hints') ? OutputCLI::format('Enabled', 'green') : OutputCLI::format('Disabled', 'red');
                    $unsecureBaseUrl = $store->getConfig(Mage_Core_Model_Store::XML_PATH_UNSECURE_BASE_URL);
                    $unsecureBaseUrlScope = $this->_getEffectiveScopeInfo(Mage_Core_Model_Store::XML_PATH_UNSECURE_BASE_URL, 'stores', $store->getId());
                    $secureBaseUrl = $store->getConfig(Mage_Core_Model_Store::XML_PATH_SECURE_BASE_URL);
                    $secureBaseUrlScope = $this->_getEffectiveScopeInfo(Mage_Core_Model_Store::XML_PATH_SECURE_BASE_URL, 'stores', $store->getId());
                    $hints .= OutputCLI::columnize($store->getData('code').':', 0, $templateHints, self::SECOND_COLUMN_LEFT). $n;
                    $urls .= OutputCLI::format($store->getData('code').':', NULL, NULL, 'bold').$n;
                    $urls .= OutputCLI::columnize('Unsecure base URL:', 3, OutputCLI::format($unsecureBaseUrl, NULL, NULL, 'underline').' '.OutputCLI::format('['.$unsecureBaseUrlScope['scope'].']', NULL, NULL, 'bold'), self::SECOND_COLUMN_LEFT).$n;
                    $urls .= OutputCLI::columnize('Secure base URL:', 3, OutputCLI::format($secureBaseUrl, NULL, NULL, 'underline').' '.OutputCLI::format('['.$secureBaseUrlScope['scope'].']', NULL, NULL, 'bold'), self::SECOND_COLUMN_LEFT).$n;
                }

                $indexer = Mage::getSingleton('index/indexer');
                $indexes = "";
                foreach ($indexer->getProcessesCollection() as $process)
                {
                    if ($process->getData('mode') == 'manual') $processMode = OutputCLI::format('Manual', 'yellow');
                    else $processMode = OutputCLI::format('Real time', 'green');
                    switch ($process->getStatus())
                    {
                        case Mage_Index_Model_Process::STATUS_PENDING:
                            $processStatus = OutputCLI::format('Ready', 'green');
                            break;
                        case Mage_Index_Model_Process::STATUS_RUNNING:
                            $processStatus = OutputCLI::format('Processing', 'yellow');
                            break;
                        case Mage_Index_Model_Process::STATUS_REQUIRE_REINDEX:
                            $processStatus = OutputCLI::format('Reindex Required', 'red');
                            break;
                        default:
                            $processStatus = '';
                            break;
                    }
                    $indexes .= OutputCLI::columnize($process->getIndexerCode(), 0, $processStatus. ' ('.$processMode.')', self::SECOND_COLUMN_LEFT). $n;
                }

                if (file_exists($this->_pathVarFallback))
                    echo OutputCLI::format('/tmp/magento directory detected!!!'.$n.'Check filesystem permissions and remove it.', 'red'), $n, $n;

                echo 'Database info:',$n,'---------------',$n;
                echo OutputCLI::columnize('DB Host:', 0, $host, self::SECOND_COLUMN_LEFT).$n;
                echo OutputCLI::columnize('DB Name:', 0, $dbName, self::SECOND_COLUMN_LEFT).$n;
                echo OutputCLI::columnize('Username:', 0, $username, self::SECOND_COLUMN_LEFT).$n;
                echo OutputCLI::columnize('Password:', 0, $password, self::SECOND_COLUMN_LEFT).$n;

                echo $n,'Url info:',$n,'---------------',$n;
                echo $urls;
                echo $n,'Template hints',$n,'---------------',$n;
                echo $hints;
                echo $n,'Index status:',$n,'---------------',$n;
                echo $indexes;
            }
        }
        
        /**
         * Returns the scope and scopeId for a config value
         * 
         * @param string $configPath
         * @param string $_scope
         * @param string $_scopeId
         * @return array
         */
        protected function _getEffectiveScopeInfo($configPath, $_scope, $_scopeId)
        {
            $scope = 'default';
            $scopeId = Mage_Core_Model_App::ADMIN_STORE_ID;
            if ($_scope == 'stores')
            {
                $_scopeId = Mage::app()->getStore($_scopeId)->getWebsiteId();
                $_scope = 'websites';
            }
            elseif ($_scope == 'websites')
            {
                $_scope = $scope;
                $_scopeId = $scopeId;
            }
            $query = "SELECT * FROM core_config_data WHERE path='$configPath' AND (scope = '$_scope' OR scope = '$scope') AND (scope_id = $_scopeId OR scope_id = $scopeId) ORDER BY scope DESC LIMIT 1";
            $data = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($query);
            if (!empty($data) && $data[0]['scope'] != 'default')
            {
                //remove the last character 's' appended by magento
                $scope = ($data[0]['scope'] == 'websites' || $data[0]['scope'] == 'stores') ? substr($data[0]['scope'], 0, -1) : $data[0]['scope'];
                $scopeId = $data[0]['scope_id'];
            }

            return array('scope' => $scope, 'scope_id' => $scopeId);
        }
        
        /**
         * Cleans the magento cache
         * 
         * @return boolean
         */
        public function cleanCache()
        {
            extract($this->_commonVars);
            $result = FALSE;
            $cacheDir = $this->_rootPath.'var'.DS.'cache'.DS;
            $fallbackCacheDir = $this->_pathVarFallback.'var'.DS.'cache'.DS;
            if (file_exists($this->_pathVarFallback))
            {
                echo OutputCLI::format('/tmp/magento directory detected!!!'.$n.'Check filesystem permissions and remove it.', 'red'), $n, $n;
                $command = 'rm -rf '.$fallbackCacheDir.'*';
                shell_exec($command);
                echo '/tmp/magento caches cleaned!!!', $n;
                $result = TRUE;
            }
            if (file_exists($cacheDir))
            {
                $command = 'rm -rf '.$cacheDir.'*';
                shell_exec($command);
                echo 'Caches cleaned!!!', $n;
                $result = TRUE;
            }
            else
                echo OutputCLI::format('Caches could not be cleaned. You must clean them manually.', 'red'), $n;

            //flush or restart memcached
            $memcachedPath = DS.'etc'.DS.'init.d'.DS.'memcached';
            if (file_exists($memcachedPath))
            {
                if (file_exists(DS.'usr'.DS.'bin'.DS.'nc'))
                    shell_exec("echo 'flush_all' | nc localhost 11211");
                else
                    shell_exec("$memcachedPath restart");
                
                echo 'Memcached emptied!!!', $n;
            }

            //delete core cache
            $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
            $coreCacheTable = Mage::getSingleton('core/resource')->getTableName('core/cache');
            $coreCacheTagTable = Mage::getSingleton('core/resource')->getTableName('core/cache_tag');
            $connection->query("TRUNCATE {$coreCacheTable};");
            $connection->query("TRUNCATE {$coreCacheTagTable};");
            echo 'Cache tables emptied!!!', $n;
            
            return $result;
        }
        
        /**
         * Reindex the specified index or all the indexes
         * 
         * @param string $arg Index code
         */
        public function reindex($arg)
        {
            extract($this->_commonVars);
            $indexerScript = $this->_rootPath.'shell'.DS.'indexer.php';
            $availableIndexes = $this->_getIndexList();
            switch ($arg) {
                case 'all':
                    $command = "php $indexerScript reindexall";
                    echo 'Reindexing ...', $n;
                    break;
                default:
                    if (in_array($arg, array_keys($availableIndexes))) {
                        $command = "php $indexerScript --reindex $arg";
                    }
                    else {
                        echo 'Usage: php ', $_script. ' -i [all|index]', $n, $n;
                        echo 'Available indexes:', $n;
                        foreach ($availableIndexes as $code => $name) {
                            echo OutputCLI::columnize($code, 2, $name, self::SECOND_COLUMN_LEFT), $n;
                        }
                    }
                    break;
            }
            
            if (isset($command) && !empty($command)) echo shell_exec($command);
        }
        
        /**
         * Gets the index codes and names in an array
         * 
         * @return array
         */
        protected function _getIndexList()
        {
            $indexer = Mage::getSingleton('index/indexer');
            $indexList = array();
            foreach ($indexer->getProcessesCollection() as $process) {
                /* @var $process Mage_Index_Model_Process */
                $indexList[$process->getIndexerCode()] = $process->getIndexer()->getName();
            }
            
            return $indexList;
        }
        
        /**
         * Changes the indexing mode for the specified index or for all the indexes
         * 
         * @param string $arg Indexing mode
         */
        public function setIndexMode($arg)
        {
            extract($this->_commonVars);
            $indexerScript = $this->_rootPath.'shell'.DS.'indexer.php';
            $availableIndexes = $this->_getIndexList();
            //TODO:change this way to get the index param
            $param = $_SERVER['argv'][2];
            $mode = '--mode-';
            //build the mode string
            switch ($arg) {
                case 'manual':
                case 'realtime':
                    $mode.= $arg;
                    break;
                default:
                    $mode .= 'realtime';
                    break;
            }
            
            switch ($param) {
                case 'all':
                    $command = "php $indexerScript $mode";
                    break;
                default:
                    if (in_array($param, array_keys($availableIndexes))) {
                        $command = "php $indexerScript $mode $param";
                    }
                    else {
                        echo 'Usage: php ', $_script, ' -m [all|index] [realtime|manual]', $n, $n;
                        echo 'Available indexes:', $n;
                        foreach ($availableIndexes as $code => $name) {
                            echo OutputCLI::columnize($code, 2, $name, self::SECOND_COLUMN_LEFT), $n;
                        }
                    }
                    break;
            }
            
            if (isset($command) && !empty($command)) echo shell_exec($command);
        }
        
        /**
         * Changes the database info in app/etc/local.xml file
         * 
         * @param string $arg New database info
         */
        public function setDatabaseInfo($arg)
        {
            extract($this->_commonVars);
            
            if (file_exists($this->_configPath)) {
                $dbData = explode(';', $arg);
                if (count($dbData) >= 3) {
                    //default value for host
                    if (count($dbData == 3)) $dbData []= 'localhost';
                    list($dbUser,$dbPassword,$dbName,$dbHost) = $dbData;

                    $dom = new DOMDocument();
                    $dom->load($this->_configPath);
                    //we use firstChild because the value is enclosed inside CDATA
                    $dom->getElementsByTagName('username')->item(0)->firstChild->nodeValue = $dbUser;
                    $dom->getElementsByTagName('password')->item(0)->firstChild->nodeValue = $dbPassword;
                    $dom->getElementsByTagName('dbname')->item(0)->firstChild->nodeValue = $dbName;
                    $dom->getElementsByTagName('host')->item(0)->firstChild->nodeValue = $dbHost;
                    $fp = fopen($this->_configPath, 'w');
                    fwrite($fp, $dom->saveXML());
                    fclose($fp);

                    //important to clear caches for the changes to take effect
                    $this->cleanCache();

                    echo $this->_configPath, ' has been changed', $n;
                }
                else {
                    echo 'Incorrect data. You need to specify dbuser, dbpassword and dbname (host is optional) in this format: "dbuser;dbpassword;dbname[;dbhost]"', $n;
                }
            }
            else {
                echo $this->_configPath, ' cannot be found', $n;
            }
        }
        
        /**
         * Changes the password for the specified backend user
         * 
         * @param string $arg The user login
         */
        public function setPasswordForUser($arg)
        {
            extract($this->_commonVars);
            
            if ($arg !== TRUE) {
                $username = $arg;
                if (isset($this->_args['password']) && $this->_args['password'] !== TRUE) {
                    $password = $this->_args['password'];
                }
                elseif (isset($this->_args['p']) && $this->_args['p'] !== TRUE) {
                    $password = $this->_args['p'];
                }
                else {
                    $password = 'admin';
                    echo 'No password specified, "', $password, '" will be used', $n;
                }

                $user = Mage::getModel('admin/user')->loadByUsername($username);
                if ($user->getId()) {
                    //Magento will handle the hash generation
                    $oldHash = $user->getPassword();
                    $user->setPassword($password);
                    $user->save();

                    echo 'Hash for "', $password, '" is:', $n;
                    echo "\t", $user->getPassword(), $n;
                    echo 'Old hash was:', $n;
                    echo "\t", $oldHash, $n;
                    echo 'User "', $username, '" password has been changed', $n;
                }
                else {
                    echo 'User "', $username, '" does not exist', $n;
                }
            }
            else {
                echo 'User cannot be empty', $n;
            }
        }
        
        /**
         * URL format check
         * 
         * @param string $url
         * @return boolean
         */
        protected function _checkUrl($url)
        {
            extract($this->_commonVars);
            
            $pattern = "/^https?:\/\/.*\/{1}$/";
            
            $match = preg_match($pattern, $url);
            if (!$match) {
                echo 'The URL format is incorrect. It should be: http://site.url.com/ or https://site.url.com/', $n, 'The trailing slash (/) is mandatory.', $n;
            }
            
            return $match;
        }
        
        /**
         * Sets the base URL for the current scope
         * 
         * @param string $arg The URL
         * @param string $type secure|unsecure
         * @see MageToolbox::setScope()
         */
        public function setBaseUrl($arg, $type = 'unsecure')
        {
            extract($this->_commonVars);
            
            if ($arg === TRUE) {
                $argOK = TRUE;
                $arg = '';
            }
            else $argOK = $this->_checkUrl($arg);
            
            if ($argOK) {
                $baseUrl = $arg;
                if ($type == 'secure')
                    $configPath = Mage_Core_Model_Store::XML_PATH_SECURE_BASE_URL;
                else
                    $configPath = Mage_Core_Model_Store::XML_PATH_UNSECURE_BASE_URL;
                
                if ($this->_scope == 'default' || $this->_scope == 'stores') {
                    $scope = 'store';
                    $scopeCode = Mage::app()->getStore($this->_scopeId)->getCode();
                    $oldBaseUrl = Mage::getStoreConfig($configPath, $this->_scopeId);
                }
                else {
                    $scope = 'website';
                    $scopeCode = Mage::app()->getWebsite($this->_scopeId)->getCode();
                    $oldBaseUrl = Mage::app()->getWebsite($this->_scopeId)->getConfig($configPath);
                }
                
                if (!empty($baseUrl))
                    Mage::getConfig()->saveConfig($configPath, $baseUrl, $this->_scope, $this->_scopeId);
                //we don't remove the config value if it's the admin store
                elseif(empty($baseUrl) && $this->_scopeId !== Mage_Core_Model_App::ADMIN_STORE_ID)
                    Mage::getConfig()->deleteConfig($configPath, $this->_scope, $this->_scopeId);

                //important to clear caches for the changes to take effect
                $this->cleanCache();

                if (!empty($baseUrl))
                    echo ucfirst($type), ' base url changed from ', $oldBaseUrl, ' to ', $baseUrl, ' for ', $scope, ' ', $scopeCode, $n;
                elseif (empty($baseUrl) && $this->_scopeId !== Mage_Core_Model_App::ADMIN_STORE_ID)
                    echo ucfirst($type), ' base url ', $oldBaseUrl, ' deleted from ', $scope, ' ', $scopeCode, $n;
                elseif (empty($baseUrl) && $this->_scopeId === Mage_Core_Model_App::ADMIN_STORE_ID)
                {
                    echo ucfirst($type), ' base url cannot be empty for ', $scope, ' ', $scopeCode, $n;
                    echo ucfirst($type), ' base url ', $oldBaseUrl, ' has not been changed for ', $scope, ' ', $scopeCode, $n;
                }
            }
        }
        
        /**
         * 
         * @param string $arg The URL
         */
        public function setUnsecureBaseUrl($arg)
        {
            $this->setBaseUrl($arg, 'unsecure');
        }
        
        /**
         * 
         * @param string $arg The URL
         */
        public function setSecureBaseUrl($arg)
        {
            $this->setBaseUrl($arg, 'secure');
        }
        
        /**
         * Enable or disable the template hints for the specified store
         * 
         * @param string $arg The store code
         */
        public function toggleTemplateHints($arg)
        {
            extract($this->_commonVars);
            
            $storeCode = $arg;
            //with the ? we print the list of stores
            if ($storeCode == '?') {
                echo 'List of store codes:', $n;
                $stores = Mage::app()->getStores(TRUE);
                foreach ($stores as $store) {
                    echo '    ', $store->getData('code'), $n;
                }
            }
            else {
                try {
                    //get the store and its config values in a try catch block
                    //in order to avoid errors if the $storeCode is incorrect
                    $store = Mage::app()->getStore($storeCode);
                    $templateHints = $store->getConfig('dev/debug/template_hints');
                    $templateHintsBlocks = $store->getConfig('dev/debug/template_hints_blocks');

                    //we enable and disable all the hints at once
                    if (!$templateHints || !$templateHintsBlocks) {
                        Mage::getConfig()->saveConfig('dev/debug/template_hints', '1', 'stores', $store->getId());
                        Mage::getConfig()->saveConfig('dev/debug/template_hints_blocks', '1', 'stores', $store->getId());
                        echo 'Template hints for the store ', $storeCode, ' have been enabled',$n;
                    }
                    else {
                        Mage::getConfig()->saveConfig('dev/debug/template_hints', "0", 'stores', $store->getId());
                        Mage::getConfig()->saveConfig('dev/debug/template_hints_blocks', "0", 'stores', $store->getId());
                        echo 'Template hints for the store ', $storeCode, ' have been disabled',$n;
                    }
                    
                    //important to clear caches for the changes to take effect
                    //also the getConfig method uses the cache when returning the values
                    $this->cleanCache();
                }
                catch (Exception $e) {
                    echo 'The store code does not exist', $n;
                    //echo $e->getMessage();
                }
            }
        }
        
        /**
         * Execute some script or snippet using the magento includes
         * 
         * @param string $arg The filepath to the script
         */
        public function exec($arg)
        {
            extract($this->_commonVars);
            
            $includeFile = $arg;
            $absolutePath = $this->_getRootPath().$includeFile;
            if (file_exists($absolutePath)) {
                try {
                    include $absolutePath;
                }
                catch (Exception $e) {
                    echo $e->getMessage();
                }
                echo $n;
            }
        }
        
        /**
         * Returns the id of the apache group for a OS
         * Currently supported OS: debian, centos
         * 
         * @param string $os
         * @return string
         */
        protected function _getApacheGroup($os)
        {
            switch (strtolower($os)) {
                case 'centos':
                    $group = '48';
                    break;
                default:
                    //debian
                    $group = '33';
                    break;
            }
            
            return $group;
        }
        
        /**
         * Sets the filesystem permissions for a development environment
         * 
         * @param string $arg The OS in which the permissions should be fixed (debian, centos)
         */
        public function fixPerms($arg)
        {
            extract($this->_commonVars);
            
            $group = $this->_getApacheGroup($arg);
            $_user = trim($_SERVER['USER']);
            //file ownership
            echo 'Changing ownership of ', $this->_getRootPath(), ' and subdirectories ...', $n;
            $command = 'chown -R $USER:'.$group.' '.$this->_getRootPath();
            if ($_user != 'root') $command = 'sudo '. $command;
            shell_exec($command);

            echo 'Fixing permissions of ', $this->_getRootPath(), ' and subdirectories ...', $n;
            //file and directory permissions
            shell_exec('find '.$this->_getRootPath().' -type d -exec chmod 755 {} \;');
            shell_exec('find '.$this->_getRootPath().' -type f -exec chmod 644 {} \;');
            shell_exec('chmod -R 775 '.$this->_getRootPath().DS."app".DS."etc");
            shell_exec('find '.$this->_getRootPath().' -name var -type d -exec chmod -R 775 {} \;');
            shell_exec('find '.$this->_getRootPath().' -name media -type d -exec chmod -R 775 {} \;');

            echo 'Done.', $n;
        }
    }
    
    class OutputCLI
    {
        const MAX_LINE_CHARS = 106;
        
        static $stringFormat = "\033[%sm%s";
        static $stringReset = "\033[0m";
        
        static $foregroundColors = array(
            'black'         => '0;30',  'dark_gray'     => '1;30',
            'blue'          => '0;34',  'light_blue'    => '1;34',
            'green'         => '0;32',  'light_green'   => '1;32',
            'cyan'          => '0;36',  'light_cyan'    => '1;36',
            'red'           => '0;31',  'light_red'     => '1;31',
            'purple'        => '0;35',  'light_purple'  => '1;35',
            'brown'         => '0;33',  'yellow'        => '1;33',
            'light_gray'    => '0;37',  'white'         => '1;37',
        );

        static $backgroundColors = array(
            'black'         => '40',    'red'           => '41',
            'green'         => '42',    'yellow'        => '43',
            'blue'          => '44',    'magenta'       => '45',
            'cyan'          => '46',    'light_gray'    => '47',
        );
        
        /**
         * Formating codes.
         * Not all the formatings are recognized in every shell
         * 
         * @var array
         */
        static $formatings = array(
            'bold'          => '1',     'dark'          => '2',
            'underline'     => '4',     'blink'         => '5',
            'reverse'       => '7',     'concealed'     => '8',
        );

        /**
         * Returns formated string
         *
         * @param string $string
         * @param string $foregroundColor
         * @param string $backgroundColor
         * @param string $formating
         * @return string 
         */
        public static function format($string, $foregroundColor = NULL, $backgroundColor = NULL, $formating = NULL)
        {
            //we have to apply the format strings in a specific order:
            //    [foregroundColor][backgroundColor][formating] string [stringReset]
            $coloredString = $string;
            if ($_SERVER['SHELL'] == '/bin/bash')
            {
                // Check if given formating found
                if (isset(self::$formatings[$formating]))
                {
                    $coloredString = sprintf(self::$stringFormat, self::$formatings[$formating], $coloredString);
                }
                // Check if given background color found
                if (isset(self::$backgroundColors[$backgroundColor]))
                {
                    $coloredString = sprintf(self::$stringFormat, self::$backgroundColors[$backgroundColor], $coloredString);
                }
                // Check if given foreground color found
                if (isset(self::$foregroundColors[$foregroundColor]))
                {
                    $coloredString = sprintf(self::$stringFormat, self::$foregroundColors[$foregroundColor], $coloredString);
                }

                // Reset coloring and format
                $coloredString .= self::$stringReset;
            }

            return $coloredString;
        }
        
        /**
         * Formats the output in columns. Can take any number of parameters
         * 
         * @param string $param1 the first column value
         * @param int $param2 the left margin (as number of spaces)
         *
         * @return string
         */
        public static function columnize()
        {
            $n = PHP_EOL;
            if (isset($_SERVER['SHELL'], $_SERVER['TERM']) && $_SERVER['SHELL'] == '/bin/bash') {
                $maxChars = shell_exec('tput cols');
                $maxChars = str_replace($n, '', $maxChars);
                $maxChars = is_numeric($maxChars) ? $maxChars : self::MAX_LINE_CHARS;
            }
            else {
                $maxChars = self::MAX_LINE_CHARS;
            }
            $args = func_get_args();
            $output = '';
            
            $index = 0;
            $lastIndex = count($args) -1;
            while ($index <= $lastIndex)
            {
                if ($index % 2 == 0 && !is_numeric($args[$index]))
                {
                    $left = is_numeric($args[$index + 1]) ? $args[$index + 1] : 0;
                        
                    if ($left < $maxChars)
                    {
                        $value = '';
                        $left = ($left - strlen($output)) > 0 ? $left - strlen($output) : 0;
                        if ($left > 0)
                            $value .= implode('', array_pad(array(), $left, ' '));

                        $value .= $args[$index];
                        //we split the line if it's too long
                        if ((strlen($output.$value)) > $maxChars)
                        {
                            $trimPortion = substr($output. $value, $maxChars);
                            $output = substr($output.$value, 0, $maxChars);
                            $output .= $n;
                            $output .= self::columnize($trimPortion, $args[$index + 1]);
                        }
                        else
                            $output .= $value;
                    } 

                    $index += 2;
                }
                else
                    $index++;
            }
            
            return $output;
        }

        /**
         * Returns all foreground color names
         *
         * @return array 
         */
        public static function getForegroundColors()
        {
            return array_keys(self::$foregroundColors);
        }

        /**
         * Returns all background color names
         *
         * @return array 
         */
        public static function getBackgroundColors()
        {
            return array_keys(self::$backgroundColors);
        }
        
        /**
         * Returns all formating names
         *
         * @return type 
         */
        public static function getFormatings()
        {
            return array_keys(self::$formatings);
        }
    }
    
    $mageToolbox = new MageToolbox($magentoRoot);
    $mageToolbox->run();
?>
