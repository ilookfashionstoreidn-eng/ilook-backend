<?php 
require 'vendor/autoload.php'; 
$app = require_once 'bootstrap/app.php'; 
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); 
$tables = DB::select('SHOW TABLES'); 
foreach ($tables as $t) { 
    $table = array_values((array)$t)[0]; 
    $cols = Schema::getColumnListing($table); 
    foreach ($cols as $c) { 
        if (strpos($c, 'gambar') !== false || strpos($c, 'image') !== false || strpos($c, 'foto') !== false) { 
            echo $table . '.' . $c . "\n"; 
        } 
    } 
}
