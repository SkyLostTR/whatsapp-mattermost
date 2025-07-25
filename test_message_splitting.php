<?php
declare(strict_types=1);

// Test script for message splitting functionality
require_once __DIR__ . "/vendor/autoload.php";
require_once __DIR__ . "/src/config.php";

use de\phosco\mattermost\whatsapp\JsonLConverter;

echo "Testing Message Splitting Functionality\n";
echo "=========================================\n\n";

// Create a test converter
$converter = new JsonLConverter("test-team", "test-channel");

// Use reflection to test the private splitLongMessage method
$reflection = new ReflectionClass($converter);
$splitMethod = $reflection->getMethod('splitLongMessage');
$splitMethod->setAccessible(true);

// Test 1: Short message (should not be split)
echo "Test 1: Short message\n";
$shortMessage = "This is a short message that should not be split.";
$result = $splitMethod->invokeArgs($converter, [$shortMessage]);
echo "Input length: " . mb_strlen($shortMessage, 'UTF-8') . " characters\n";
echo "Number of parts: " . count($result) . "\n";
echo "Parts: " . json_encode($result) . "\n\n";

// Test 2: Long message (should be split)
echo "Test 2: Long message\n";
$longMessage = str_repeat("This is a very long message that should be split into multiple parts. ", 500);
$result = $splitMethod->invokeArgs($converter, [$longMessage]);
echo "Input length: " . mb_strlen($longMessage, 'UTF-8') . " characters\n";
echo "Number of parts: " . count($result) . "\n";
echo "First part length: " . mb_strlen($result[0], 'UTF-8') . " characters\n";
if (count($result) > 1) {
    echo "Second part length: " . mb_strlen($result[1], 'UTF-8') . " characters\n";
    echo "Last part length: " . mb_strlen($result[count($result) - 1], 'UTF-8') . " characters\n";
}
echo "\n";

// Test 3: Message with line breaks
echo "Test 3: Message with line breaks\n";
$messageWithLines = str_repeat("Line 1\nLine 2\nLine 3\n", 200);
$result = $splitMethod->invokeArgs($converter, [$messageWithLines]);
echo "Input length: " . mb_strlen($messageWithLines, 'UTF-8') . " characters\n";
echo "Number of parts: " . count($result) . "\n";
echo "First part preview: " . substr($result[0], 0, 100) . "...\n";
echo "\n";

echo "Test completed successfully!\n";
echo "The fix should prevent the 'Post Message property is longer than the maximum permitted length' error.\n";
?>
