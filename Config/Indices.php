<?php
// cSpell:disable
/**
 * Creación automática de índices y ajustes de rendimiento.
 *
 * Se ejecuta una sola vez por proceso al abrir la conexión. Cada índice se crea
 * solo si la tabla y todas sus columnas existen, de modo que funciona igual en
 * bases nuevas, bases antiguas y en las dos formas de conexión (SQLite y MySQL).
 */
if (!class_exists('Indices', false)) {
    class Indices
    {
        /** Índices por tabla: nombre => columnas */
        private const INDICES = [
            'productos' => [
                'idx_productos_codigo'         => ['codigo'],
                'idx_productos_codigo_barras'  => ['codigo_barras'],
                'idx_productos_nombre'         => ['nombre'],
                'idx_productos_categoria'      => ['categoria_id'],
                'idx_productos_empresa'        => ['empresa_id'],
                'idx_productos_usuario'        => ['usuario_id'],
                'idx_productos_estado'         => ['estado'],
                'idx_productos_empresa_nombre' => ['empresa_id', 'nombre'],
                'idx_productos_empresa_codigo' => ['empresa_id', 'codigo'],
                'idx_productos_empresa_id'     => ['empresa_id', 'id'],
            ],
            'categorias' => [
                'idx_categorias_nombre'  => ['nombre'],
                'idx_categorias_empresa' => ['empresa_id'],
                'idx_categorias_usuario' => ['usuario_id'],
            ],
            'entradas_inventario' => [
                'idx_entradas_producto'      => ['producto_id'],
                'idx_entradas_fecha'         => ['fecha_entrada'],
                'idx_entradas_empresa'       => ['empresa_id'],
                'idx_entradas_usuario'       => ['usuario_id'],
                'idx_entradas_proveedor'     => ['proveedor_id'],
                'idx_entradas_vencimiento'   => ['fecha_vencimiento'],
                'idx_entradas_prod_fecha'    => ['producto_id', 'fecha_entrada'],
                'idx_entradas_empresa_fecha' => ['empresa_id', 'fecha_entrada'],
            ],
            'salidas_inventario' => [
                'idx_salidas_producto'    => ['producto_id'],
                'idx_salidas_fecha'       => ['fecha_salida'],
                'idx_salidas_empresa'     => ['empresa_id'],
                'idx_salidas_usuario'     => ['usuario_id'],
                'idx_salidas_referencia'  => ['referencia'],
                'idx_salidas_tipo'        => ['tipo_salida'],
                'idx_salidas_empresa_fecha' => ['empresa_id', 'fecha_salida'],
            ],
            'movimientos_inventario' => [
                'idx_movimientos_producto'   => ['producto_id'],
                'idx_movimientos_fecha'      => ['fecha_movimiento'],
                'idx_movimientos_empresa'    => ['empresa_id'],
                'idx_movimientos_usuario'    => ['usuario_id'],
                'idx_movimientos_tipo_ref'   => ['tipo_movimiento', 'referencia_id'],
                'idx_movimientos_empresa_fecha' => ['empresa_id', 'fecha_movimiento'],
            ],
            'creditos' => [
                'idx_creditos_empresa' => ['empresa_id'],
                'idx_creditos_cliente' => ['cliente_id'],
                'idx_creditos_estado'  => ['estado'],
                'idx_creditos_fecha'   => ['fecha_credito'],
            ],
            'detalle_creditos' => [
                'idx_detalle_creditos_credito'  => ['credito_id'],
                'idx_detalle_creditos_producto' => ['producto_id'],
            ],
            'abonos_creditos' => [
                'idx_abonos_credito' => ['credito_id'],
                'idx_abonos_fecha'   => ['fecha_abono'],
            ],
            'lotes_vencidos' => [
                'idx_lotes_vencidos_producto' => ['producto_id'],
                'idx_lotes_vencidos_entrada'  => ['entrada_id'],
                'idx_lotes_vencidos_empresa'  => ['empresa_id'],
            ],
            'producto_presentaciones' => [
                'idx_presentaciones_producto' => ['producto_id'],
            ],
            'producto_stock_presentacion' => [
                'idx_stock_presentacion_producto' => ['producto_id'],
            ],
            'usuarios' => [
                'idx_usuarios_empresa' => ['empresa_id'],
                'idx_usuarios_correo'  => ['correo'],
                'idx_usuarios_rol'     => ['rol'],
            ],
            'clientes' => [
                'idx_clientes_empresa' => ['empresa_id'],
                'idx_clientes_nombre'  => ['nombre'],
            ],
            'proveedores' => [
                'idx_proveedores_empresa' => ['empresa_id'],
                'idx_proveedores_nombre'  => ['nombre'],
            ],
        ];

        /** Evita repetir el trabajo dentro de la misma petición. */
        private static array $procesadas = [];

        public static function aplicar(PDO $db, bool $esSqlite): void
        {
            $huella = spl_object_hash($db);
            if (isset(self::$procesadas[$huella])) {
                return;
            }
            self::$procesadas[$huella] = true;

            try {
                if ($esSqlite && !self::sqliteNecesitaRevision($db)) {
                    return;
                }
            } catch (Throwable $e) {
                // Si no se puede leer la marca, se sigue con la revisión normal.
            }

            $creados = 0;
            foreach (self::INDICES as $tabla => $indices) {
                $columnasTabla = self::columnas($db, $tabla, $esSqlite);
                if (empty($columnasTabla)) {
                    continue;
                }
                foreach ($indices as $nombre => $columnas) {
                    foreach ($columnas as $columna) {
                        if (!in_array(strtolower($columna), $columnasTabla, true)) {
                            continue 2;
                        }
                    }
                    if (self::crear($db, $esSqlite, $nombre, $tabla, $columnas)) {
                        $creados++;
                    }
                }
            }

            try {
                if ($esSqlite) {
                    if ($creados > 0) {
                        $db->exec('ANALYZE');
                    }
                    self::marcarSqliteRevisado($db);
                }
            } catch (Throwable $e) {
                error_log('Indices: no se pudo finalizar la revisión: ' . $e->getMessage());
            }
        }

        private static function crear(PDO $db, bool $esSqlite, string $nombre, string $tabla, array $columnas): bool
        {
            $lista = implode(', ', $columnas);
            try {
                if ($esSqlite) {
                    $db->exec("CREATE INDEX IF NOT EXISTS {$nombre} ON {$tabla} ({$lista})");
                    return true;
                }
                if (self::indiceMysqlExiste($db, $tabla, $nombre)) {
                    return false;
                }
                $db->exec("CREATE INDEX {$nombre} ON {$tabla} ({$lista})");
                return true;
            } catch (Throwable $e) {
                error_log("Indices: {$nombre} en {$tabla}: " . $e->getMessage());
                return false;
            }
        }

        private static function indiceMysqlExiste(PDO $db, string $tabla, string $nombre): bool
        {
            try {
                $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.statistics
                                      WHERE table_schema = DATABASE() AND table_name = :tabla AND index_name = :indice');
                $stmt->execute([':tabla' => $tabla, ':indice' => $nombre]);
                return (int)$stmt->fetchColumn() > 0;
            } catch (Throwable $e) {
                return true;
            }
        }

        /** Columnas en minúscula de una tabla, o [] si la tabla no existe. */
        private static function columnas(PDO $db, string $tabla, bool $esSqlite): array
        {
            try {
                if ($esSqlite) {
                    $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :tabla");
                    $stmt->execute([':tabla' => $tabla]);
                    if (!$stmt->fetchColumn()) {
                        return [];
                    }
                    $filas = $db->query("PRAGMA table_info({$tabla})")->fetchAll(PDO::FETCH_ASSOC);
                    return array_map(static fn($fila) => strtolower((string)$fila['name']), $filas);
                }

                $stmt = $db->prepare('SELECT column_name FROM information_schema.columns
                                      WHERE table_schema = DATABASE() AND table_name = :tabla');
                $stmt->execute([':tabla' => $tabla]);
                $columnas = $stmt->fetchAll(PDO::FETCH_COLUMN);
                return array_map(static fn($columna) => strtolower((string)$columna), $columnas);
            } catch (Throwable $e) {
                return [];
            }
        }

        /**
         * Marca de revisión en SQLite: se guarda en user_version para no repetir
         * la comprobación de índices en cada apertura de la aplicación.
         */
        private const VERSION_INDICES = 4;

        private static function sqliteNecesitaRevision(PDO $db): bool
        {
            $version = (int)$db->query('PRAGMA user_version')->fetchColumn();
            return $version < self::VERSION_INDICES;
        }

        private static function marcarSqliteRevisado(PDO $db): void
        {
            $db->exec('PRAGMA user_version = ' . self::VERSION_INDICES);
        }
    }
}
