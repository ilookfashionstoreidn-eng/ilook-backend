<?php
try { 
    $controller = app()->make('App\Http\Controllers\ProductListController');
    $response = $controller->spkOptions();
    echo json_encode($response->getData()); 
} catch (\Exception $e) { 
    echo $e->getMessage(); 
}
