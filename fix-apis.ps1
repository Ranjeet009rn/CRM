# PowerShell script to replace all localhost API URLs with production server URLs

$componentsPath = ".\frontend\src\Components"

# Get all .jsx files recursively
$files = Get-ChildItem -Path $componentsPath -Filter "*.jsx" -Recurse

$replacements = 0

foreach ($file in $files) {
    $content = Get-Content $file.FullName -Raw
    $originalContent = $content
    
    # Replace localhost URLs with production URLs
    $content = $content -replace 'http://localhost/CRM/CRM/backend', 'https://crm.swift2ai.com/backend'
    
    # If content changed, write it back
    if ($content -ne $originalContent) {
        Set-Content -Path $file.FullName -Value $content -NoNewline
        Write-Host "✅ Updated: $($file.Name)" -ForegroundColor Green
        $replacements++
    }
}

Write-Host "`n✅ Complete! Updated $replacements files" -ForegroundColor Cyan
Write-Host "All components now use: https://crm.swift2ai.com/backend" -ForegroundColor Yellow
