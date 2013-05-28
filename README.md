magento-toolbox
===============

# Description
This script has some utilities aimed at ease Magento development:
- Cache cleaning (memcached too)
- Base URL change
- Backend user's password change
- Enable/disable template hints
- Permission fixing
- Reindex
- Change index mode
- Modify database setup
- Current status of the magento installation (template hints enabled, database setup, URLs, indexes, etc.)
- Allows the execution of php files using the Magento includes
    
This script will try to auto detect the relative path of the magento root folder from the current script location
and it will modify itself (the first time it is run). Thereafter it will use the stored setting until you manually
change it (editing the script) or the magento root path changes.

You can see a detailed command help executing the script without parameters.

    php mage_toolbox.php

# Additional requisites
- netcat: by default this script restarts the memcached service, but if netcat is installed it will flush the cache without restart
