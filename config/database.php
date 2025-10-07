<?php
/**
 * Configurazione Database SQLite per OrdiGO
 * Sistema di gestione ordini per Festa Oratorio
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $dbPath;
    
    private function __construct() {
        $this->dbPath = __DIR__ . '/../database/ordigo.db';
        $this->initializeDatabase();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initializeDatabase() {
        try {
            // Crea directory database se non esiste
            $dbDir = dirname($this->dbPath);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            // Connessione SQLite
            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Timeout attesa lock per operazioni di migrazione
            $this->pdo->exec('PRAGMA busy_timeout = 20000');
            // Modalità WAL per migliorare la concorrenza lettura/scrittura
            $this->pdo->exec("PRAGMA journal_mode = WAL");
            // Riduce contesa mantenendo performance
            $this->pdo->exec("PRAGMA synchronous = NORMAL");
            
            // Abilita foreign keys
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            
            // Crea tabelle se non esistono
            $this->createTables();
            $this->insertDefaultData();
            $this->migrateSchema();
            
        } catch (PDOException $e) {
            die('Errore connessione database: ' . $e->getMessage());
        }
    }
    
    private function createTables() {
        $sql = "
        -- Tabella categorie
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            color VARCHAR(7) DEFAULT '#3B82F6',
            icon VARCHAR(50) DEFAULT 'tag',
            active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Tabella prodotti
        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(200) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            category_id INTEGER,
            stock_quantity INTEGER DEFAULT 0,
            min_stock_level INTEGER DEFAULT 5,
            image_url VARCHAR(500),
            active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        );

        -- Tabella aggiunte/extra
        CREATE TABLE IF NOT EXISTS product_extras (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            name VARCHAR(100) NOT NULL,
            purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            selling_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            stock_quantity INTEGER DEFAULT 0,
            min_stock_level INTEGER DEFAULT 5,
            active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        );

        -- Tabella offerte stock per bundle di prodotto
        CREATE TABLE IF NOT EXISTS product_offers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            quantity INTEGER NOT NULL,
            offer_price DECIMAL(10,2) NOT NULL,
            active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        );

        -- Tabella offerte stock rimossa su richiesta

        -- Tabella ordini
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number VARCHAR(20) UNIQUE NOT NULL,
            customer_name VARCHAR(200),
            customer_phone VARCHAR(20),
            total_amount DECIMAL(10,2) NOT NULL,
            status TEXT CHECK(status IN ('pending', 'preparing', 'ready', 'completed', 'cancelled')) DEFAULT 'pending',
            payment_method TEXT CHECK(payment_method IN ('Contanti', 'Bancomat', 'Satispay')) DEFAULT 'Contanti',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ready_at DATETIME,
            completed_at DATETIME
        );

        -- Tabella dettagli ordini
        CREATE TABLE IF NOT EXISTS order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            product_name VARCHAR(200) NOT NULL,
            quantity INTEGER NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            extras TEXT, -- JSON per aggiunte
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id)
        );

        -- Tabella log sincronizzazione
        CREATE TABLE IF NOT EXISTS sync_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            operation_type VARCHAR(50) NOT NULL,
            table_name VARCHAR(50) NOT NULL,
            record_id INTEGER,
            data TEXT, -- JSON
            status TEXT CHECK(status IN ('pending', 'synced', 'failed')) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            synced_at DATETIME
        );

        -- Tabella spese generali (costi pre-evento)
        CREATE TABLE IF NOT EXISTS general_expenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            description VARCHAR(200) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            notes TEXT,
            expense_date DATE DEFAULT CURRENT_DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- Indici per performance
        CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id);
        CREATE INDEX IF NOT EXISTS idx_products_active ON products(active);
        CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
        CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);
        CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);
        CREATE INDEX IF NOT EXISTS idx_sync_log_status ON sync_log(status);
        CREATE INDEX IF NOT EXISTS idx_product_offers_product ON product_offers(product_id);
        CREATE INDEX IF NOT EXISTS idx_general_expenses_created ON general_expenses(created_at);
        ";
        
        $this->pdo->exec($sql);
    }
    
    private function migrateSchema() {
        // Migrazione schema prodotti: rinomina price -> purchase_price, aggiunge selling_price
        try {
            // Rimuovi tabella stock_offers se presente
            try {
                $this->pdo->exec("DROP TABLE IF EXISTS stock_offers");
            } catch (Exception $e) {
                error_log('Drop stock_offers fallito: ' . $e->getMessage());
            }
            // Leggi colonne esistenti
            $stmt = $this->pdo->query("PRAGMA table_info(products)");
            $columns = $stmt->fetchAll();
            $colNames = array_map(function($c){ return $c['name']; }, $columns);

            $hasPrice = in_array('price', $colNames);
            $hasPurchasePrice = in_array('purchase_price', $colNames);
            $hasSellingPrice = in_array('selling_price', $colNames);

            // Rinomina price -> purchase_price se necessario
            if ($hasPrice && !$hasPurchasePrice) {
                $this->pdo->exec("ALTER TABLE products RENAME COLUMN price TO purchase_price");
                // Aggiorna lista colonne dopo rinomina
                $stmt = $this->pdo->query("PRAGMA table_info(products)");
                $columns = $stmt->fetchAll();
                $colNames = array_map(function($c){ return $c['name']; }, $columns);
                $hasPurchasePrice = in_array('purchase_price', $colNames);
            }

            // Aggiungi selling_price se mancante
            if (!$hasSellingPrice) {
                $this->pdo->exec("ALTER TABLE products ADD COLUMN selling_price DECIMAL(10,2) DEFAULT 0");
            }

            // Inizializza selling_price = purchase_price se è 0 o NULL
            if (in_array('purchase_price', $colNames)) {
                $this->pdo->exec("UPDATE products SET selling_price = purchase_price WHERE selling_price IS NULL OR selling_price = 0");
            }
        } catch (Exception $e) {
            // Log senza interrompere
            error_log('Migrazione schema prodotti fallita: ' . $e->getMessage());
        }
        
        // Migrazione schema extras: rinomina price -> purchase_price, aggiunge selling_price, stock e min_stock
        try {
            $stmt = $this->pdo->query("PRAGMA table_info(product_extras)");
            $columns = $stmt->fetchAll();
            $colNames = array_map(function($c){ return $c['name']; }, $columns);

            $hasPrice = in_array('price', $colNames);
            $hasPurchasePrice = in_array('purchase_price', $colNames);
            $hasSellingPrice = in_array('selling_price', $colNames);
            $hasStockQty = in_array('stock_quantity', $colNames);
            $hasMinStock = in_array('min_stock_level', $colNames);

            // Rinomina price -> purchase_price se necessario
            if ($hasPrice && !$hasPurchasePrice) {
                $this->pdo->exec("ALTER TABLE product_extras RENAME COLUMN price TO purchase_price");
                // ricarica colonne
                $stmt = $this->pdo->query("PRAGMA table_info(product_extras)");
                $columns = $stmt->fetchAll();
                $colNames = array_map(function($c){ return $c['name']; }, $columns);
                $hasPurchasePrice = in_array('purchase_price', $colNames);
            }

            // Aggiungi selling_price se mancante
            if (!$hasSellingPrice) {
                $this->pdo->exec("ALTER TABLE product_extras ADD COLUMN selling_price DECIMAL(10,2) DEFAULT 0");
            }

            // Aggiungi stock_quantity se mancante
            if (!$hasStockQty) {
                $this->pdo->exec("ALTER TABLE product_extras ADD COLUMN stock_quantity INTEGER DEFAULT 0");
            }

            // Aggiungi min_stock_level se mancante
            if (!$hasMinStock) {
                $this->pdo->exec("ALTER TABLE product_extras ADD COLUMN min_stock_level INTEGER DEFAULT 5");
            }

            // Inizializza selling_price = purchase_price se mancante/zero
            if (in_array('purchase_price', $colNames)) {
                $this->pdo->exec("UPDATE product_extras SET selling_price = purchase_price WHERE selling_price IS NULL OR selling_price = 0");
            }
        } catch (Exception $e) {
            error_log('Migrazione schema extras fallita: ' . $e->getMessage());
        }

        // Migrazione tabella orders: aggiorna CHECK di payment_method e mappa valori esistenti
        try {
            $stmt = $this->pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='orders'");
            $ordersSql = $stmt->fetchColumn();
            if ($stmt) { $stmt->closeCursor(); }
            if ($ordersSql) {
                // Verifica se il nuovo CHECK con valori in italiano è presente
                $normalized = strtolower(preg_replace('/\s+/', '', $ordersSql));
                $hasNewCheck = strpos($normalized, "check(payment_methodin('contanti','bancomat','satispay'))") !== false;
                if (!$hasNewCheck) {
                    // Mappa valori vecchi a nuovi
                    try {
                        $this->pdo->exec("UPDATE orders SET payment_method = CASE payment_method WHEN 'cash' THEN 'Contanti' WHEN 'card' THEN 'Bancomat' WHEN 'digital' THEN 'Satispay' ELSE payment_method END");
                    } catch (Exception $e) {
                        // Ignora se tabella vuota
                    }

                    // Ricrea la tabella con nuovo CHECK preservando i dati
                    $this->pdo->exec('PRAGMA foreign_keys = OFF');
                    // Esegue checkpoint WAL per liberare lock residui
                    try { $this->pdo->exec('PRAGMA wal_checkpoint(FULL)'); } catch (Exception $e) {}
                    $this->pdo->beginTransaction();
                    $this->pdo->exec("
                        CREATE TABLE IF NOT EXISTS orders_new (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            order_number VARCHAR(20) UNIQUE NOT NULL,
                            customer_name VARCHAR(200),
                            customer_phone VARCHAR(20),
                            total_amount DECIMAL(10,2) NOT NULL,
                            status TEXT CHECK(status IN ('pending', 'preparing', 'ready', 'completed', 'cancelled')) DEFAULT 'pending',
                            payment_method TEXT CHECK(payment_method IN ('Contanti', 'Bancomat', 'Satispay')) DEFAULT 'Contanti',
                            notes TEXT,
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                            ready_at DATETIME,
                            completed_at DATETIME
                        );
                    ");
                    $this->pdo->exec("
                        INSERT INTO orders_new (id, order_number, customer_name, customer_phone, total_amount, status, payment_method, notes, created_at, ready_at, completed_at)
                        SELECT id, order_number, customer_name, customer_phone, total_amount, status,
                               CASE payment_method
                                   WHEN 'cash' THEN 'Contanti'
                                   WHEN 'card' THEN 'Bancomat'
                                   WHEN 'digital' THEN 'Satispay'
                                   ELSE payment_method
                               END as payment_method,
                               notes, created_at, ready_at, completed_at FROM orders
                    ");
                    $this->pdo->exec("DROP TABLE orders");
                    $this->pdo->exec("ALTER TABLE orders_new RENAME TO orders");
                    // Ricrea indici
                    $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)");
                    $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at)");
                    $this->pdo->commit();
                    $this->pdo->exec('PRAGMA foreign_keys = ON');
                } else {
                    // Effettua comunque mapping di eventuali valori vecchi
                    try {
                        $this->pdo->exec("UPDATE orders SET payment_method = CASE payment_method WHEN 'cash' THEN 'Contanti' WHEN 'card' THEN 'Bancomat' WHEN 'digital' THEN 'Satispay' ELSE payment_method END");
                    } catch (Exception $e) {}
                }
            }
        } catch (Exception $e) {
            try { $this->pdo->rollBack(); } catch (Exception $ee) {}
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            error_log('Migrazione schema orders fallita: ' . $e->getMessage());
        }

        // Migrazione tabella general_expenses: aggiunge expense_date se mancante e backfill
        try {
            $stmt = $this->pdo->query("PRAGMA table_info(general_expenses)");
            $columns = $stmt->fetchAll();
            $colNames = array_map(function($c){ return $c['name']; }, $columns);
            $hasExpenseDate = in_array('expense_date', $colNames);
            if (!$hasExpenseDate) {
                $this->pdo->exec("ALTER TABLE general_expenses ADD COLUMN expense_date DATE");
                // Backfill: imposta expense_date = DATE(created_at)
                try {
                    $this->pdo->exec("UPDATE general_expenses SET expense_date = DATE(created_at) WHERE expense_date IS NULL");
                } catch (Exception $e) {}
                // Crea indice se non esiste
                try {
                    $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_general_expenses_date ON general_expenses(expense_date)");
                } catch (Exception $e) {}
            }
        } catch (Exception $e) {
            error_log('Migrazione schema general_expenses fallita: ' . $e->getMessage());
        }
    }
    
    private function insertDefaultData() {
        // Controlla se esistono già dati
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM categories");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $categories = [
                ['Panini', 'Panini e toast vari', '#EF4444', 'utensils'],
                ['Bevande', 'Bibite, acqua, caffè', '#3B82F6', 'coffee'],
                ['Dolci', 'Torte, gelati, dolci vari', '#F59E0B', 'cake'],
                ['Snack', 'Patatine, pop-corn, caramelle', '#10B981', 'cookie'],
                ['Primi Piatti', 'Pasta, risotti, zuppe', '#8B5CF6', 'bowl-food']
            ];
            
            $stmt = $this->pdo->prepare("
                INSERT INTO categories (name, description, color, icon) 
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($categories as $category) {
                $stmt->execute($category);
            }
            
            // Prodotti di esempio
            $products = [
                ['Panino Prosciutto', 'Panino con prosciutto crudo e mozzarella', 4.50, 1, 50],
                ['Panino Salame', 'Panino con salame e formaggio', 4.00, 1, 30],
                ['Toast Prosciutto', 'Toast con prosciutto cotto e formaggio', 3.50, 1, 40],
                ['Coca Cola', 'Lattina 33cl', 2.00, 2, 100],
                ['Acqua Naturale', 'Bottiglia 50cl', 1.00, 2, 80],
                ['Caffè', 'Espresso italiano', 1.50, 2, 200],
                ['Tiramisù', 'Porzione singola', 3.00, 3, 20],
                ['Gelato', 'Coppetta 2 gusti', 2.50, 3, 50],
                ['Patatine', 'Sacchetto 50g', 1.50, 4, 60],
                ['Pasta al Pomodoro', 'Porzione abbondante', 6.00, 5, 25]
            ];
            
            $stmt = $this->pdo->prepare("
                INSERT INTO products (name, description, price, category_id, stock_quantity) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            foreach ($products as $product) {
                $stmt->execute($product);
            }
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Database query error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    // Funzioni di utilità per sincronizzazione
    public function logSyncOperation($operation, $table, $recordId, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO sync_log (operation_type, table_name, record_id, data) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$operation, $table, $recordId, json_encode($data)]);
    }
    
    public function getPendingSyncOperations() {
        $stmt = $this->pdo->query("
            SELECT * FROM sync_log 
            WHERE status = 'pending' 
            ORDER BY created_at ASC
        ");
        return $stmt->fetchAll();
    }
    
    public function markSyncCompleted($syncId) {
        $stmt = $this->pdo->prepare("
            UPDATE sync_log 
            SET status = 'synced', synced_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        return $stmt->execute([$syncId]);
    }
}

// Funzione helper per ottenere l'istanza del database
function getDB() {
    return Database::getInstance();
}
?>