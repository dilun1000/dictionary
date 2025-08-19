<?php

namespace App\Core\Model;

use PDO;

class Model
{
    public static $table = '';
    public static $primaryKey = 'id';
    public $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Get all records
    public function all()
    {
        $stmt = $this->pdo->query("SELECT * FROM " . static::$table);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Find by ID
    public function find($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Insert new record
    public function insert(array $data)
    {
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO " . static::$table . " ($columns) VALUES ($placeholders)");
        return $stmt->execute(array_values($data));
    }

    // Update record by ID
    public function update($id, array $data)
    {
        $fields = implode(' = ?, ', array_keys($data)) . ' = ?';
        $values = array_values($data);
        $values[] = $id;
        $stmt = $this->pdo->prepare("UPDATE " . static::$table . " SET $fields WHERE " . static::$primaryKey . " = ?");
        return $stmt->execute($values);
    }

    // Delete record by ID
    public function delete($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?");
        return $stmt->execute([$id]);
    }
}