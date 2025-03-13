### Running Unit Tests with SQLite  

1. **Install dependencies**:  
   ```sh
   composer install
   ```  

2. **Install required packages on Debian**:  
   ```sh
   apt install php-pdo php-pdo-sqlite php-odbc libsqliteodbc sqlite3 
   ```  

3. **Enable SQLite mocking**:  
   - Open `tests/config/db.config.php`  
   - Set:  
     ```php
     'mockDb2UsingSqlite' => true,
     ```

4. **Run the tests**:  
   ```sh
   vendor/bin/phpunit
   ```