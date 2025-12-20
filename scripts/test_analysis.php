<?php
/**
 * Script de prueba para ejecutar análisis directamente
 */

// Simular parámetros GET
$_GET['action'] = 'init';
$_GET['chunk_size'] = '1000';

// Incluir el script principal
require_once 'backup_chunk.php';
