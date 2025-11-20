<?php
require_once 'config.php';

try {
    $pdo = getDB();
    $sql = file_get_contents('mxbttmmy_fidels_pizza.sql');
    
    // Disable foreign key checks to avoid issues with table creation order
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    $pdo->exec($sql);
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "Database import successful!\n";
} catch (PDOException $e) {
    echo "Database import failed: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
