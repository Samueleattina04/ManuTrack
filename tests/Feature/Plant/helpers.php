<?php

/**
 * GLB minimo valido (glTF 2.0 binario): un triangolo. Serve ai test del caricamento dei modelli 3D.
 * $extra si aggiunge al JSON della scena (es. buffer o immagini con un "uri"); $patch lo modifica.
 */
function tinyGlb(array $extra = [], ?callable $patch = null): string
{
    $bin = pack('g*', 0, 0, 0, 1, 0, 0, 0, 1, 0);
    $json = json_encode(array_merge_recursive([
        'asset' => ['version' => '2.0'],
        'scene' => 0,
        'scenes' => [['nodes' => [0]]],
        'nodes' => [['mesh' => 0]],
        'meshes' => [['primitives' => [['attributes' => ['POSITION' => 0]]]]],
        'accessors' => [['bufferView' => 0, 'componentType' => 5126, 'count' => 3, 'type' => 'VEC3', 'min' => [0, 0, 0], 'max' => [1, 1, 0]]],
        'bufferViews' => [['buffer' => 0, 'byteLength' => strlen($bin)]],
        'buffers' => [['byteLength' => strlen($bin)]],
    ], $extra));
    if ($patch) {
        $json = json_encode($patch(json_decode($json, true)));
    }
    $json .= str_repeat(' ', (4 - strlen($json) % 4) % 4);
    $body = pack('V', strlen($json)).'JSON'.$json.pack('V', strlen($bin))."BIN\0".$bin;

    return 'glTF'.pack('V', 2).pack('V', 12 + strlen($body)).$body;
}
