<?php
/**
 * Test script to check if ZipArchive extension is available
 * Run this to verify your PHP installation supports ZIP functionality
 */

echo "Testing PHP ZipArchive extension...\n\n";

// Check if ZipArchive class exists
if (class_exists('ZipArchive')) {
    echo "✅ ZipArchive class is available\n";
    
    // Test creating a zip file
    $zip = new ZipArchive();
    $tempFile = tempnam(sys_get_temp_dir(), 'test_zip_') . '.zip';
    
    $result = $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($result === TRUE) {
        echo "✅ Can create ZIP files\n";
        
        // Add a test file
        $zip->addFromString('test.txt', 'This is a test file for ZIP functionality.');
        $zip->close();
        
        // Verify the file was created
        if (file_exists($tempFile) && filesize($tempFile) > 0) {
            echo "✅ ZIP file creation successful\n";
            echo "✅ Test file size: " . filesize($tempFile) . " bytes\n";
            
            // Clean up
            unlink($tempFile);
            echo "✅ Cleanup completed\n\n";
            
            echo "🎉 Your PHP installation fully supports ZIP functionality!\n";
            echo "   You can use all import methods including ZIP package creation.\n";
        } else {
            echo "❌ ZIP file was not created properly\n";
            exit(1);
        }
    } else {
        echo "❌ Cannot create ZIP files. Error code: $result\n";
        echo "   Common error codes:\n";
        echo "   9  - ZipArchive::ER_NOENT (No such file)\n";
        echo "   19 - ZipArchive::ER_INVAL (Invalid argument)\n";
        exit(1);
    }
} else {
    echo "❌ ZipArchive class is NOT available\n";
    echo "   This means the PHP ZIP extension is not installed.\n";
    echo "   \n";
    echo "   Solutions:\n";
    echo "   • On Windows: Uncomment 'extension=zip' in php.ini\n";
    echo "   • On Ubuntu/Debian: sudo apt-get install php-zip\n";
    echo "   • On CentOS/RHEL: sudo yum install php-zip\n";
    echo "   • On macOS with Homebrew: brew install php --with-zip\n";
    echo "   \n";
    echo "   Without ZIP support:\n";
    echo "   • You can still use the converter\n";
    echo "   • Import packages will be created as folders instead of ZIP files\n";
    echo "   • Direct API import will still work\n";
    exit(1);
}

echo "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Operating System: " . PHP_OS . "\n";

// Check other required extensions
$requiredExtensions = ['json', 'curl'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ Extension '$ext' is loaded\n";
    } else {
        echo "❌ Extension '$ext' is NOT loaded\n";
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    echo "\n⚠️  Missing required extensions: " . implode(', ', $missingExtensions) . "\n";
    echo "   The converter may not work properly without these extensions.\n";
    exit(1);
}

echo "\n🎉 All required extensions are available!\n";
echo "Version: " . "1.1.0" . "\n";
?>