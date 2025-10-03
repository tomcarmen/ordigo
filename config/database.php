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
            
            // Abilita foreign keys
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            
            // Crea tabelle se non esistono
            $this->createTables();
            $this->insertDefaultData();
            
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
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        );

        -- Tabella offerte stock
        CREATE TABLE IF NOT EXISTS stock_offers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            discount_type TEXT CHECK(discount_type IN ('percentage', 'fixed')) DEFAULT 'percentage',
            discount_value DECIMAL(10,2) NOT NULL,
            min_quantity INTEGER DEFAULT 1,
            max_quantity INTEGER,
            start_date DATETIME,
            end_date DATETIME,
            active BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        );

        -- Tabella ordini
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number VARCHAR(20) UNIQUE NOT NULL,
            customer_name VARCHAR(200),
            customer_phone VARCHAR(20),
            total_amount DECIMAL(10,2) NOT NULL,
            status TEXT CHECK(status IN ('pending', 'preparing', 'ready', 'completed', 'cancelled')) DEFAULT 'pending',
            payment_method TEXT CHECK(payment_method IN ('cash', 'card', 'digital')) DEFAULT 'cash',
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

        -- Indici per performance
        CREATE INDEX IF NOT EXISTS idx_products_category ON products(category_id);
        CREATE INDEX IF NOT EXISTS idx_products_active ON products(active);
        CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
        CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at);
        CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);
        CREATE INDEX IF NOT EXISTS idx_sync_log_status ON sync_log(status);
        ";
        
        $this->pdo->exec($sql);
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