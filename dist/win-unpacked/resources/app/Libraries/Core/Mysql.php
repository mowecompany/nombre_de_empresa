<?php
    class Mysql extends Conexion
    {
        private $conexion;
        private $strquery;
        private $arrValues;

        function __construct()
        {
            $this->conexion = new Conexion();
            $this->conexion = $this->conexion->conect();
            if (!($this->conexion instanceof PDO)) {
                $this->conexion = $this->resolveFallbackConnection();
            }
        }

        private function resolveFallbackConnection()
        {
            try {
                $databasePath = __DIR__ . '/../../Config/database.php';
                if (is_file($databasePath)) {
                    require_once $databasePath;
                }

                if (class_exists('Database') && method_exists('Database', 'connect')) {
                    $fallback = Database::connect();
                    if ($fallback instanceof PDO) {
                        return $fallback;
                    }
                }
            } catch (Throwable $e) {
                error_log('MYSQL_FALLBACK_CONNECT_ERROR: ' . $e->getMessage());
            }

            return null;
        }

        private function requireConnection(): void
        {
            if (!($this->conexion instanceof PDO)) {
                $this->conexion = $this->resolveFallbackConnection();
            }

            if (!($this->conexion instanceof PDO)) {
                throw new Exception('Conexión de base de datos no válida. Verifica DB_HOST, DB_NAME, DB_USERNAME y DB_PASSWORD en Config/Config.php');
            }
        }

        //Insertar un registro
        public function insert(string $query, array $arrValues)
        {
            $this->requireConnection();
            $this->strquery = $query;
            $this->arrValues = $arrValues;
            $insert = $this->conexion->prepare($this->strquery);
            $resInsert = $insert->execute($this->arrValues);
            if($resInsert)
            {
                $lastInsert = $this->conexion->lastInsertId();
            }else{
                $lastInsert = 0;
            }
            return $lastInsert;
        }

        //Buscar un registro
        public function select(string $query)
        {
            $this->requireConnection();
            $this->strquery = $query;
            $result = $this->conexion->prepare($this->strquery);
            $result->execute();
            $data = $result->fetch(PDO::FETCH_ASSOC);
            return $data;
        }

        //Devuelve todos los registros
        public function select_all(string $query)
        {
            $this->requireConnection();
            $this->strquery = $query;
            $result = $this->conexion->prepare($this->strquery);
            $result->execute();
            $data = $result->fetchall(PDO::FETCH_ASSOC);
            return $data;
        }

        //Actualizar registros
        public function update(string $query, array $arrValues)
        {
            $this->requireConnection();
            $this->strquery = $query;
            $this->arrValues = $arrValues;
            $update = $this->conexion->prepare($this->strquery);
            $resExecute = $update->execute($this->arrValues);
            return $resExecute;
        }

        //Eliminar un registros
        public function delete(string $query)
        {
            $this->requireConnection();
            $this->strquery = $query;
            $result = $this->conexion->prepare($this->strquery);
            $del = $result->execute();
            return $del;
        }
    }
?> 