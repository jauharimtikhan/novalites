<?php

use Novalites\Http\Response;
use Novalites\Router\Route;

Route::get('/storage/{slug*}', function ($request, $slug) {
    $baseUploadDir = realpath(constant('BASE_PATH') . '/storage/uploads');
    $requestedPath = $baseUploadDir . '/' . $slug;

    $realFilePath = realpath($requestedPath);

    // Security check wajib
    if ($realFilePath === false || strpos($realFilePath, $baseUploadDir) !== 0) {
        Response::error('Asset tidak ditemukan atau akses ditolak.', 404);
        return;
    }

    Response::serveAsset($realFilePath);
});
