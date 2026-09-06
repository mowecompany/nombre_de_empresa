# API Local - Documentación Completa

**Versión:** 1.0  
**Estado:** ✅ LISTO PARA USO  
**Fecha:** 2026-09-01  

---

## 📋 Índice
1. [Introducción](#introducción)
2. [Autenticación](#autenticación)
3. [Endpoints](#endpoints)
4. [Ejemplos de Uso](#ejemplos-de-uso)
5. [Códigos de Error](#códigos-de-error)
6. [Rate Limiting](#rate-limiting)
7. [CORS](#cors)

---

## Introducción

Este API proporciona una interfaz REST para el sistema multi-caja, permitiendo:
- ✅ Operaciones de entrada/salida de inventario
- ✅ Consulta de stock en tiempo real
- ✅ Auditoría de movimientos
- ✅ Autenticación segura
- ✅ Control de concurrencia

### Base URL
```
http://192.168.1.100/nombre_de_empresa/api/v1
```

### Formato de respuestas
Todas las respuestas son en **JSON**:
```json
{
  "success": true,
  "data": { ... },
  "message": "Descripción",
  "timestamp": "2026-09-01 14:30:45",
  "version": "1.0"
}
```

---

## Autenticación

### Endpoint: POST /auth/login
Autentica usuario y retorna sesión.

**Request:**
```json
{
  "email": "usuario@empresa.com",
  "password": "contraseña"
}
```

**Response (201 - Exitoso):**
```json
{
  "success": true,
  "data": {
    "usuario_id": 1,
    "nombre": "Juan Pérez",
    "email": "usuario@empresa.com",
    "rol": "administrador",
    "empresa_id": 1,
    "token": "abc123def456xyz"
  },
  "message": "Login exitoso"
}
```

**Response (401 - Error):**
```json
{
  "success": false,
  "message": "Credenciales inválidas",
  "data": null
}
```

**Notas:**
- El token retornado es el session_id
- Se debe enviar en header: `Cookie: PHPSESSID=[token]`
- Válido para toda la sesión activa

---

## Endpoints

### 1. Health Check

**Endpoint:** `GET /health`

**Descripción:** Verifica si el API está operativo

**Request:**
```bash
curl http://192.168.1.100/nombre_de_empresa/api/v1/health
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "status": "online",
    "database": "sqlite",
    "version": "1.0",
    "uptime": "2026-09-01 14:30:45"
  },
  "message": "API operativa"
}
```

---

### 2. Registrar Entrada de Inventario

**Endpoint:** `POST /inventario/entrada`

**Autenticación:** Requerida (sesión activa)

**Description:** Registra entrada de productos al inventario

**Request:**
```json
{
  "producto_id": 5,
  "proveedor_id": 1,
  "cantidad": 100,
  "precio_compra": 30000,
  "porcentaje_ganancia": 20,
  "notas": "Compra a proveedor XYZ"
}
```

**Parámetros:**
| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| producto_id | int | ✅ | ID del producto |
| proveedor_id | int | ✅ | ID del proveedor |
| cantidad | int | ✅ | Cantidad a agregar |
| precio_compra | float | ✅ | Precio unitario de compra |
| porcentaje_ganancia | float | ❌ | Porcentaje de ganancia (default: 0) |
| notas | string | ❌ | Notas adicionales |

**Response (201 - Exitoso):**
```json
{
  "success": true,
  "data": {
    "entrada_id": 45,
    "mensaje": "Entrada registrada correctamente",
    "producto_id": 5,
    "cantidad": 100,
    "precio_compra": 30000
  },
  "message": "Entrada registrada correctamente"
}
```

**Response (400 - Error):**
```json
{
  "success": false,
  "message": "Datos inválidos: producto_id, cantidad, precio_compra son requeridos",
  "data": null
}
```

**Notas:**
- Usa `BEGIN IMMEDIATE` para transacción segura
- Actualiza stock automáticamente
- Registra movimiento en auditoría
- Calcula precio de venta basado en ganancia

---

### 3. Registrar Salida de Inventario

**Endpoint:** `POST /inventario/salida`

**Autenticación:** Requerida (sesión activa)

**Description:** Registra salida/venta de productos

**Request:**
```json
{
  "producto_id": 5,
  "cantidad": 30,
  "tipo_salida": "venta",
  "precio_venta": 36000,
  "referencia": "FAC-001",
  "notas": "Venta en mostrador"
}
```

**Parámetros:**
| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| producto_id | int | ✅ | ID del producto |
| cantidad | int | ✅ | Cantidad a vender |
| tipo_salida | string | ❌ | 'venta', 'devolución', 'merma' (default: 'venta') |
| precio_venta | float | ❌ | Precio unitario de venta |
| referencia | string | ❌ | Referencia (factura, etc) |
| notas | string | ❌ | Notas adicionales |

**Response (201 - Exitoso):**
```json
{
  "success": true,
  "data": {
    "salida_id": 156,
    "mensaje": "Salida registrada correctamente",
    "producto_id": 5,
    "cantidad": 30,
    "tipo_salida": "venta"
  },
  "message": "Salida registrada correctamente"
}
```

**Response (400 - Error - Stock insuficiente):**
```json
{
  "success": false,
  "message": "Stock insuficiente. Disponible: 45",
  "data": null
}
```

**Notas:**
- Valida stock disponible
- Usa `BEGIN IMMEDIATE` para serializar operaciones
- Si hay concurrencia, espera el bloqueo
- Rechaza si no hay suficiente stock
- Registra costo y ganancia automáticamente

---

### 4. Consultar Stock

**Endpoint:** `GET /inventario/stock/{producto_id}`

**Autenticación:** Requerida (sesión activa)

**Description:** Obtiene stock actual de un producto

**Request:**
```bash
curl http://192.168.1.100/nombre_de_empresa/api/v1/inventario/stock/5 \
  -H "Cookie: PHPSESSID=abc123def456xyz"
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "producto_id": 5,
    "nombre": "Laptop Dell",
    "codigo": "DELL-001",
    "precio": 1500000,
    "stock": 45,
    "disponible": true
  },
  "message": "Stock obtenido correctamente"
}
```

**Response (404 - No encontrado):**
```json
{
  "success": false,
  "message": "Producto no encontrado",
  "data": null
}
```

---

### 5. Listar Movimientos

**Endpoint:** `GET /inventario/movimientos`

**Autenticación:** Requerida (sesión activa)

**Description:** Obtiene historial de movimientos con paginación

**Request:**
```bash
curl "http://192.168.1.100/nombre_de_empresa/api/v1/inventario/movimientos?producto_id=5&tipo=salida&limit=10&offset=0" \
  -H "Cookie: PHPSESSID=abc123def456xyz"
```

**Parámetros (Query String):**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| producto_id | int | Filtrar por producto (opcional) |
| tipo | string | Filtrar por tipo: 'entrada', 'salida' (opcional) |
| limit | int | Registros por página (default: 50, máx: 500) |
| offset | int | Desplazamiento (default: 0) |

**Response (200):**
```json
{
  "success": true,
  "data": {
    "movimientos": [
      {
        "id": 156,
        "producto_id": 5,
        "tipo_movimiento": "salida",
        "cantidad": 30,
        "stock_anterior": 75,
        "stock_nuevo": 45,
        "usuario_id": 1,
        "descripcion": "Salida por venta",
        "fecha_movimiento": "2026-09-01 14:30:15"
      },
      {
        "id": 155,
        "producto_id": 5,
        "tipo_movimiento": "entrada",
        "cantidad": 100,
        "stock_anterior": 0,
        "stock_nuevo": 100,
        "usuario_id": 1,
        "descripcion": "Entrada de inventario",
        "fecha_movimiento": "2026-09-01 14:20:00"
      }
    ],
    "total": 2,
    "limit": 10,
    "offset": 0,
    "paginas": 1
  },
  "message": "Movimientos obtenidos correctamente"
}
```

---

## Ejemplos de Uso

### Ejemplo 1: Flujo completo en Caja 2

```bash
#!/bin/bash
BASE_URL="http://192.168.1.100/nombre_de_empresa/api/v1"
COOKIE="cookies.txt"

# Paso 1: Login
curl -X POST "$BASE_URL/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "caja2@empresa.com",
    "password": "contraseña123"
  }' \
  -c $COOKIE

# Paso 2: Registrar entrada de 100 unidades
curl -X POST "$BASE_URL/inventario/entrada" \
  -H "Content-Type: application/json" \
  -b $COOKIE \
  -d '{
    "producto_id": 5,
    "proveedor_id": 1,
    "cantidad": 100,
    "precio_compra": 30000,
    "porcentaje_ganancia": 20
  }'

# Paso 3: Vender 30 unidades
curl -X POST "$BASE_URL/inventario/salida" \
  -H "Content-Type: application/json" \
  -b $COOKIE \
  -d '{
    "producto_id": 5,
    "cantidad": 30,
    "tipo_salida": "venta",
    "precio_venta": 36000,
    "referencia": "FAC-2026-001"
  }'

# Paso 4: Consultar stock actual
curl -X GET "$BASE_URL/inventario/stock/5" \
  -b $COOKIE

# Paso 5: Ver movimientos
curl -X GET "$BASE_URL/inventario/movimientos?producto_id=5&limit=5" \
  -b $COOKIE
```

### Ejemplo 2: Concurrencia desde 2 cajas simultáneamente

**Caja 2 (PowerShell):**
```powershell
$base = "http://192.168.1.100/nombre_de_empresa/api/v1"

# Login Caja 2
$login2 = Invoke-RestMethod -Uri "$base/auth/login" -Method POST -ContentType "application/json" -Body '{"email":"caja2@empresa.com","password":"pass123"}'

# Caja 2: Vender 40 unidades (toma bloqueo)
Invoke-RestMethod -Uri "$base/inventario/salida" -Method POST -ContentType "application/json" -Headers @{Cookie="PHPSESSID=$($login2.data.token)"} -Body '{"producto_id":5,"cantidad":40}'
```

**Caja 3 (PowerShell):**
```powershell
$base = "http://192.168.1.100/nombre_de_empresa/api/v1"

# Login Caja 3
$login3 = Invoke-RestMethod -Uri "$base/auth/login" -Method POST -ContentType "application/json" -Body '{"email":"caja3@empresa.com","password":"pass123"}'

# Caja 3: Vender 40 unidades (espera bloqueo de Caja 2)
Invoke-RestMethod -Uri "$base/inventario/salida" -Method POST -ContentType "application/json" -Headers @{Cookie="PHPSESSID=$($login3.data.token)"} -Body '{"producto_id":5,"cantidad":40}'
```

**Resultado esperado:**
- Caja 2 completa primero (BEGIN IMMEDIATE adquiere bloqueo)
- Caja 3 espera (bloqueada)
- Stock final: correcto, sin duplicaciones

---

## Códigos de Error

| Código HTTP | Significado | Ejemplo |
|-------------|-----------|---------|
| 200 | ✅ Éxito (GET) | Consulta completada |
| 201 | ✅ Creado (POST) | Entrada/Salida registrada |
| 400 | ❌ Solicitud inválida | Parámetro faltante o incorrecto |
| 401 | ❌ No autenticado | Sesión expirada o inválida |
| 404 | ❌ No encontrado | Producto/Recurso no existe |
| 500 | ❌ Error servidor | Error interno, revisar logs |

---

## Rate Limiting

**Límites actuales:**
- Sin límite de rate por sesión (confianza en red local)
- Máximo 500 registros por página en movimientos
- Conexión timeout: 30 segundos

**Para futuro:**
- Implementar límite de 100 req/min por caja
- Implementar throttling por IP
- Cache de consultas frecuentes

---

## CORS

**Orígenes permitidos:**
- `*` (todos en red local)

**Headers permitidos:**
- GET, POST, OPTIONS
- Content-Type, Authorization

**Preflight:**
- Automático, sin configuración requerida

---

## Notas de Seguridad

### ✅ Implementado
- ✅ Validación de entrada
- ✅ Autenticación obligatoria
- ✅ Transacciones protegidas
- ✅ Auditoría de todas las operaciones
- ✅ CORS configurado

### 🔍 Consideraciones
- El API asume red local segura
- No usar en internet sin HTTPS
- No usar sin firewall
- Cambiar credenciales default

### 🔐 Para producción
- Implementar HTTPS/SSL
- Implementar rate limiting
- Implementar API keys
- Implementar logging avanzado
- Implementar backup automático

---

## Changelog

### v1.0 (2026-09-01)
- ✅ Inicial release
- ✅ Endpoints de entrada/salida
- ✅ Consulta de stock
- ✅ Auditoría de movimientos
- ✅ Autenticación por sesión
- ✅ Protección concurrencia

---

**Fin de Documentación del API**
