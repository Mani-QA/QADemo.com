<?php
class Database {
    private $db;

    public function __construct() {
        try {
            $this->db = new SQLite3(__DIR__ . '/../database/ecommerce.db');
            $this->initializeDatabase();
        } catch (Exception $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    private function initializeDatabase() {
        // Create users table
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                user_type TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Create products table
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                price REAL NOT NULL,
                stock INTEGER NOT NULL,
                image_path TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Create orders table
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                total_amount REAL NOT NULL,
                shipping_address TEXT NOT NULL,
                payment_details TEXT NOT NULL,
                status TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');

        // Create order_items table
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                price REAL NOT NULL,
                FOREIGN KEY (order_id) REFERENCES orders(id),
                FOREIGN KEY (product_id) REFERENCES products(id)
            )
        ');

        // Insert default users if they don't exist
        $this->insertDefaultUsers();
    }

    private function insertDefaultUsers() {
        $defaultUsers = [
            ['standard_user', password_hash('standard123', PASSWORD_DEFAULT), 'standard'],
            ['locked_user', password_hash('locked123', PASSWORD_DEFAULT), 'locked'],
            ['admin_user', password_hash('admin123', PASSWORD_DEFAULT), 'admin']
        ];

        $stmt = $this->db->prepare('INSERT OR IGNORE INTO users (username, password, user_type) VALUES (:username, :password, :user_type)');
        
        foreach ($defaultUsers as $user) {
            $stmt->bindValue(':username', $user[0], SQLITE3_TEXT);
            $stmt->bindValue(':password', $user[1], SQLITE3_TEXT);
            $stmt->bindValue(':user_type', $user[2], SQLITE3_TEXT);
            $stmt->execute();
        }
    }    public function getConnection() {
        return $this->db;
    }
}