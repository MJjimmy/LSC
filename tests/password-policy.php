<?php

declare(strict_types=1);

// Offline: only the production password policy, no bootstrap, accounts, or network.
require dirname(__DIR__) . '/app/Users/PasswordPolicy.php';
use LSC\Users\PasswordPolicy;

$checks = 0;
$failures = [];
$cases = [
    '12345678' => false,
    '12345678901234567890' => false,
    '123456789012345!' => false,
    'Copper owls fly over misty hills!' => false, // missing number
    'Copper owls fly over misty hills7' => false, // missing special character
    'copper owls fly over misty hills7!' => false, // missing uppercase
    'COPPER OWLS FLY OVER MISTY HILLS7!' => false, // missing lowercase
    'Abc7!xy' => false,
    'Password12345678!' => false,
    'Copper owls fly over misty hills7!' => true,
    'Rêves de cuivre et de lumière7!' => true,
    'Copper owls fly over misty hills7€' => true,
    'Copper owls fly over misty hills7 ' => false, // whitespace is not a special character
    "Copper owls fly over misty hills7\0!" => false,
    str_repeat('A', 70) . 'b7!' => false,
];
foreach ($cases as $password => $expected) {
    $checks++;
    $accepted = PasswordPolicy::error((string)$password) === null;
    if ($accepted !== $expected) $failures[] = ['case'=>$checks, 'expected_accepted'=>$expected];
}
echo json_encode(['checks'=>$checks, 'failed'=>count($failures), 'failures'=>$failures], JSON_PRETTY_PRINT) . "\n";
exit($failures ? 1 : 0);
