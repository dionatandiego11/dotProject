<?php
/**
 * Fix all .inc translation files in locales/pt_br
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dir = __DIR__ . '/locales/pt_br';
if (!is_dir($dir)) {
    die("Directory $dir not found!");
}

echo "<h1>Fixing Translation Files</h1><pre>\n";

$files = glob($dir . '/*.inc');

foreach ($files as $file) {
    $filename = basename($file);
    echo "Processing $filename... ";

    $content = file_get_contents($file);
    $original_len = strlen($content);

    // Check if it has <?php
    if (strpos($content, '<?php') !== false) {
        // Remove valid PHP tokens
        $tokens = token_get_all($content);
        $new_content = '';

        foreach ($tokens as $token) {
            if (is_array($token)) {
                // Skip open tag, array(), variable $content
                if ($token[0] == T_OPEN_TAG)
                    continue;
                if ($token[0] == T_VARIABLE && $token[1] == '$content')
                    continue;
                if ($token[0] == T_ARRAY)
                    continue;
                if ($token[0] == T_CLOSE_TAG)
                    continue;
            }
            // Add content
            $text = is_array($token) ? $token[1] : $token;
            $new_content .= $text;
        }

        // Cleanup remaining syntax like = array( );
        $new_content = preg_replace('/^\s*=\s*\(\s*/', '', $new_content); // remove "= ("
        $new_content = preg_replace('/^\s*\(\s*/', '', $new_content);     // remove "("
        $new_content = preg_replace('/\);\s*$/', '', $new_content);       // remove ");"
        $new_content = preg_replace('/\)\s*$/', '', $new_content);         // remove ")"

        // Save back
        if (trim($new_content) !== trim($content)) {
            file_put_contents($file, $new_content);
            echo "FIXED (size: $original_len -> " . strlen($new_content) . ")\n";
        } else {
            echo "NO CHANGE NEEDED\n";
        }
    } else {
        echo "OK (already raw format)\n";
    }
}

echo "\n</pre>";
echo "<p style='color:green;font-weight:bold'>✅ All files processed!</p>";
echo "<p><a href='clear_lang_cache.php'>Clear Cache & Reload</a></p>";
