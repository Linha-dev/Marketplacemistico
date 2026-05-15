<?php
/**
 * Frontend SPA fallback para servidores sem mod_rewrite ativo.
 * Se o .htaccess estiver funcionando corretamente, este arquivo não será chamado.
 */
$htmlFile = __DIR__ . '/index.html';
if (file_exists($htmlFile)) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($htmlFile);
} else {
    // Fallback: redireciona para /api/health para mostrar que a API está funcionando
    header('Location: /api/health');
}
