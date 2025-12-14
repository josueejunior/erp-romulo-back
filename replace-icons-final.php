<?php

$replacements = [
    // Check circle
    '/<svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">\s*<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"><\/path>\s*<\/svg>/' => '<x-heroicon-o-check-circle class="w-6 h-6 text-blue-600" />',
    
    // Building office
    '/<svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">\s*<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"><\/path>\s*<\/svg>/' => '<x-heroicon-o-building-office class="w-6 h-6 text-green-600" />',
    
    // Calendar
    '/<svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">\s*<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"><\/path>\s*<\/svg>/' => '<x-heroicon-o-calendar class="w-6 h-6 text-purple-600" />',
    
    // Information circle
    '/<svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">\s*<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"><\/path>\s*<\/svg>/' => '<x-heroicon-o-information-circle class="w-6 h-6 text-blue-600" />',
    
    // Clipboard
    '/<svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">\s*<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"><\/path>\s*<\/svg>/' => '<x-heroicon-o-clipboard-document class="w-6 h-6 text-blue-600" />',
    
    // X mark
    '/<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">\s*<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"><\/path>\s*<\/svg>/' => '<x-heroicon-o-x-mark class="w-5 h-5" />',
];

$files = glob('resources/views/**/*.blade.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    $original = $content;
    
    foreach ($replacements as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }
    
    if ($content !== $original) {
        file_put_contents($file, $content);
        echo "Updated: $file\n";
    }
}

echo "Done!\n";
