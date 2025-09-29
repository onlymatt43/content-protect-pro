<?php
require_once 'includes/cpp-token-helpers.php';

echo "=== TEST DES FONCTIONS TOKEN ===\n";

// Test 1: Génération de token sécurisé
echo "1. Génération de token sécurisé:\n";
$token = cpp_generate_secure_token();
echo "Token généré: " . $token . "\n";
echo "Longueur: " . strlen($token) . " caractères\n\n";

// Test 2: Génération de code simple à partir du token
echo "2. Code simple généré à partir du token:\n";
$simple_code = cpp_generate_simple_code_from_token($token);
echo "Code simple: " . $simple_code . "\n";
echo "Longueur: " . strlen($simple_code) . " caractères\n\n";

// Test 3: Conversion de durées
echo "3. Tests de conversion de durée:\n";
$test_durations = [
    ['value' => 30, 'unit' => 'minutes'],
    ['value' => 2, 'unit' => 'hours'],
    ['value' => 1, 'unit' => 'days'],
    ['value' => 1, 'unit' => 'months'],
    ['value' => 1, 'unit' => 'years']
];

foreach ($test_durations as $duration) {
    $minutes = cpp_convert_to_minutes($duration['value'], $duration['unit']);
    echo "- {$duration['value']} {$duration['unit']} = {$minutes} minutes\n";
}

echo "\n4. Test IP Validation:\n";
$test_ips = [
    ['ip' => '192.168.1.100', 'range' => '192.168.1.0/24'],
    ['ip' => '10.0.0.5', 'range' => '192.168.1.0/24'],
    ['ip' => '203.0.113.10', 'range' => '203.0.113.10']
];

foreach ($test_ips as $test) {
    $valid = cpp_ip_in_range($test['ip'], $test['range']);
    $status = $valid ? "✅ AUTORISÉ" : "❌ REFUSÉ";
    echo "- IP {$test['ip']} dans {$test['range']}: {$status}\n";
}

echo "\n=== GÉNÉRATION DE CODES EN LOT ===\n";
echo "Test génération 5 codes avec préfixe PROMO:\n";
for ($i = 1; $i <= 5; $i++) {
    $token = cpp_generate_secure_token();
    $code = "PROMO-" . str_pad($i, 2, '0', STR_PAD_LEFT);
    $simple_from_token = cpp_generate_simple_code_from_token($token);
    
    echo "Code #{$i}: {$code}\n";
    echo "  Token: " . substr($token, 0, 16) . "...\n";
    echo "  Code alternatif du token: {$simple_from_token}\n";
    echo "  Durée: " . cpp_convert_to_minutes(2, 'hours') . " minutes (2 heures)\n\n";
}
