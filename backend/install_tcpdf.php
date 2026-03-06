<?php
echo "🚀 Starting TCPDF Installation...\n\n";

// Check if Composer is available
echo "📦 Checking Composer availability...\n";
$composerAvailable = false;

// Try to run composer
exec('composer --version 2>&1', $output, $returnCode);
if ($returnCode === 0) {
    echo "✅ Composer is available\n";
    $composerAvailable = true;
} else {
    echo "❌ Composer not found, trying alternative installation...\n";
}

if ($composerAvailable) {
    // Install TCPDF via Composer
    echo "📥 Installing TCPDF via Composer...\n";
    exec('composer require tecnickcom/tcpdf --no-interaction 2>&1', $output, $returnCode);
    
    if ($returnCode === 0) {
        echo "✅ TCPDF installed successfully via Composer\n";
    } else {
        echo "❌ Composer installation failed, trying manual installation...\n";
        $composerAvailable = false;
    }
}

if (!$composerAvailable) {
    // Manual installation
    echo "📥 Downloading TCPDF manually...\n";
    
    // Create vendor directory
    if (!is_dir('vendor')) {
        mkdir('vendor', 0755, true);
        echo "✅ Created vendor directory\n";
    }
    
    if (!is_dir('vendor/tecnickcom')) {
        mkdir('vendor/tecnickcom', 0755, true);
        echo "✅ Created tecnickcom directory\n";
    }
    
    // Download TCPDF
    $tcpdfUrl = 'https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.6.5.zip';
    $zipFile = 'tcpdf.zip';
    
    echo "📥 Downloading TCPDF from GitHub...\n";
    $zipContent = file_get_contents($tcpdfUrl);
    
    if ($zipContent !== false) {
        file_put_contents($zipFile, $zipContent);
        echo "✅ Downloaded TCPDF zip file\n";
        
        // Extract zip
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo('vendor/tecnickcom/');
            $zip->close();
            echo "✅ Extracted TCPDF files\n";
            
            // Rename directory
            if (is_dir('vendor/tecnickcom/TCPDF-6.6.5')) {
                rename('vendor/tecnickcom/TCPDF-6.6.5', 'vendor/tecnickcom/tcpdf');
                echo "✅ Renamed TCPDF directory\n";
            }
            
            // Clean up
            unlink($zipFile);
            echo "✅ Cleaned up temporary files\n";
        } else {
            echo "❌ Failed to extract zip file\n";
        }
    } else {
        echo "❌ Failed to download TCPDF\n";
    }
}

// Create uploads directory
echo "📁 Creating uploads directory...\n";
if (!is_dir('uploads')) {
    mkdir('uploads', 0755, true);
    echo "✅ Created uploads directory\n";
}

if (!is_dir('uploads/pdfs')) {
    mkdir('uploads/pdfs', 0755, true);
    echo "✅ Created pdfs directory\n";
}

// Create autoload.php if it doesn't exist
echo "🔧 Creating autoload file...\n";
$autoloadContent = '<?php
// Simple autoloader for TCPDF
spl_autoload_register(function ($class) {
    // TCPDF class autoloading
    if ($class === "TCPDF") {
        $tcpdfPath = __DIR__ . "/vendor/tecnickcom/tcpdf/tcpdf.php";
        if (file_exists($tcpdfPath)) {
            require_once $tcpdfPath;
            return true;
        }
    }
    return false;
});
?>';

if (!file_exists('vendor/autoload.php')) {
    file_put_contents('vendor/autoload.php', $autoloadContent);
    echo "✅ Created autoload.php\n";
}

// Test TCPDF installation
echo "🧪 Testing TCPDF installation...\n";
try {
    require_once 'vendor/autoload.php';
    if (class_exists('TCPDF')) {
        echo "✅ TCPDF class loaded successfully\n";
        
        // Create a test PDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('CRM System');
        $pdf->SetTitle('Test PDF');
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'TCPDF is working correctly!', 0, 1, 'C');
        
        $testFile = 'uploads/pdfs/test.pdf';
        $pdf->Output($testFile, 'F');
        
        if (file_exists($testFile)) {
            echo "✅ Test PDF created successfully\n";
            unlink($testFile); // Clean up test file
        } else {
            echo "❌ Test PDF creation failed\n";
        }
    } else {
        echo "❌ TCPDF class not found\n";
    }
} catch (Exception $e) {
    echo "❌ Error testing TCPDF: " . $e->getMessage() . "\n";
}

echo "\n🎉 Installation completed!\n";
echo "📋 Summary:\n";
echo "   - TCPDF library: " . (class_exists('TCPDF') ? '✅ Installed' : '❌ Failed') . "\n";
echo "   - Uploads directory: " . (is_dir('uploads/pdfs') ? '✅ Created' : '❌ Failed') . "\n";
echo "   - Autoload file: " . (file_exists('vendor/autoload.php') ? '✅ Created' : '❌ Failed') . "\n";

echo "\n🚀 You can now use the PDF generation feature in your CRM!\n";
?>